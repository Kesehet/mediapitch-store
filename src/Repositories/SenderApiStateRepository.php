<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Services\SenderApiException;
use PDO;

final class SenderApiStateRepository
{
    private static bool $schemaReady = false;

    private function ensureSchema(): void
    {
        if (self::$schemaReady) return;

        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS sender_api_state (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            cooldown_until DATETIME NULL,
            rate_limit_limit INT UNSIGNED NULL,
            rate_limit_remaining INT UNSIGNED NULL,
            rate_limit_reset_at DATETIME NULL,
            last_status SMALLINT UNSIGNED NULL,
            last_error VARCHAR(500) NULL,
            last_request_at DATETIME NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO sender_api_state(id) VALUES(1)");
        self::$schemaReady = true;
    }

    /** @return array<string,mixed> */
    public function status(): array
    {
        $this->ensureSchema();
        $row = Database::connection()->query("SELECT * FROM sender_api_state WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$row) return [];

        $reset = trim((string)($row['rate_limit_reset_at'] ?? ''));
        if ($reset !== '') {
            $resetTs = strtotime($reset . ' UTC');
            if ($resetTs !== false && $resetTs <= time()) {
                Database::connection()->exec(
                    "UPDATE sender_api_state
                     SET rate_limit_remaining=NULL,rate_limit_reset_at=NULL,
                         cooldown_until=IF(cooldown_until<=UTC_TIMESTAMP(),NULL,cooldown_until)
                     WHERE id=1"
                );
                $row['rate_limit_remaining'] = null;
                $row['rate_limit_reset_at'] = null;
                $cooldown = trim((string)($row['cooldown_until'] ?? ''));
                if ($cooldown !== '') {
                    $cooldownTs = strtotime($cooldown . ' UTC');
                    if ($cooldownTs !== false && $cooldownTs <= time()) $row['cooldown_until'] = null;
                }
            }
        }

        return $row;
    }

    public function assertRequestAllowed(): void
    {
        $row = $this->status();
        $until = trim((string)($row['cooldown_until'] ?? ''));
        if ($until === '') return;

        $ts = strtotime($until . ' UTC');
        if ($ts === false || $ts <= time()) {
            $this->clearCooldown();
            return;
        }

        $retryAfter = max(1, $ts - time());
        throw new SenderApiException(
            'Sender API cooldown is active until ' . gmdate('Y-m-d\TH:i:s\Z', $ts) . '.',
            429,
            $retryAfter
        );
    }

    /** @param array<string,string> $headers */
    public function recordResponse(int $status, array $headers, ?string $message = null): void
    {
        $this->ensureSchema();

        $limit = $this->positiveInt($headers['x-ratelimit-limit'] ?? null);
        $remaining = $this->nonNegativeInt($headers['x-ratelimit-remaining'] ?? null);
        $resetTs = $this->parseReset($headers['x-ratelimit-reset'] ?? null);
        $retryTs = $this->parseRetryAfter($headers['retry-after'] ?? null);

        $messageRetryTs = $this->parseMessageRetry($message);
        $existing = $this->status();
        $existingCooldownTs = null;
        $existingCooldown = trim((string)($existing['cooldown_until'] ?? ''));
        if ($existingCooldown !== '') {
            $parsed = strtotime($existingCooldown . ' UTC');
            if ($parsed !== false && $parsed > time()) $existingCooldownTs = $parsed;
        }

        $cooldownTs = null;
        if ($status === 429) {
            $cooldownTs = $retryTs ?? $resetTs ?? $messageRetryTs ?? (time() + 60);
        } elseif ($remaining === 0 && $resetTs !== null && $resetTs > time()) {
            $cooldownTs = $resetTs;
        } elseif ($existingCooldownTs !== null) {
            // A concurrent request may finish successfully after another request has
            // already received a 429. Preserve the future cooldown rather than
            // clearing it with that late success.
            $cooldownTs = $existingCooldownTs;
        }

        $stmt = Database::connection()->prepare(
            "UPDATE sender_api_state
             SET cooldown_until=:cooldown_until,
                 rate_limit_limit=COALESCE(:rate_limit_limit,rate_limit_limit),
                 rate_limit_remaining=COALESCE(:rate_limit_remaining,rate_limit_remaining),
                 rate_limit_reset_at=COALESCE(:rate_limit_reset_at,rate_limit_reset_at),
                 last_status=:last_status,
                 last_error=:last_error,
                 last_request_at=UTC_TIMESTAMP()
             WHERE id=1"
        );
        $stmt->execute([
            'cooldown_until' => $cooldownTs !== null ? gmdate('Y-m-d H:i:s', $cooldownTs) : null,
            'rate_limit_limit' => $limit,
            'rate_limit_remaining' => $remaining,
            'rate_limit_reset_at' => $resetTs !== null ? gmdate('Y-m-d H:i:s', $resetTs) : null,
            'last_status' => max(0, $status),
            'last_error' => $message !== null && trim($message) !== '' ? substr(trim($message), 0, 500) : null,
        ]);
    }

    public function clearCooldown(): void
    {
        $this->ensureSchema();
        Database::connection()->exec(
            "UPDATE sender_api_state
             SET cooldown_until=NULL
             WHERE id=1"
        );
    }

    public function resetAll(): void
    {
        $this->ensureSchema();
        Database::connection()->exec(
            "UPDATE sender_api_state
             SET cooldown_until=NULL,
                 rate_limit_limit=NULL,
                 rate_limit_remaining=NULL,
                 rate_limit_reset_at=NULL,
                 last_status=NULL,
                 last_error=NULL,
                 last_request_at=NULL
             WHERE id=1"
        );
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) return null;
        $value = (int)$value;
        return $value > 0 ? $value : null;
    }

    private function nonNegativeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (!is_numeric($value)) return null;
        return max(0, (int)$value);
    }

    private function parseReset(mixed $value): ?int
    {
        if ($value === null) return null;
        $raw = trim((string)$value);
        if ($raw === '') return null;

        if (ctype_digit($raw)) {
            $number = (int)$raw;
            if ($number > 1000000000) return $number;
            if ($number > 0) return time() + $number;
        }

        $ts = strtotime($raw);
        return $ts !== false ? $ts : null;
    }

    private function parseMessageRetry(?string $message): ?int
    {
        $message = trim((string)$message);
        if ($message === '') return null;

        if (preg_match('/retry\s+after\s+([0-9T:\-+.]+Z?)/i', $message, $matches)) {
            $ts = strtotime($matches[1]);
            if ($ts !== false && $ts > time()) return $ts;
        }

        if (preg_match('/retry\s+after\s+(\d+)\s*(?:second|seconds|sec|secs)/i', $message, $matches)) {
            return time() + max(1, (int)$matches[1]);
        }

        return null;
    }

    private function parseRetryAfter(mixed $value): ?int
    {
        if ($value === null) return null;
        $raw = trim((string)$value);
        if ($raw === '') return null;

        if (ctype_digit($raw)) {
            return time() + max(1, (int)$raw);
        }

        $ts = strtotime($raw);
        return $ts !== false ? $ts : null;
    }
}
