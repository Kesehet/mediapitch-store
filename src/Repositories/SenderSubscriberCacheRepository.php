<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class SenderSubscriberCacheRepository
{
    private static bool $schemaReady = false;

    private function ensureSchema(): void
    {
        if (self::$schemaReady) return;

        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS sender_subscriber_cache (
            email VARCHAR(190) NOT NULL PRIMARY KEY,
            provider_subscriber_id VARCHAR(100) NULL,
            firstname VARCHAR(100) NULL,
            lastname VARCHAR(100) NULL,
            status VARCHAR(40) NULL,
            groups_json LONGTEXT NULL,
            provider_created_at VARCHAR(64) NULL,
            synced_at DATETIME NOT NULL,
            KEY idx_sender_subscriber_cache_status (status),
            KEY idx_sender_subscriber_cache_synced (synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sender_subscriber_cache_meta (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            last_synced_at DATETIME NULL,
            refresh_after DATETIME NULL,
            reported_total INT UNSIGNED NOT NULL DEFAULT 0,
            cached_rows INT UNSIGNED NOT NULL DEFAULT 0,
            pages INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(500) NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("INSERT IGNORE INTO sender_subscriber_cache_meta(id) VALUES(1)");
        self::$schemaReady = true;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $this->ensureSchema();
        $rows = Database::connection()->query(
            "SELECT * FROM sender_subscriber_cache ORDER BY email ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $groups = [];
            if (!empty($row['groups_json'])) {
                $decoded = json_decode((string)$row['groups_json'], true);
                if (is_array($decoded)) $groups = $decoded;
            }
            $row['groups'] = $groups;
        }
        unset($row);

        return $rows;
    }

    /** @return array<string,mixed> */
    public function meta(): array
    {
        $this->ensureSchema();
        $stmt = Database::connection()->query(
            "SELECT * FROM sender_subscriber_cache_meta WHERE id=1 LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [
            'last_synced_at' => null,
            'refresh_after' => null,
            'reported_total' => 0,
            'cached_rows' => 0,
            'pages' => 0,
            'last_error' => null,
        ];
    }

    public function hasSnapshot(): bool
    {
        $meta = $this->meta();
        return !empty($meta['last_synced_at']);
    }

    public function snapshotAgeSeconds(): ?int
    {
        $meta = $this->meta();
        $last = trim((string)($meta['last_synced_at'] ?? ''));
        if ($last === '') return null;

        $ts = strtotime($last . ' UTC');
        if ($ts === false) return null;
        return max(0, time() - $ts);
    }

    public function refreshAllowed(): bool
    {
        $meta = $this->meta();
        $after = trim((string)($meta['refresh_after'] ?? ''));
        if ($after === '') return true;

        $ts = strtotime($after . ' UTC');
        return $ts === false || time() >= $ts;
    }

    /**
     * Replace the cache only after a complete successful Sender fetch.
     *
     * @param array<int,array<string,mixed>> $rows
     */
    public function replace(array $rows, int $reportedTotal, int $pages): void
    {
        $this->ensureSchema();
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $pdo->exec('DELETE FROM sender_subscriber_cache');
            $insert = $pdo->prepare(
                "INSERT INTO sender_subscriber_cache
                 (email,provider_subscriber_id,firstname,lastname,status,groups_json,provider_created_at,synced_at)
                 VALUES
                 (:email,:provider_subscriber_id,:firstname,:lastname,:status,:groups_json,:provider_created_at,UTC_TIMESTAMP())"
            );

            $count = 0;
            foreach ($rows as $row) {
                $email = strtolower(trim((string)($row['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;

                $groups = $row['subscriber_tags'] ?? $row['groups'] ?? [];
                if (!is_array($groups)) $groups = [];

                $insert->execute([
                    'email' => $email,
                    'provider_subscriber_id' => substr(trim((string)($row['id'] ?? '')), 0, 100) ?: null,
                    'firstname' => substr(trim((string)($row['firstname'] ?? $row['first_name'] ?? '')), 0, 100) ?: null,
                    'lastname' => substr(trim((string)($row['lastname'] ?? $row['last_name'] ?? '')), 0, 100) ?: null,
                    'status' => substr(strtolower(trim((string)($row['status'] ?? ''))), 0, 40) ?: null,
                    'groups_json' => $groups !== [] ? json_encode($groups, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'provider_created_at' => substr(trim((string)($row['created'] ?? $row['created_at'] ?? '')), 0, 64) ?: null,
                ]);
                $count++;
            }

            $update = $pdo->prepare(
                "UPDATE sender_subscriber_cache_meta
                 SET last_synced_at=UTC_TIMESTAMP(),
                     refresh_after=NULL,
                     reported_total=:reported_total,
                     cached_rows=:cached_rows,
                     pages=:pages,
                     last_error=NULL
                 WHERE id=1"
            );
            $update->execute([
                'reported_total' => max(0, $reportedTotal),
                'cached_rows' => $count,
                'pages' => max(0, $pages),
            ]);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function recordFailure(string $message, int $cooldownSeconds = 300): void
    {
        $this->ensureSchema();
        $cooldownSeconds = max(60, min(172800, $cooldownSeconds));
        $refreshAfter = gmdate('Y-m-d H:i:s', time() + $cooldownSeconds);

        $stmt = Database::connection()->prepare(
            "UPDATE sender_subscriber_cache_meta
             SET refresh_after=:refresh_after,last_error=:last_error
             WHERE id=1"
        );
        $stmt->execute([
            'refresh_after' => $refreshAfter,
            'last_error' => substr(trim($message), 0, 500),
        ]);
    }

    public function clearFailure(): void
    {
        $this->ensureSchema();
        Database::connection()->exec(
            "UPDATE sender_subscriber_cache_meta SET refresh_after=NULL,last_error=NULL WHERE id=1"
        );
    }
}
