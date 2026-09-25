<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use DateTimeImmutable;
use DateTimeZone;
use MediaPitch\Repositories\SenderCampaignRepository;
use MediaPitch\Repositories\SenderQueueRepository;
use MediaPitch\Repositories\SettingsRepository;

final class SenderQueueService
{
    public function __construct(
        private readonly SenderQueueRepository $repo = new SenderQueueRepository(),
        private readonly SenderClient $sender = new SenderClient(),
        private readonly EmailValidationClient $validator = new EmailValidationClient(),
        private readonly SettingsRepository $settings = new SettingsRepository(),
        private readonly SenderCampaignRepository $campaigns = new SenderCampaignRepository()
    ) {
    }

    public function dailyLimit(): int
    {
        return (int)$this->settings->sender()['daily_limit'];
    }

    public function batchSize(): int
    {
        return (int)$this->settings->sender()['batch_size'];
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

    /** @return array<string,int|string> */
    public function stats(): array
    {
        $window = $this->dailyWindow();
        $stats = $this->repo->stats($window['start_utc'], $window['end_utc']);
        $transactionalToday = (int)$stats['sent_today'];
        $campaignToday = $this->campaigns->countDispatchedBetween($window['start_utc'], $window['end_utc']);
        $stats['transactional_sent_today'] = $transactionalToday;
        $stats['campaign_sent_today'] = $campaignToday;
        $stats['sent_today'] = $transactionalToday + $campaignToday;
        $stats['daily_limit'] = $this->dailyLimit();
        $stats['remaining_today'] = max(0, $this->dailyLimit() - (int)$stats['sent_today']);
        $stats['local_date'] = $window['date'];

        return $stats;
    }

    /**
     * @return array{added:int,duplicates:int,invalid:int,rejected:int,risky:int,cleaner_invalid:int,unknown:int}
     */
    public function queueFromText(
        string $templateId,
        string $templateTitle,
        string $recipientText,
        string $variablesJson,
        int $createdBy,
        bool $consentConfirmed
    ): array {
        if (!$consentConfirmed) {
            throw new \InvalidArgumentException('Confirm that these recipients are permitted to receive this email.');
        }

        $recipients = $this->parseRecipients($recipientText);
        if ($recipients['valid'] === []) {
            throw new \InvalidArgumentException('Add at least one valid recipient email.');
        }

        $cleaning = $this->cleanBeforeQueue($recipients['valid']);
        if ($cleaning['clean'] === []) {
            throw new \InvalidArgumentException('No recipient passed the live email cleaner as clean, so nothing was queued.');
        }

        $variables = $this->parseVariables($variablesJson);
        $result = $this->repo->queueMany(
            trim($templateId),
            trim($templateTitle),
            $cleaning['clean'],
            $variables,
            $createdBy,
            true
        );

        return [
            'added' => $result['added'],
            'duplicates' => $result['duplicates'],
            'invalid' => $recipients['invalid'],
            'rejected' => $cleaning['rejected'],
            'risky' => $cleaning['risky'],
            'cleaner_invalid' => $cleaning['invalid'],
            'unknown' => $cleaning['unknown'],
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $subscribers
     * @return array{added:int,duplicates:int,rejected:int,risky:int,cleaner_invalid:int,unknown:int}
     */
    public function queueSubscribers(
        string $templateId,
        string $templateTitle,
        array $subscribers,
        int $createdBy,
        bool $consentConfirmed
    ): array {
        if (!$consentConfirmed) {
            throw new \InvalidArgumentException('Confirm that these newsletter subscribers may receive this email.');
        }

        $recipients = [];
        foreach ($subscribers as $subscriber) {
            $email = strtolower(trim((string)($subscriber['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $recipients[] = ['email' => $email, 'name' => ''];
        }

        if ($recipients === []) {
            throw new \InvalidArgumentException('There are no active newsletter subscribers to clean and queue.');
        }

        $cleaning = $this->cleanBeforeQueue($recipients);
        if ($cleaning['clean'] === []) {
            throw new \InvalidArgumentException('No newsletter subscriber passed the live email cleaner as clean, so nothing was queued.');
        }

        $result = $this->repo->queueMany(
            trim($templateId),
            trim($templateTitle),
            $cleaning['clean'],
            [],
            $createdBy,
            true
        );

        return [
            'added' => $result['added'],
            'duplicates' => $result['duplicates'],
            'rejected' => $cleaning['rejected'],
            'risky' => $cleaning['risky'],
            'cleaner_invalid' => $cleaning['invalid'],
            'unknown' => $cleaning['unknown'],
        ];
    }

    /**
     * Process queued messages. Only cleaner status "clean" can reach Sender.
     *
     * @return array{examined:int,sent:int,blocked:int,retried:int,failed:int,remaining_today:int,daily_limit:int,sender_cooldown_until:?string}
     */
    public function process(int $requested = 50): array
    {
        if (!$this->sender->configured()) {
            throw new SenderApiException('Sender API token is not configured.');
        }

        if (!$this->repo->acquireWorkerLock()) {
            throw new \RuntimeException('Another Sender queue worker is already running.');
        }

        $summary = [
            'examined' => 0,
            'sent' => 0,
            'blocked' => 0,
            'retried' => 0,
            'failed' => 0,
            'remaining_today' => 0,
            'daily_limit' => $this->dailyLimit(),
            'sender_cooldown_until' => null,
        ];

        try {
            $window = $this->dailyWindow();
            $sentToday = $this->repo->countSentBetween($window['start_utc'], $window['end_utc'])
                + $this->campaigns->countDispatchedBetween($window['start_utc'], $window['end_utc']);
            $remaining = max(0, $this->dailyLimit() - $sentToday);
            $summary['remaining_today'] = $remaining;

            if ($remaining === 0) {
                return $summary;
            }

            try {
                $this->sender->assertApiAvailable();
            } catch (SenderApiException $e) {
                if ($e->statusCode === 429) {
                    $summary['sender_cooldown_until'] = $this->senderCooldownUntil();
                    return $summary;
                }
                throw $e;
            }

            $requested = max(1, min(100, $requested));
            $rows = $this->repo->ready(min($requested, $remaining));

            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $email = strtolower(trim((string)$row['recipient_email']));
                $name = trim((string)($row['recipient_name'] ?? ''));
                $attempt = (int)($row['attempts'] ?? 0) + 1;

                $this->repo->markProcessing($id);
                $summary['examined']++;

                $validation = $this->validator->validate($email);
                $validationStatus = strtolower(trim((string)($validation['status'] ?? 'unknown')));
                $validationReason = trim((string)($validation['reason'] ?? ''));
                $this->repo->markValidation($id, $validationStatus, $validationReason);

                if ($validationStatus !== 'clean') {
                    $message = 'List cleaner returned ' . ($validationStatus ?: 'unknown');
                    if ($validationReason !== '') {
                        $message .= ': ' . $validationReason;
                    }

                    if ($validationStatus === 'unknown' && $attempt < 3) {
                        $this->repo->markRetry($id, $message, 1800);
                        $summary['retried']++;
                    } elseif ($validationStatus === 'unknown') {
                        $this->repo->markFailed($id, $message);
                        $summary['failed']++;
                    } else {
                        $this->repo->markBlocked($id, $message);
                        $summary['blocked']++;
                    }
                    continue;
                }

                $variables = [];
                $rawVariables = trim((string)($row['variables_json'] ?? ''));
                if ($rawVariables !== '') {
                    $decoded = json_decode($rawVariables, true);
                    if (is_array($decoded)) {
                        $variables = $decoded;
                    }
                }

                $firstname = $name !== '' ? preg_split('/\s+/', $name)[0] ?? $name : '';
                $variables['email'] = $email;
                $variables['name'] = $name;
                $variables['firstname'] = $firstname;

                try {
                    $response = $this->sender->sendTemplate(
                        (string)$row['template_id'],
                        $email,
                        $name,
                        $variables
                    );

                    if (array_key_exists('success', $response) && $response['success'] === false) {
                        throw new SenderApiException(
                            trim((string)($response['message'] ?? 'Sender rejected the message.'))
                        );
                    }

                    $providerMessageId = isset($response['emailId'])
                        ? (string)$response['emailId']
                        : (isset($response['data']['emailId']) ? (string)$response['data']['emailId'] : null);

                    $this->repo->markSent($id, $providerMessageId);
                    $summary['sent']++;
                    $summary['remaining_today'] = max(0, $summary['remaining_today'] - 1);

                    if ($summary['remaining_today'] === 0) {
                        break;
                    }
                } catch (SenderApiException $e) {
                    if ($e->statusCode === 429) {
                        $delay = $e->retryAfter ?? 900;
                        $this->repo->markRetry($id, 'Sender rate limit: ' . $e->getMessage(), $delay);
                        $summary['retried']++;
                        $summary['sender_cooldown_until'] = $this->senderCooldownUntil();
                        break;
                    }

                    if ($e->retryable() && $attempt < 3) {
                        $delay = $e->retryAfter ?? min(3600, 300 * (2 ** max(0, $attempt - 1)));
                        $this->repo->markRetry($id, $e->getMessage(), $delay);
                        $summary['retried']++;
                    } else {
                        $this->repo->markFailed($id, $e->getMessage());
                        $summary['failed']++;

                        if (in_array($e->statusCode, [401, 403], true)) {
                            break;
                        }
                    }
                } catch (\Throwable $e) {
                    if ($attempt < 3) {
                        $this->repo->markRetry($id, $e->getMessage(), 900);
                        $summary['retried']++;
                    } else {
                        $this->repo->markFailed($id, $e->getMessage());
                        $summary['failed']++;
                    }
                }
            }

            return $summary;
        } finally {
            $this->repo->releaseWorkerLock();
        }
    }

    private function senderCooldownUntil(): ?string
    {
        $status = $this->sender->apiStatus();
        $until = trim((string)($status['cooldown_until'] ?? ''));
        return $until !== '' ? $until : null;
    }

    /**
     * @param array<int,array<string,mixed>> $recipients
     * @return array{clean:array<int,array<string,mixed>>,rejected:int,risky:int,invalid:int,unknown:int}
     */
    private function cleanBeforeQueue(array $recipients): array
    {
        $emails = [];
        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string)($recipient['email'] ?? '')));
            if ($email !== '') $emails[$email] = $email;
        }

        $results = $this->validator->validateMany(array_values($emails), 10);
        $clean = [];
        $counts = ['rejected' => 0, 'risky' => 0, 'invalid' => 0, 'unknown' => 0];

        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string)($recipient['email'] ?? '')));
            $validation = $results[$email] ?? ['status' => 'unknown'];
            $status = strtolower(trim((string)($validation['status'] ?? 'unknown')));

            if ($status === 'clean') {
                $clean[] = $recipient;
                continue;
            }

            $counts['rejected']++;
            if ($status === 'risky') $counts['risky']++;
            elseif ($status === 'invalid') $counts['invalid']++;
            else $counts['unknown']++;
        }

        return [
            'clean' => $clean,
            'rejected' => $counts['rejected'],
            'risky' => $counts['risky'],
            'invalid' => $counts['invalid'],
            'unknown' => $counts['unknown'],
        ];
    }

    /**
     * @return array{valid:array<int,array{email:string,name:string}>,invalid:int}
     */
    private function parseRecipients(string $text): array
    {
        $lines = preg_split('/\R+/', trim($text)) ?: [];
        if (count($lines) > 1000) {
            throw new \InvalidArgumentException('Queue at most 1,000 recipient lines at a time.');
        }

        $valid = [];
        $invalid = 0;
        $seen = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $email = '';
            $name = '';

            if (preg_match('/^(.+?)\s*<([^>]+)>$/', $line, $matches)) {
                $name = trim($matches[1], " \t\n\r\0\x0B\"'");
                $email = trim($matches[2]);
            } else {
                $parts = array_map('trim', str_getcsv($line));
                if (count($parts) >= 2) {
                    if (filter_var($parts[0], FILTER_VALIDATE_EMAIL)) {
                        $email = $parts[0];
                        $name = $parts[1];
                    } elseif (filter_var($parts[1], FILTER_VALIDATE_EMAIL)) {
                        $name = $parts[0];
                        $email = $parts[1];
                    }
                }

                if ($email === '') {
                    $email = $line;
                }
            }

            $email = strtolower(trim($email));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                $invalid++;
                continue;
            }

            if (isset($seen[$email])) {
                continue;
            }
            $seen[$email] = true;

            $valid[] = [
                'email' => $email,
                'name' => substr(trim($name), 0, 190),
            ];
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    /** @return array<string,mixed> */
    private function parseVariables(string $json): array
    {
        $json = trim($json);
        if ($json === '') {
            return [];
        }

        if (strlen($json) > 20000) {
            throw new \InvalidArgumentException('Template variables JSON is too large.');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \InvalidArgumentException('Template variables must be a JSON object.');
        }

        foreach ($decoded as $key => $value) {
            if (!is_string($key) || !preg_match('/^[A-Za-z0-9_.-]{1,80}$/', $key)) {
                throw new \InvalidArgumentException('Template variable names may contain letters, numbers, dot, dash and underscore.');
            }
            if (!is_scalar($value) && $value !== null) {
                throw new \InvalidArgumentException('Template variable values must be simple values.');
            }
        }

        return $decoded;
    }
}
