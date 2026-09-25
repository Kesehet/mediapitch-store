<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use DateTimeImmutable;
use DateTimeZone;
use MediaPitch\Repositories\NewsletterRepository;
use MediaPitch\Repositories\SenderCampaignRepository;
use MediaPitch\Repositories\SenderQueueRepository;
use MediaPitch\Repositories\SenderSubscriberCacheRepository;
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
        private readonly SubscriberMergeService $subscribers = new SubscriberMergeService(),
        private readonly SenderSubscriberCacheRepository $subscriberCache = new SenderSubscriberCacheRepository()
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

        $snapshotFallback = false;
        try {
            $campaign = $this->sender->campaign($campaignId);
            $snapshot = $this->snapshotCampaign($campaign);
        } catch (SenderApiException $e) {
            if (!$e->retryable()) throw $e;
            $snapshot = $this->repo->latestSourceSnapshot($campaignId);
            if (!$snapshot) throw $e;
            $snapshotFallback = true;
        }

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
        $run['source_snapshot_fallback'] = $snapshotFallback;

        return $run;
    }

    /** @return array{examined:int,dispatched:int,blocked:int,retried:int,failed:int,batches:int,remaining_today:int,recovered_stale:int,queued_ready:int,queued_waiting:int,processing:int,next_retry_at:?string,sender_cooldown_until:?string,api_remaining:?int,api_deferred:int,reconciled_sent:int,uncertain_batches:int} */
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

    /** @return array{examined:int,dispatched:int,blocked:int,retried:int,failed:int,batches:int,remaining_today:int,recovered_stale:int,queued_ready:int,queued_waiting:int,processing:int,next_retry_at:?string,sender_cooldown_until:?string,api_remaining:?int,api_deferred:int,reconciled_sent:int,uncertain_batches:int} */
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
            'recovered_stale' => 0,
            'queued_ready' => 0,
            'queued_waiting' => 0,
            'processing' => 0,
            'next_retry_at' => null,
            'sender_cooldown_until' => null,
            'api_remaining' => null,
            'api_deferred' => 0,
            'reconciled_sent' => 0,
            'uncertain_batches' => 0,
        ];

        try {
            $requested = max(1, min(100, $requested));
            $remainingRequest = min($requested, $summary['remaining_today']);

            foreach ($this->repo->activeRuns() as $run) {
                if ($remainingRequest < 1 || $summary['remaining_today'] < 1) break;
                $result = $this->processRunUnlocked((int)$run['id'], $remainingRequest);

                foreach (['examined','dispatched','blocked','retried','failed','batches','recovered_stale'] as $key) {
                    $summary[$key] += (int)$result[$key];
                }
                $summary['remaining_today'] = (int)$result['remaining_today'];
                $summary['queued_ready'] = (int)$result['queued_ready'];
                $summary['queued_waiting'] = (int)$result['queued_waiting'];
                $summary['processing'] = (int)$result['processing'];
                $summary['next_retry_at'] = $result['next_retry_at'];
                $summary['sender_cooldown_until'] = $result['sender_cooldown_until'];
                $summary['api_remaining'] = $result['api_remaining'];
                $summary['api_deferred'] += (int)$result['api_deferred'];
                $summary['reconciled_sent'] += (int)$result['reconciled_sent'];
                $summary['uncertain_batches'] += (int)$result['uncertain_batches'];
                $remainingRequest = max(0, $remainingRequest - (int)$result['dispatched']);

                // Do not move to another campaign while the previous provider outcome is
                // unknown, the API circuit breaker is active, or the reported API budget
                // is too low to safely construct a batch.
                if (
                    (int)$result['uncertain_batches'] > 0 ||
                    !empty($result['sender_cooldown_until']) ||
                    ((int)$result['api_deferred'] > 0 && (int)$result['dispatched'] === 0)
                ) {
                    break;
                }
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
     * @return array{examined:int,dispatched:int,blocked:int,retried:int,failed:int,batches:int,remaining_today:int,recovered_stale:int,queued_ready:int,queued_waiting:int,processing:int,next_retry_at:?string,sender_cooldown_until:?string,api_remaining:?int,api_deferred:int,reconciled_sent:int,uncertain_batches:int}
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
            'recovered_stale' => 0,
            'queued_ready' => 0,
            'queued_waiting' => 0,
            'processing' => 0,
            'next_retry_at' => null,
            'sender_cooldown_until' => null,
            'api_remaining' => null,
            'api_deferred' => 0,
            'reconciled_sent' => 0,
            'uncertain_batches' => 0,
        ];

        if ($target < 1) return $summary;

        try {
            $this->sender->assertApiAvailable();
        } catch (SenderApiException $e) {
            if ($e->statusCode === 429) {
                $api = $this->sender->apiStatus();
                $summary['sender_cooldown_until'] = $this->nonEmpty((string)($api['cooldown_until'] ?? ''));
                $summary['api_remaining'] = isset($api['rate_limit_remaining']) ? (int)$api['rate_limit_remaining'] : null;
                $summary['recovered_stale'] = $this->repo->recoverStaleProcessing($runId);
                $state = $this->repo->recipientState($runId);
                $summary['queued_ready'] = $state['queued_ready'];
                $summary['queued_waiting'] = $state['queued_waiting'];
                $summary['processing'] = $state['processing'];
                $summary['next_retry_at'] = $state['next_retry_at'];
                return $summary;
            }
            throw $e;
        }

        $reconciled = $this->reconcileUncertainBatches($runId);
        $summary['reconciled_sent'] = $reconciled['sent'];
        $summary['uncertain_batches'] = $reconciled['unresolved'];

        if ($reconciled['sent'] > 0 || $reconciled['requeued'] > 0 || $reconciled['unresolved'] > 0) {
            $this->repo->refreshRunStats($runId);
            $state = $this->repo->recipientState($runId);
            $summary['queued_ready'] = $state['queued_ready'];
            $summary['queued_waiting'] = $state['queued_waiting'];
            $summary['processing'] = $state['processing'];
            $summary['next_retry_at'] = $state['next_retry_at'];
            $summary['remaining_today'] = $this->remainingToday();
            $api = $this->sender->apiStatus();
            $summary['sender_cooldown_until'] = $this->nonEmpty((string)($api['cooldown_until'] ?? ''));
            $summary['api_remaining'] = isset($api['rate_limit_remaining']) ? (int)$api['rate_limit_remaining'] : null;
            return $summary;
        }

        $summary['recovered_stale'] = $this->repo->recoverStaleProcessing($runId);
        $state = $this->repo->recipientState($runId);
        $summary['queued_ready'] = $state['queued_ready'];
        $summary['queued_waiting'] = $state['queued_waiting'];
        $summary['processing'] = $state['processing'];
        $summary['next_retry_at'] = $state['next_retry_at'];

        $candidates = $this->repo->readyRecipients($runId, min(500, max($target * 4, $target)));
        if ($candidates === []) {
            return $summary;
        }

        $localIds=array_values(array_unique(array_filter(array_map(
            static fn(array $row): int => (int)($row['newsletter_subscriber_id']??0),
            $candidates
        ))));
        $localActive=$this->newsletter->activeIdMap($localIds);

        $ready = [];

        foreach ($candidates as $recipient) {
            if (count($ready) >= $target) break;

            $id = (int)$recipient['id'];
            $email = strtolower(trim((string)$recipient['recipient_email']));
            $localSubscriberId=(int)($recipient['newsletter_subscriber_id']??0);
            $attempt = (int)($recipient['attempts'] ?? 0) + 1;

            $summary['examined']++;

            if($localSubscriberId>0 && empty($localActive[$localSubscriberId])){
                $this->repo->markBlocked(
                    $id,
                    'Local newsletter subscriber is no longer active; suppressed before campaign send.'
                );
                $summary['blocked']++;
                continue;
            }

            $this->repo->markProcessing($id);

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

            if ($status === 'unknown') {
                // Unknown means the cleaner could not make a reliable decision (including
                // temporary service/network issues). Never send, but also never discard a
                // subscriber merely because infrastructure was unavailable.
                $delay = min(21600, 1800 * (2 ** min(4, max(0, $attempt - 1))));
                $this->repo->markRetry($id, $message, $delay);
                $summary['retried']++;
            } else {
                $this->repo->markBlocked($id, $message);
                $summary['blocked']++;
            }
        }

        if ($ready === []) {
            $this->repo->refreshRunStats($runId);
            $state = $this->repo->recipientState($runId);
            $summary['queued_ready'] = $state['queued_ready'];
            $summary['queued_waiting'] = $state['queued_waiting'];
            $summary['processing'] = $state['processing'];
            $summary['next_retry_at'] = $state['next_retry_at'];
            $api = $this->sender->apiStatus();
            $summary['sender_cooldown_until'] = $this->nonEmpty((string)($api['cooldown_until'] ?? ''));
            $summary['api_remaining'] = isset($api['rate_limit_remaining']) ? (int)$api['rate_limit_remaining'] : $summary['api_remaining'];
            return $summary;
        }

        $budgeted = $this->fitReadyToApiBudget($ready);
        $selectedReady = $budgeted['selected'];
        $deferredReady = $budgeted['deferred'];
        $summary['api_remaining'] = $budgeted['api_remaining'];

        if ($deferredReady !== []) {
            $delay = $this->senderApiRetryDelay();
            $this->repo->requeueMany(
                array_map(static fn(array $row): int => (int)$row['id'], $deferredReady),
                'Deferred to preserve Sender API request budget.',
                $delay
            );
            $summary['api_deferred'] += count($deferredReady);
        }

        if ($selectedReady === []) {
            $this->repo->refreshRunStats($runId);
            $state = $this->repo->recipientState($runId);
            $summary['queued_ready'] = $state['queued_ready'];
            $summary['queued_waiting'] = $state['queued_waiting'];
            $summary['processing'] = $state['processing'];
            $summary['next_retry_at'] = $state['next_retry_at'];
            $api = $this->sender->apiStatus();
            $summary['sender_cooldown_until'] = $this->nonEmpty((string)($api['cooldown_until'] ?? ''));
            return $summary;
        }

        $ready = $selectedReady;

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
        $this->repo->attachRecipientsToBatch($batchId, $recipientIds);
        $providerCampaignId = null;

        try {
            $groupId = $this->sender->createGroup($groupTitle);
            $this->repo->setBatchGroup($batchId, $groupId);

            $groupResult = $this->sender->addSubscribersToGroup($groupId, $emails, false);
            $missing = $this->extractMissingSubscribers($groupResult);

            if ($missing !== []) {
                $byEmail = [];
                foreach ($ready as $row) $byEmail[strtolower((string)$row['recipient_email'])] = $row;

                $knownSenderPresence = $this->subscriberCache->presenceMap($missing);
                foreach ($missing as $email) {
                    // If our last complete Sender snapshot knew this subscriber but the
                    // live API now says they do not exist, do not silently recreate them.
                    // A provider-side unsubscribe/removal may have occurred since the
                    // snapshot; require a fresh audience sync first.
                    if (!empty($knownSenderPresence[$email])) {
                        throw new SenderApiException(
                            'Subscriber ' . $email . ' was present in the cached Sender audience but is now missing. Refresh the Sender subscriber snapshot before sending to avoid an accidental re-subscribe.',
                            422
                        );
                    }

                    $row = $byEmail[$email] ?? [];
                    try {
                        $this->sender->createSubscriber(
                            $email,
                            (string)($row['recipient_name'] ?? ''),
                            [$groupId],
                            false
                        );
                    } catch (SenderApiException $e) {
                        // 409 means the subscriber already exists, which is harmless.
                        // A 422 is a real validation/business error and must pause the run.
                        if ($e->statusCode !== 409) throw $e;
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
            $state = $this->repo->recipientState($runId);
            $summary['queued_ready'] = $state['queued_ready'];
            $summary['queued_waiting'] = $state['queued_waiting'];
            $summary['processing'] = $state['processing'];
            $summary['next_retry_at'] = $state['next_retry_at'];
            $api = $this->sender->apiStatus();
            $summary['sender_cooldown_until'] = $this->nonEmpty((string)($api['cooldown_until'] ?? ''));
            $summary['api_remaining'] = isset($api['rate_limit_remaining']) ? (int)$api['rate_limit_remaining'] : $summary['api_remaining'];

            return $summary;
        } catch (\Throwable $e) {
            $message = $e->getMessage();

            if ($e instanceof SenderApiException && $e->statusCode === 429) {
                // A 429 explicitly rejects this request. The provider has not accepted
                // a new send, so this batch can safely return to the retry queue.
                $this->repo->markBatchFailed($batchId, $message);
                $delay = $e->retryAfter ?? 900;
                $this->repo->requeueMany($recipientIds, 'Sender rate limit: ' . $message, $delay);
                $summary['retried'] += count($recipientIds);
            } elseif ($e instanceof SenderApiException && !$e->retryable()) {
                // A definite non-retryable 4xx requires admin attention, but no automatic
                // replay. Keep the audience queued and pause the campaign.
                $this->repo->markBatchFailed($batchId, $message);
                $this->repo->returnToQueueMany($recipientIds, 'Sender requires attention: ' . $message);
                $this->repo->setRunStatus($runId, 'paused', 'Sender requires attention: ' . $message);
            } elseif ($providerCampaignId !== null) {
                // Once Sender has returned a campaign ID, a timeout/5xx around the final
                // send call is ambiguous: Sender may have accepted the send before the
                // response was lost. Keep the batch PREPARING and recipients PROCESSING.
                // Reconciliation will inspect the live Sender campaign before any replay.
                $this->repo->markBatchIssue($batchId, 'Ambiguous Sender response: ' . $message);
                $this->repo->setRunStatus(
                    $runId,
                    'paused',
                    'Interrupted Sender batch requires reconciliation before retry: ' . $message
                );
                $summary['uncertain_batches']++;
            } else {
                // No Sender campaign ID exists yet, so the send endpoint was never reached.
                // Retrying this preparation work cannot duplicate an email.
                $this->repo->markBatchFailed($batchId, $message);
                $delay = $e instanceof SenderApiException && $e->retryAfter
                    ? $e->retryAfter
                    : 900;
                $this->repo->requeueMany($recipientIds, $message, $delay);
                $summary['retried'] += count($recipientIds);
            }

            $this->repo->refreshRunStats($runId);
            $state = $this->repo->recipientState($runId);
            $summary['queued_ready'] = $state['queued_ready'];
            $summary['queued_waiting'] = $state['queued_waiting'];
            $summary['processing'] = $state['processing'];
            $summary['next_retry_at'] = $state['next_retry_at'];
            $api = $this->sender->apiStatus();
            $summary['sender_cooldown_until'] = $this->nonEmpty((string)($api['cooldown_until'] ?? ''));
            $summary['api_remaining'] = isset($api['rate_limit_remaining']) && $api['rate_limit_remaining'] !== null
                ? (int)$api['rate_limit_remaining']
                : $summary['api_remaining'];
            return $summary;
        }
    }

    /**
     * Resolve a batch left in "preparing" by an interrupted PHP/worker process.
     * We never send a new batch while Sender's outcome for the old one is unknown.
     *
     * @return array{sent:int,requeued:int,unresolved:int}
     */
    private function reconcileUncertainBatches(int $runId): array
    {
        $result=['sent'=>0,'requeued'=>0,'unresolved'=>0];

        foreach($this->repo->uncertainBatches($runId) as $batch){
            $batchId=(int)$batch['id'];
            $recipientIds=$this->repo->batchRecipientIds($batchId);
            if($recipientIds===[]){
                $this->repo->markBatchFailed($batchId,'Recovered empty interrupted batch.');
                continue;
            }

            $providerCampaignId=trim((string)($batch['provider_campaign_id']??''));
            if($providerCampaignId===''){
                $result['requeued'] += $this->repo->requeueAttachedBatch(
                    $batchId,
                    'Recovered interrupted batch before Sender campaign creation.',
                    60
                );
                continue;
            }

            try{
                $provider=$this->sender->campaignLive($providerCampaignId);
            }catch(SenderApiException $e){
                // Without a live provider state we cannot know whether the send was
                // accepted. Transient errors wait; permanent 4xx errors pause the run.
                if(!$e->retryable()){
                    $this->repo->setRunStatus(
                        $runId,
                        'paused',
                        'Cannot reconcile interrupted Sender batch: '.$e->getMessage()
                    );
                }
                $result['unresolved']++;
                continue;
            }

            $status=strtoupper(trim((string)($provider['status']??'')));
            $sentTime=trim((string)($provider['sent_time']??''));
            $sentCount=(int)($provider['sent_count']??0);

            if(
                $sentTime!=='' ||
                $sentCount>0 ||
                in_array($status,['SENDING','SENT','COMPLETED','DONE','PROCESSING','QUEUED','SCHEDULED'],true)
            ){
                $result['sent'] += $this->repo->markAttachedBatchSent($batchId);
                continue;
            }

            if(in_array($status,['DRAFT','NEW','CREATED',''],true)){
                $createdAt=trim((string)($batch['created_at']??''));
                $createdTs=$createdAt!==''?strtotime($createdAt.' UTC'):false;

                // Sender campaign status can lag briefly after a send request.
                // Keep a fresh interrupted batch attached until the provider has
                // had time to settle, rather than risk creating a duplicate.
                if($createdTs===false || (time()-$createdTs)<300){
                    $result['unresolved']++;
                    continue;
                }

                $result['requeued'] += $this->repo->requeueAttachedBatch(
                    $batchId,
                    'Recovered interrupted batch after reconciliation grace period; Sender still reports draft.',
                    60
                );
                continue;
            }

            // Unknown provider state: pause instead of risking a duplicate send.
            $this->repo->setRunStatus(
                $runId,
                'paused',
                'Interrupted Sender batch has unknown provider status: '.($status!==''?$status:'(empty)')
            );
            $result['unresolved']++;
        }

        return $result;
    }

    /**
     * Keep a campaign batch inside the currently reported Sender API budget.
     * Existing Sender subscribers cost no per-recipient create call; local-only
     * subscribers are budgeted conservatively as one create call each.
     *
     * @param array<int,array<string,mixed>> $ready
     * @return array{selected:array<int,array<string,mixed>>,deferred:array<int,array<string,mixed>>,api_remaining:?int}
     */
    private function fitReadyToApiBudget(array $ready): array
    {
        $api = $this->sender->apiStatus();
        $remaining = isset($api['rate_limit_remaining']) && $api['rate_limit_remaining'] !== null
            ? max(0, (int)$api['rate_limit_remaining'])
            : null;

        if ($remaining === null) {
            return ['selected' => $ready, 'deferred' => [], 'api_remaining' => null];
        }

        // Reserve one request so normal admin/status work is not forced straight
        // into a provider 429. A campaign batch itself needs four API requests:
        // create group, add group members, create campaign, start campaign.
        $usable = max(0, $remaining - 1);
        if ($usable < 4) {
            return ['selected' => [], 'deferred' => $ready, 'api_remaining' => $remaining];
        }

        $emails = array_values(array_unique(array_map(
            static fn(array $row): string => strtolower(trim((string)($row['recipient_email'] ?? ''))),
            $ready
        )));
        $presence = $this->subscriberCache->presenceMap($emails);

        $existing = [];
        $missing = [];
        foreach ($ready as $row) {
            $email = strtolower(trim((string)($row['recipient_email'] ?? '')));
            if (!empty($presence[$email])) $existing[] = $row;
            else $missing[] = $row;
        }

        $selected = $existing;
        $deferred = [];
        $missingAllowance = max(0, $usable - 5); // extra add-to-group retry + one create per missing subscriber

        foreach ($missing as $index => $row) {
            if ($index < $missingAllowance) $selected[] = $row;
            else $deferred[] = $row;
        }

        // If no cached-existing recipients and there is not enough budget for at
        // least one missing subscriber, defer the whole batch.
        if ($selected === [] && $missing !== []) {
            return ['selected' => [], 'deferred' => $ready, 'api_remaining' => $remaining];
        }

        return ['selected' => $selected, 'deferred' => $deferred, 'api_remaining' => $remaining];
    }

    private function senderApiRetryDelay(): int
    {
        $api = $this->sender->apiStatus();
        foreach (['cooldown_until','rate_limit_reset_at'] as $key) {
            $value = trim((string)($api[$key] ?? ''));
            if ($value === '') continue;
            $ts = strtotime($value . ' UTC');
            if ($ts !== false && $ts > time()) {
                return max(60, min(86400, $ts - time() + 5));
            }
        }
        return 300;
    }

    private function nonEmpty(string $value): ?string
    {
        $value = trim($value);
        return $value !== '' ? $value : null;
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
