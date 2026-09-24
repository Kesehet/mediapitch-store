<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use DateTimeImmutable;
use DateTimeZone;
use MediaPitch\Repositories\NewsletterRepository;
use MediaPitch\Repositories\SenderCampaignRepository;
use MediaPitch\Repositories\SenderQueueRepository;
use MediaPitch\Repositories\SettingsRepository;

final class SenderCampaignService
{
    public function __construct(
        private readonly SenderCampaignRepository $repo = new SenderCampaignRepository(),
        private readonly SenderClient $sender = new SenderClient(),
        private readonly EmailValidationClient $validator = new EmailValidationClient(),
        private readonly SettingsRepository $settings = new SettingsRepository(),
        private readonly SenderQueueRepository $queueRepo = new SenderQueueRepository(),
        private readonly NewsletterRepository $newsletter = new NewsletterRepository(),
        private readonly SubscriberMergeService $subscribers = new SubscriberMergeService()
    ) {
    }

    /** @return array{start_utc:string,end_utc:string,date:string} */
    public function dailyWindow(): array
    {
        $timezone = new DateTimeZone((string)\env('CONTENT_TIMEZONE', 'Asia/Kolkata'));
        $startLocal = new DateTimeImmutable('today', $timezone);
        $endLocal = $startLocal->modify('+1 day');
        $utc = new DateTimeZone('UTC');

        return [
            'start_utc' => $startLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
            'end_utc' => $endLocal->setTimezone($utc)->format('Y-m-d H:i:s'),
            'date' => $startLocal->format('Y-m-d'),
        ];
    }

    public function remainingToday(): int
    {
        $window = $this->dailyWindow();
        $limit = (int)$this->settings->sender()['daily_limit'];
        $transactional = $this->queueRepo->countSentBetween($window['start_utc'], $window['end_utc']);
        $campaign = $this->repo->countDispatchedBetween($window['start_utc'], $window['end_utc']);
        return max(0, $limit - $transactional - $campaign);
    }

    /** @return array<string,int|string> */
    public function stats(): array
    {
        $window = $this->dailyWindow();
        $stats = $this->repo->stats();
        $stats['sent_today'] = $this->repo->countDispatchedBetween($window['start_utc'], $window['end_utc']);
        $stats['remaining_today'] = $this->remainingToday();
        $stats['daily_limit'] = (int)$this->settings->sender()['daily_limit'];
        $stats['local_date'] = $window['date'];
        return $stats;
    }

    /** @return array<string,mixed> */
    public function queueCampaign(string $campaignId, int $createdBy, bool $consentConfirmed, bool $autoContinue = true): array
    {
        if (!$consentConfirmed) {
            throw new \InvalidArgumentException('Confirm that these active newsletter subscribers may receive this campaign.');
        }
        if (!$this->sender->configured()) {
            throw new SenderApiException('Sender API token is not configured.');
        }

        $campaign = $this->sender->campaign($campaignId);
        $snapshot = $this->snapshotCampaign($campaign);
        $candidates = $this->subscribers->campaignCandidates();

        if ($candidates === []) {
            throw new \InvalidArgumentException('There are no active merged subscribers to clean and queue.');
        }

        $emails = array_values(array_unique(array_map(
            static fn(array $row): string => strtolower(trim((string)($row['email'] ?? ''))),
            $candidates
        )));
        $validations = $this->validator->validateMany($emails, 10);

        $recipients = [];
        $rejected = 0;
        $unknown = 0;
        $risky = 0;
        $invalid = 0;

        foreach ($candidates as $candidate) {
            $email = strtolower(trim((string)($candidate['email'] ?? '')));
            $validation = $validations[$email] ?? [
                'status' => 'unknown',
                'reason' => 'No validation result was returned',
            ];
            $status = strtolower(trim((string)($validation['status'] ?? 'unknown')));
            $reason = trim((string)($validation['reason'] ?? ''));

            $localId = (int)($candidate['id'] ?? 0);
            if ($localId > 0) {
                $this->newsletter->recordValidation($localId, $status, $reason);
            }

            if ($status !== 'clean') {
                $rejected++;
                if ($status === 'unknown') $unknown++;
                elseif ($status === 'risky') $risky++;
                elseif ($status === 'invalid') $invalid++;
                continue;
            }

            $recipients[] = [
                'email' => $email,
                'name' => trim((string)($candidate['name'] ?? '')),
                'id' => $localId,
            ];
        }

        if ($recipients === []) {
            throw new \InvalidArgumentException(
                'The merged audience had ' . count($candidates) .
                ' active subscriber(s), but none passed the live email cleaner as clean.'
            );
        }

        $run = $this->repo->createRun($snapshot, $recipients, $createdBy, $autoContinue);
        if (empty($run['id']) || (int)($run['total_recipients'] ?? 0) < 1) {
            throw new \RuntimeException('Campaign queue could not be created.');
        }

        $run['audience_candidates'] = count($candidates);
        $run['clean_queued'] = count($recipients);
        $run['rejected_before_queue'] = $rejected;
        $run['unknown_before_queue'] = $unknown;
        $run['risky_before_queue'] = $risky;
        $run['invalid_before_queue'] = $invalid;

        return $run;
    }

    /** @return array{examined:int,dispatched:int,blocked:int,retried:int,failed:int,batches:int,remaining_today:int} */
    public function processRun(int $runId, int $requested = 50): array
    {
        if (!$this->repo->acquireWorkerLock()) {
            throw new \RuntimeException('Another Sender queue worker is already running.');
        }

        try {
            return $this->processRunUnlocked($runId, $requested);
        } finally {
            $this->repo->releaseWorkerLock();
        }
    }

    /** @return array{examined:int,dispatched:int,blocked:int,retried:int,failed:int,batches:int,remaining_today:int} */
    public function processReadyRuns(int $requested = 50): array
    {
        if (!$this->sender->configured()) {
            throw new SenderApiException('Sender API token is not configured.');
        }
        if (!$this->repo->acquireWorkerLock()) {
            throw new \RuntimeException('Another Sender queue worker is already running.');
        }

        $summary = [
            'examined' => 0,
            'dispatched' => 0,
            'blocked' => 0,
            'retried' => 0,
            'failed' => 0,
            'batches' => 0,
            'remaining_today' => $this->remainingToday(),
        ];

        try {
            $requested = max(1, min(100, $requested));
            $remainingRequest = min($requested, $summary['remaining_today']);

            foreach ($this->repo->activeRuns() as $run) {
                if ($remainingRequest < 1 || $summary['remaining_today'] < 1) break;
                $result = $this->processRunUnlocked((int)$run['id'], $remainingRequest);

                foreach (['examined','dispatched','blocked','retried','failed','batches'] as $key) {
                    $summary[$key] += (int)$result[$key];
                }
                $summary['remaining_today'] = (int)$result['remaining_today'];
                $remainingRequest = max(0, $remainingRequest - (int)$result['dispatched']);
            }

            return $summary;
        } finally {
            $this->repo->releaseWorkerLock();
        }
    }

    public function pause(int $runId): void
    {
        $run = $this->requireRun($runId);
        if (in_array((string)$run['status'], ['completed','cancelled'], true)) return;
        $this->repo->setRunStatus($runId, 'paused');
    }

    public function resume(int $runId): void
    {
        $run = $this->requireRun($runId);
        if (in_array((string)$run['status'], ['completed','cancelled'], true)) {
            throw new \InvalidArgumentException('Completed or cancelled campaign queues cannot be resumed.');
        }
        $this->repo->setRunStatus($runId, 'active');
        $this->repo->setAutoContinue($runId, true);
    }

    public function cancel(int $runId): void
    {
        $run = $this->requireRun($runId);
        if ((string)$run['status'] === 'completed') return;
        $this->repo->setRunStatus($runId, 'cancelled');
        $this->repo->setAutoContinue($runId, false);
    }

    /**
     * Called only while the shared Sender worker lock is held.
     *
     * @return array{examined:int,dispatched:int,blocked:int,retried:int,failed:int,batches:int,remaining_today:int}
     */
    private function processRunUnlocked(int $runId, int $requested): array
    {
        if (!$this->sender->configured()) {
            throw new SenderApiException('Sender API token is not configured.');
        }

        $run = $this->requireRun($runId);
        if (!in_array((string)$run['status'], ['queued','active'], true)) {
            throw new \InvalidArgumentException('This campaign queue is not active.');
        }

        $remainingToday = $this->remainingToday();
        $requested = max(1, min(100, $requested));
        $target = min($requested, $remainingToday);

        $summary = [
            'examined' => 0,
            'dispatched' => 0,
            'blocked' => 0,
            'retried' => 0,
            'failed' => 0,
            'batches' => 0,
            'remaining_today' => $remainingToday,
        ];

        if ($target < 1) return $summary;

        $candidates = $this->repo->readyRecipients($runId, min(500, max($target * 4, $target)));
        $ready = [];

        foreach ($candidates as $recipient) {
            if (count($ready) >= $target) break;

            $id = (int)$recipient['id'];
            $email = strtolower(trim((string)$recipient['recipient_email']));
            $attempt = (int)($recipient['attempts'] ?? 0) + 1;

            $this->repo->markProcessing($id);
            $summary['examined']++;

            $validation = $this->validator->validate($email);
            $status = strtolower(trim((string)($validation['status'] ?? 'unknown')));
            $reason = trim((string)($validation['reason'] ?? ''));
            $this->repo->markValidation($id, $status, $reason);

            if ($status === 'clean') {
                $recipient['attempts'] = $attempt;
                $ready[] = $recipient;
                continue;
            }

            $message = 'List cleaner returned ' . ($status !== '' ? $status : 'unknown');
            if ($reason !== '') $message .= ': ' . $reason;

            if ($status === 'unknown' && $attempt < 3) {
                $this->repo->markRetry($id, $message, 1800);
                $summary['retried']++;
            } elseif ($status === 'unknown') {
                $this->repo->markFailedMany([$id], $message);
                $summary['failed']++;
            } else {
                $this->repo->markBlocked($id, $message);
                $summary['blocked']++;
            }
        }

        if ($ready === []) {
            $this->repo->refreshRunStats($runId);
            return $summary;
        }

        $batchNo = $this->repo->nextBatchNo($runId);
        $groupTitle = sprintf(
            'MediaPitch batch %d-%d %s',
            $runId,
            $batchNo,
            $this->dailyWindow()['date']
        );
        $recipientIds = array_map(static fn(array $row): int => (int)$row['id'], $ready);
        $emails = array_values(array_unique(array_map(
            static fn(array $row): string => strtolower(trim((string)$row['recipient_email'])),
            $ready
        )));
        $batchId = $this->repo->createBatch($runId, $batchNo, $groupTitle, count($emails));
        $providerCampaignId = null;

        try {
            $groupId = $this->sender->createGroup($groupTitle);
            $this->repo->setBatchGroup($batchId, $groupId);

            $groupResult = $this->sender->addSubscribersToGroup($groupId, $emails, false);
            $missing = $this->extractMissingSubscribers($groupResult);

            if ($missing !== []) {
                $byEmail = [];
                foreach ($ready as $row) $byEmail[strtolower((string)$row['recipient_email'])] = $row;

                foreach ($missing as $email) {
                    $row = $byEmail[$email] ?? [];
                    try {
                        $this->sender->createSubscriber(
                            $email,
                            (string)($row['recipient_name'] ?? ''),
                            [$groupId],
                            false
                        );
                    } catch (SenderApiException $e) {
                        if (!in_array($e->statusCode, [409, 422], true)) throw $e;
                    }
                }

                $retryGroup = $this->sender->addSubscribersToGroup($groupId, $missing, false);
                $stillMissing = $this->extractMissingSubscribers($retryGroup);
                if ($stillMissing !== []) {
                    throw new SenderApiException(
                        'Sender could not create or attach ' . count($stillMissing) . ' campaign recipient(s).'
                    );
                }
            }

            $providerCampaign = $this->sender->createCampaign([
                'title' => sprintf('[MediaPitch batch] %s · %s · #%d', (string)($run['source_title'] ?: $run['subject']), $this->dailyWindow()['date'], $batchNo),
                'subject' => (string)$run['subject'],
                'from' => (string)$run['from_name'],
                'reply_to' => (string)$run['reply_to'],
                'preheader' => (string)($run['preheader'] ?? ''),
                'content_type' => (string)$run['content_type'],
                'google_analytics' => 1,
                'auto_followup_active' => false,
                'groups' => [$groupId],
                'segments' => [],
                'content' => (string)$run['content'],
            ]);

            $providerCampaignId = $this->extractId($providerCampaign, 'campaign');
            $this->repo->setBatchCampaign($batchId, $providerCampaignId);

            $this->sender->sendCampaign($providerCampaignId);
            $this->repo->markBatchSent($batchId, $recipientIds);
            $this->repo->refreshRunStats($runId);

            $summary['dispatched'] = count($emails);
            $summary['batches'] = 1;
            $summary['remaining_today'] = max(0, $remainingToday - count($emails));

            return $summary;
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->repo->markBatchFailed($batchId, $message);

            if ($providerCampaignId !== null) {
                // The provider campaign exists and the send request may have reached Sender.
                // Do not auto-retry the same recipients: pausing avoids duplicate marketing mail.
                $this->repo->markFailedMany($recipientIds, 'Campaign batch needs review: ' . $message);
                $this->repo->setRunStatus($runId, 'paused', 'Batch needs review before retrying: ' . $message);
                $summary['failed'] += count($recipientIds);
            } else {
                $delay = $e instanceof SenderApiException && $e->retryAfter
                    ? $e->retryAfter
                    : 900;
                $this->repo->requeueMany($recipientIds, $message, $delay);
                $summary['retried'] += count($recipientIds);
            }

            $this->repo->refreshRunStats($runId);
            return $summary;
        }
    }

    /** @param array<string,mixed> $campaign @return array<string,mixed> */
    private function snapshotCampaign(array $campaign): array
    {
        $id = trim((string)($campaign['id'] ?? ''));
        $subject = trim((string)($campaign['subject'] ?? ''));
        $from = trim((string)($campaign['from'] ?? ''));
        $replyTo = trim((string)($campaign['reply_to'] ?? ''));
        $html = is_array($campaign['html'] ?? null) ? $campaign['html'] : [];
        $content = trim((string)($html['html_content'] ?? $campaign['html_content'] ?? $campaign['content'] ?? ''));
        $contentType = str_contains($content, '<') ? 'html' : 'text';

        if ($id === '' || $subject === '' || $from === '' || $replyTo === '' || $content === '') {
            throw new SenderApiException(
                'This Sender campaign does not expose enough subject, sender, reply-to and content data to queue safely.'
            );
        }

        return [
            'source_campaign_id' => $id,
            'source_title' => trim((string)($campaign['title'] ?? $subject)),
            'subject' => $subject,
            'preheader' => trim((string)($campaign['preheader'] ?? '')),
            'from_name' => $from,
            'reply_to' => $replyTo,
            'content_type' => $contentType,
            'content' => $content,
        ];
    }

    /** @param array<string,mixed> $response @return array<int,string> */
    private function extractMissingSubscribers(array $response): array
    {
        $message = is_array($response['message'] ?? null) ? $response['message'] : [];
        $rows = $message['non_existing_subscribers'] ?? $response['non_existing_subscribers'] ?? [];
        if (!is_array($rows)) return [];

        $out = [];
        foreach ($rows as $email) {
            $email = strtolower(trim((string)$email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $out[$email] = $email;
        }
        return array_values($out);
    }

    /** @param array<string,mixed> $response */
    private function extractId(array $response, string $kind): string
    {
        $id = trim((string)($response['data']['id'] ?? $response['id'] ?? ''));
        if ($id === '') {
            throw new SenderApiException('Sender returned the ' . $kind . ' without an ID.');
        }
        return $id;
    }

    /** @return array<string,mixed> */
    private function requireRun(int $runId): array
    {
        $run = $this->repo->run($runId);
        if (!$run) throw new \InvalidArgumentException('Campaign queue not found.');
        return $run;
    }
}
