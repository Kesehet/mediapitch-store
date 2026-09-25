<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class SenderWorkerStateRepository
{
    private static bool $schemaReady=false;

    private function ensureSchema():void
    {
        if(self::$schemaReady)return;
        $pdo=Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS sender_worker_state (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            last_started_at DATETIME NULL,
            last_completed_at DATETIME NULL,
            last_success_at DATETIME NULL,
            last_error VARCHAR(500) NULL,
            last_transactional_sent INT UNSIGNED NOT NULL DEFAULT 0,
            last_campaign_dispatched INT UNSIGNED NOT NULL DEFAULT 0,
            last_remaining_today INT UNSIGNED NULL,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("INSERT IGNORE INTO sender_worker_state(id) VALUES(1)");
        self::$schemaReady=true;
    }

    /** @return array<string,mixed> */
    public function state():array
    {
        $this->ensureSchema();
        $row=Database::connection()->query("SELECT * FROM sender_worker_state WHERE id=1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return $row?:[];
    }

    public function started():void
    {
        $this->ensureSchema();
        Database::connection()->exec(
            "UPDATE sender_worker_state
             SET last_started_at=UTC_TIMESTAMP(),last_error=NULL
             WHERE id=1"
        );
    }

    /** @param array<string,mixed> $transactional @param array<string,mixed> $campaign */
    public function succeeded(array $transactional,array $campaign,int $remainingToday):void
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare(
            "UPDATE sender_worker_state
             SET last_completed_at=UTC_TIMESTAMP(),
                 last_success_at=UTC_TIMESTAMP(),
                 last_error=NULL,
                 last_transactional_sent=:transactional_sent,
                 last_campaign_dispatched=:campaign_dispatched,
                 last_remaining_today=:remaining_today
             WHERE id=1"
        );
        $stmt->execute([
            'transactional_sent'=>max(0,(int)($transactional['sent']??0)),
            'campaign_dispatched'=>max(0,(int)($campaign['dispatched']??0)),
            'remaining_today'=>max(0,$remainingToday),
        ]);
    }

    public function failed(string $error):void
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare(
            "UPDATE sender_worker_state
             SET last_completed_at=UTC_TIMESTAMP(),last_error=:last_error
             WHERE id=1"
        );
        $stmt->execute(['last_error'=>substr(trim($error),0,500)]);
    }
}
