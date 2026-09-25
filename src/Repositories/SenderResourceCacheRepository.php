<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class SenderResourceCacheRepository
{
    private static bool $schemaReady = false;

    private function ensureSchema(): void
    {
        if (self::$schemaReady) return;

        Database::connection()->exec("CREATE TABLE IF NOT EXISTS sender_resource_cache (
            cache_key VARCHAR(190) NOT NULL PRIMARY KEY,
            payload LONGTEXT NOT NULL,
            synced_at DATETIME NOT NULL,
            KEY idx_sender_resource_cache_synced (synced_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        self::$schemaReady = true;
    }

    /** @return array{payload:array<string,mixed>|array<int,mixed>,synced_at:string,age_seconds:int}|null */
    public function get(string $key): ?array
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            'SELECT payload,synced_at FROM sender_resource_cache WHERE cache_key=:cache_key LIMIT 1'
        );
        $stmt->execute(['cache_key' => substr($key, 0, 190)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        $decoded = json_decode((string)$row['payload'], true);
        if (!is_array($decoded)) return null;

        $ts = strtotime((string)$row['synced_at'] . ' UTC');
        return [
            'payload' => $decoded,
            'synced_at' => (string)$row['synced_at'],
            'age_seconds' => $ts !== false ? max(0, time() - $ts) : PHP_INT_MAX,
        ];
    }

    /** @param array<string,mixed>|array<int,mixed> $payload */
    public function put(string $key, array $payload): void
    {
        $this->ensureSchema();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) return;

        $stmt = Database::connection()->prepare(
            "INSERT INTO sender_resource_cache(cache_key,payload,synced_at)
             VALUES(:cache_key,:payload,UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE payload=VALUES(payload),synced_at=VALUES(synced_at)"
        );
        $stmt->execute([
            'cache_key' => substr($key, 0, 190),
            'payload' => $json,
        ]);
    }
}
