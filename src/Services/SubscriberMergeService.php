<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Repositories\NewsletterRepository;

final class SubscriberMergeService
{
    public function __construct(
        private readonly NewsletterRepository $local = new NewsletterRepository(),
        private readonly SenderClient $sender = new SenderClient()
    ) {
    }

    /**
     * @return array{
     *   rows:array<int,array<string,mixed>>,
     *   stats:array<string,int>,
     *   sender_error:?string,
     *   sender_truncated:bool,
     *   sender_reported_total:int
     * }
     */
    public function merged(string $query = '', string $presence = 'all', string $status = 'all', string $validation = 'all'): array
    {
        $localRows = $this->local->allForMerge(20000);
        $senderRows = [];
        $senderError = null;
        $senderTruncated = false;
        $senderReportedTotal = 0;

        if ($this->sender->configured()) {
            try {
                $remote = $this->sender->allSubscribers(20000);
                $senderRows = $remote['rows'];
                $senderTruncated = (bool)$remote['truncated'];
                $senderReportedTotal = (int)$remote['reported_total'];
            } catch (\Throwable $e) {
                $senderError = $e->getMessage();
            }
        } else {
            $senderError = 'Sender API token is not configured.';
        }

        $merged = [];

        foreach ($localRows as $row) {
            $email = $this->normalizeEmail((string)($row['email'] ?? ''));
            if ($email === '') continue;

            $merged[$email] = [
                'email' => $email,
                'display_name' => '',
                'presence' => 'local',
                'in_local' => true,
                'in_sender' => false,
                'local_id' => (int)($row['id'] ?? 0),
                'local_status' => strtolower(trim((string)($row['status'] ?? ''))),
                'local_source' => (string)($row['source'] ?? ''),
                'validation_status' => (string)($row['validation_status'] ?? ''),
                'validation_reason' => (string)($row['validation_reason'] ?? ''),
                'validation_checked_at' => (string)($row['validation_checked_at'] ?? ''),
                'local_subscribed_at' => (string)($row['subscribed_at'] ?? ''),
                'local_unsubscribed_at' => (string)($row['unsubscribed_at'] ?? ''),
                'sender_id' => '',
                'sender_status' => '',
                'sender_created_at' => '',
                'sender_groups' => [],
                'effective_status' => $this->effectiveStatus((string)($row['status'] ?? ''), ''),
                'status_conflict' => false,
            ];
        }

        foreach ($senderRows as $row) {
            $email = $this->normalizeEmail((string)($row['email'] ?? ''));
            if ($email === '') continue;

            $firstname = trim((string)($row['firstname'] ?? $row['first_name'] ?? ''));
            $lastname = trim((string)($row['lastname'] ?? $row['last_name'] ?? ''));
            $name = trim($firstname . ' ' . $lastname);
            $senderStatus = strtolower(trim((string)($row['status'] ?? '')));
            $groups = $row['subscriber_tags'] ?? $row['groups'] ?? [];
            if (!is_array($groups)) $groups = [];

            if (!isset($merged[$email])) {
                $merged[$email] = [
                    'email' => $email,
                    'display_name' => $name,
                    'presence' => 'sender',
                    'in_local' => false,
                    'in_sender' => true,
                    'local_id' => 0,
                    'local_status' => '',
                    'local_source' => '',
                    'validation_status' => '',
                    'validation_reason' => '',
                    'validation_checked_at' => '',
                    'local_subscribed_at' => '',
                    'local_unsubscribed_at' => '',
                    'sender_id' => (string)($row['id'] ?? ''),
                    'sender_status' => $senderStatus,
                    'sender_created_at' => (string)($row['created'] ?? $row['created_at'] ?? ''),
                    'sender_groups' => $groups,
                    'effective_status' => $this->effectiveStatus('', $senderStatus),
                    'status_conflict' => false,
                ];
                continue;
            }

            $merged[$email]['in_sender'] = true;
            $merged[$email]['presence'] = 'both';
            $merged[$email]['display_name'] = $name;
            $merged[$email]['sender_id'] = (string)($row['id'] ?? '');
            $merged[$email]['sender_status'] = $senderStatus;
            $merged[$email]['sender_created_at'] = (string)($row['created'] ?? $row['created_at'] ?? '');
            $merged[$email]['sender_groups'] = $groups;
            $merged[$email]['effective_status'] = $this->effectiveStatus(
                (string)$merged[$email]['local_status'],
                $senderStatus
            );
            $merged[$email]['status_conflict'] = $this->statusConflict(
                (string)$merged[$email]['local_status'],
                $senderStatus
            );
        }

        $stats = [
            'unique_total' => count($merged),
            'local_total' => count($localRows),
            'sender_total' => $senderReportedTotal ?: count($senderRows),
            'both' => 0,
            'local_only' => 0,
            'sender_only' => 0,
            'effective_active' => 0,
            'effective_unsubscribed' => 0,
            'effective_bounced' => 0,
            'status_conflicts' => 0,
            'campaign_eligible' => 0,
        ];

        foreach ($merged as $row) {
            if ($row['presence'] === 'both') $stats['both']++;
            elseif ($row['presence'] === 'local') $stats['local_only']++;
            else $stats['sender_only']++;

            $effective = (string)$row['effective_status'];
            if ($effective === 'active') $stats['effective_active']++;
            elseif ($effective === 'unsubscribed') $stats['effective_unsubscribed']++;
            elseif ($effective === 'bounced') $stats['effective_bounced']++;

            if (!empty($row['status_conflict'])) $stats['status_conflicts']++;

            if (
                !empty($row['in_local'])
                && (string)$row['local_status'] === 'active'
                && (string)$row['validation_status'] === 'clean'
                && !in_array((string)$row['sender_status'], ['unsubscribed','bounced','spam','complaint','suppressed','inactive'], true)
            ) {
                $stats['campaign_eligible']++;
            }
        }

        $query = strtolower(trim($query));
        $filtered = array_values(array_filter($merged, function (array $row) use ($query, $presence, $status, $validation): bool {
            if ($query !== '') {
                $haystack = strtolower(trim(
                    (string)$row['email'] . ' ' .
                    (string)$row['display_name'] . ' ' .
                    (string)$row['local_source']
                ));
                if (!str_contains($haystack, $query)) return false;
            }

            if (in_array($presence, ['local','sender','both'], true) && $row['presence'] !== $presence) {
                return false;
            }

            if ($status !== 'all' && (string)$row['effective_status'] !== $status) {
                return false;
            }

            if ($validation !== 'all') {
                $actual = (string)$row['validation_status'];
                if ($validation === 'not_checked') {
                    if ($actual !== '') return false;
                } elseif ($actual !== $validation) {
                    return false;
                }
            }

            return true;
        }));

        usort($filtered, static function (array $a, array $b): int {
            $aDate = (string)($a['local_subscribed_at'] ?: $a['sender_created_at']);
            $bDate = (string)($b['local_subscribed_at'] ?: $b['sender_created_at']);
            return strcmp($bDate, $aDate);
        });

        return [
            'rows' => $filtered,
            'stats' => $stats,
            'sender_error' => $senderError,
            'sender_truncated' => $senderTruncated,
            'sender_reported_total' => $senderReportedTotal,
        ];
    }

    private function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    private function effectiveStatus(string $localStatus, string $senderStatus): string
    {
        $localStatus = strtolower(trim($localStatus));
        $senderStatus = strtolower(trim($senderStatus));

        if (in_array($senderStatus, ['bounced','bounce'], true)) return 'bounced';
        if (in_array($senderStatus, ['spam','complaint','suppressed','blocked'], true)) return 'suppressed';
        if (in_array($senderStatus, ['unsubscribed','unsubscribe','inactive'], true)) return 'unsubscribed';
        if ($localStatus === 'unsubscribed') return 'unsubscribed';
        if ($localStatus === 'active' || in_array($senderStatus, ['active','subscribed'], true)) return 'active';

        return $senderStatus !== '' ? $senderStatus : ($localStatus !== '' ? $localStatus : 'unknown');
    }

    private function statusConflict(string $localStatus, string $senderStatus): bool
    {
        $localStatus = strtolower(trim($localStatus));
        $senderStatus = strtolower(trim($senderStatus));
        if ($localStatus === '' || $senderStatus === '') return false;

        $senderSuppressed = in_array($senderStatus, ['unsubscribed','unsubscribe','inactive','bounced','bounce','spam','complaint','suppressed','blocked'], true);
        return ($localStatus === 'active' && $senderSuppressed)
            || ($localStatus === 'unsubscribed' && in_array($senderStatus, ['active','subscribed'], true));
    }
}
