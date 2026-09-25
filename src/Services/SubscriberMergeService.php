<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Repositories\NewsletterRepository;
use MediaPitch\Repositories\SenderSubscriberCacheRepository;

final class SubscriberMergeService
{
    private const CACHE_FRESH_SECONDS = 21600; // 6 hours
    private const CAMPAIGN_MAX_CACHE_AGE_SECONDS = 86400; // 24 hours

    public function __construct(
        private readonly NewsletterRepository $local = new NewsletterRepository(),
        private readonly SenderClient $sender = new SenderClient(),
        private readonly SenderSubscriberCacheRepository $cache = new SenderSubscriberCacheRepository()
    ) {
    }

    /**
     * @return array{
     *   rows:array<int,array<string,mixed>>,
     *   stats:array<string,int>,
     *   sender_error:?string,
     *   sender_warning:?string,
     *   sender_truncated:bool,
     *   sender_reported_total:int,
     *   sender_snapshot_source:string,
     *   sender_last_synced_at:?string,
     *   sender_cache_age_seconds:?int,
     *   sender_refresh_after:?string
     * }
     */
    public function merged(
        string $query = '',
        string $presence = 'all',
        string $status = 'all',
        string $validation = 'all',
        bool $forceSenderRefresh = false
    ): array {
        $localRows = $this->local->allForMerge(20000);
        $snapshot = $this->senderSnapshot($forceSenderRefresh);

        $senderRows = $snapshot['rows'];
        $senderError = $snapshot['error'];
        $senderWarning = $snapshot['warning'];
        $senderTruncated = $snapshot['truncated'];
        $senderReportedTotal = $snapshot['reported_total'];

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
            'sender_warning' => $senderWarning,
            'sender_truncated' => $senderTruncated,
            'sender_reported_total' => $senderReportedTotal,
            'sender_snapshot_source' => $snapshot['source'],
            'sender_last_synced_at' => $snapshot['last_synced_at'],
            'sender_cache_age_seconds' => $snapshot['age_seconds'],
            'sender_refresh_after' => $snapshot['refresh_after'],
        ];
    }

    /**
     * Build the deduplicated active audience before email validation.
     * A recent cached Sender snapshot is acceptable, but stale/missing snapshots fail closed.
     *
     * @return array<int,array<string,mixed>>
     */
    public function campaignCandidates(): array
    {
        $result = $this->merged();

        if (!empty($result['sender_error'])) {
            throw new \RuntimeException(
                'Cannot build the merged campaign audience: ' . (string)$result['sender_error']
            );
        }
        if (!empty($result['sender_truncated'])) {
            throw new \RuntimeException(
                'Cannot build the merged campaign audience because the Sender subscriber snapshot is incomplete.'
            );
        }

        $age = $result['sender_cache_age_seconds'];
        if ($age === null || $age > self::CAMPAIGN_MAX_CACHE_AGE_SECONDS) {
            throw new \RuntimeException(
                'Cannot queue a campaign until the Sender subscriber snapshot is refreshed. ' .
                'The last good snapshot is older than 24 hours.'
            );
        }

        $out = [];
        foreach ($result['rows'] as $row) {
            if ((string)($row['effective_status'] ?? '') !== 'active') continue;

            $email = $this->normalizeEmail((string)($row['email'] ?? ''));
            if ($email === '') continue;

            $out[] = [
                'email' => $email,
                'name' => trim((string)($row['display_name'] ?? '')),
                'id' => (int)($row['local_id'] ?? 0),
                'presence' => (string)($row['presence'] ?? ''),
                'sender_status' => (string)($row['sender_status'] ?? ''),
                'local_status' => (string)($row['local_status'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @return array{
     *   rows:array<int,array<string,mixed>>,
     *   error:?string,
     *   warning:?string,
     *   truncated:bool,
     *   reported_total:int,
     *   source:string,
     *   last_synced_at:?string,
     *   age_seconds:?int,
     *   refresh_after:?string
     * }
     */
    private function senderSnapshot(bool $forceRefresh): array
    {
        $meta = $this->cache->meta();
        $hasSnapshot = $this->cache->hasSnapshot();
        $age = $this->cache->snapshotAgeSeconds();
        $refreshAllowed = $this->cache->refreshAllowed();

        if (!$forceRefresh && $hasSnapshot && $age !== null && $age < self::CACHE_FRESH_SECONDS) {
            return $this->cachedSnapshot(null);
        }

        if (!$this->sender->configured()) {
            if ($hasSnapshot) {
                return $this->cachedSnapshot('Sender API token is not configured; using the last cached subscriber snapshot.');
            }
            return $this->emptySnapshot('Sender API token is not configured.');
        }

        if (!$refreshAllowed) {
            $after = trim((string)($meta['refresh_after'] ?? ''));
            $lastError = trim((string)($meta['last_error'] ?? 'Sender refresh is temporarily paused.'));
            if ($hasSnapshot) {
                return $this->cachedSnapshot(
                    $lastError . ($after !== '' ? ' Using cached subscribers until retry is allowed after ' . $after . ' UTC.' : '')
                );
            }
            return $this->emptySnapshot(
                $lastError . ($after !== '' ? ' Retry after ' . $after . ' UTC.' : '')
            );
        }

        try {
            $remote = $this->sender->allSubscribers(20000);
            if (!empty($remote['truncated'])) {
                $message = 'Sender returned an incomplete subscriber list; the previous cache was kept.';
                $this->cache->recordFailure($message, 900);
                if ($hasSnapshot) return $this->cachedSnapshot($message);

                return [
                    'rows' => [],
                    'error' => $message,
                    'warning' => null,
                    'truncated' => true,
                    'reported_total' => (int)($remote['reported_total'] ?? 0),
                    'source' => 'none',
                    'last_synced_at' => null,
                    'age_seconds' => null,
                    'refresh_after' => (string)($this->cache->meta()['refresh_after'] ?? ''),
                ];
            }

            $this->cache->replace(
                (array)$remote['rows'],
                (int)$remote['reported_total'],
                (int)$remote['pages']
            );
            return $this->cachedSnapshot(null, 'live');
        } catch (SenderApiException $e) {
            $cooldown = $this->cooldownSeconds($e);
            $this->cache->recordFailure($e->getMessage(), $cooldown);

            if ($hasSnapshot) {
                return $this->cachedSnapshot(
                    'Sender refresh failed: ' . $e->getMessage() . ' Using the last good subscriber snapshot.'
                );
            }

            return $this->emptySnapshot('Sender refresh failed: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->cache->recordFailure($e->getMessage(), 300);

            if ($hasSnapshot) {
                return $this->cachedSnapshot(
                    'Sender refresh failed: ' . $e->getMessage() . ' Using the last good subscriber snapshot.'
                );
            }

            return $this->emptySnapshot('Sender refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array{
     *   rows:array<int,array<string,mixed>>,
     *   error:?string,
     *   warning:?string,
     *   truncated:bool,
     *   reported_total:int,
     *   source:string,
     *   last_synced_at:?string,
     *   age_seconds:?int,
     *   refresh_after:?string
     * }
     */
    private function cachedSnapshot(?string $warning, string $source = 'cache'): array
    {
        $meta = $this->cache->meta();
        $rows = [];

        foreach ($this->cache->all() as $row) {
            $rows[] = [
                'id' => (string)($row['provider_subscriber_id'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'firstname' => (string)($row['firstname'] ?? ''),
                'lastname' => (string)($row['lastname'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'groups' => is_array($row['groups'] ?? null) ? $row['groups'] : [],
                'created_at' => (string)($row['provider_created_at'] ?? ''),
            ];
        }

        return [
            'rows' => $rows,
            'error' => null,
            'warning' => $warning,
            'truncated' => false,
            'reported_total' => (int)($meta['reported_total'] ?? count($rows)),
            'source' => $source,
            'last_synced_at' => !empty($meta['last_synced_at']) ? (string)$meta['last_synced_at'] : null,
            'age_seconds' => $this->cache->snapshotAgeSeconds(),
            'refresh_after' => !empty($meta['refresh_after']) ? (string)$meta['refresh_after'] : null,
        ];
    }

    /**
     * @return array{
     *   rows:array<int,array<string,mixed>>,
     *   error:?string,
     *   warning:?string,
     *   truncated:bool,
     *   reported_total:int,
     *   source:string,
     *   last_synced_at:?string,
     *   age_seconds:?int,
     *   refresh_after:?string
     * }
     */
    private function emptySnapshot(string $error): array
    {
        $meta = $this->cache->meta();
        return [
            'rows' => [],
            'error' => $error,
            'warning' => null,
            'truncated' => false,
            'reported_total' => 0,
            'source' => 'none',
            'last_synced_at' => null,
            'age_seconds' => null,
            'refresh_after' => !empty($meta['refresh_after']) ? (string)$meta['refresh_after'] : null,
        ];
    }

    private function cooldownSeconds(SenderApiException $e): int
    {
        if ($e->retryAfter !== null) {
            return max(60, min(172800, $e->retryAfter));
        }

        if (preg_match('/Retry after\s+([0-9T:\-]+Z)/i', $e->getMessage(), $matches)) {
            $ts = strtotime($matches[1]);
            if ($ts !== false && $ts > time()) {
                return max(60, min(172800, $ts - time()));
            }
        }

        return $e->statusCode === 429 ? 900 : 300;
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
