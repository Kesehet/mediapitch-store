<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class SenderQueueRepository
{
    private static bool $schemaReady = false;

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        Database::connection()->exec(
            "CREATE TABLE IF NOT EXISTS sender_email_queue (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                template_id VARCHAR(100) NOT NULL,
                template_title VARCHAR(255) NULL,
                recipient_email VARCHAR(190) NOT NULL,
                recipient_name VARCHAR(190) NULL,
                variables_json LONGTEXT NULL,
                consent_confirmed TINYINT(1) NOT NULL DEFAULT 0,
                validation_status VARCHAR(20) NULL,
                validation_reason VARCHAR(255) NULL,
                validation_checked_at DATETIME NULL,
                status ENUM('queued','processing','sent','blocked','failed') NOT NULL DEFAULT 'queued',
                attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
                next_attempt_at DATETIME NULL,
                provider_message_id VARCHAR(191) NULL,
                last_error VARCHAR(500) NULL,
                sent_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_sender_queue_ready (status, next_attempt_at, id),
                KEY idx_sender_queue_sent (sent_at),
                KEY idx_sender_queue_recipient (recipient_email),
                KEY idx_sender_queue_template (template_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$schemaReady = true;
    }

    /**
     * @param array<int,array{email:string,name:string}> $recipients
     * @param array<string,mixed> $variables
     * @return array{added:int,duplicates:int}
     */
    public function queueMany(
        string $templateId,
        string $templateTitle,
        array $recipients,
        array $variables,
        int $createdBy,
        bool $consentConfirmed
    ): array {
        $this->ensureSchema();
        $pdo = Database::connection();
        $check = $pdo->prepare(
            "SELECT id FROM sender_email_queue
             WHERE template_id=:template_id AND recipient_email=:email
               AND status IN ('queued','processing','sent')
             LIMIT 1"
        );
        $insert = $pdo->prepare(
            "INSERT INTO sender_email_queue
             (template_id,template_title,recipient_email,recipient_name,variables_json,consent_confirmed,status,created_by)
             VALUES
             (:template_id,:template_title,:email,:name,:variables_json,:consent_confirmed,'queued',:created_by)"
        );

        $json = $variables !== []
            ? json_encode($variables, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;
        if ($json === false) {
            throw new \InvalidArgumentException('Template variables could not be encoded.');
        }

        $added = 0;
        $duplicates = 0;

        foreach ($recipients as $recipient) {
            $email = strtolower(trim((string)($recipient['email'] ?? '')));
            $name = trim((string)($recipient['name'] ?? ''));

            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                continue;
            }

            $check->execute(['template_id' => $templateId, 'email' => $email]);
            if ($check->fetchColumn()) {
                $duplicates++;
                continue;
            }

            $insert->execute([
                'template_id' => $templateId,
                'template_title' => substr($templateTitle, 0, 255) ?: null,
                'email' => $email,
                'name' => substr($name, 0, 190) ?: null,
                'variables_json' => $json,
                'consent_confirmed' => $consentConfirmed ? 1 : 0,
                'created_by' => $createdBy > 0 ? $createdBy : null,
            ]);
            $added++;
        }

        return ['added' => $added, 'duplicates' => $duplicates];
    }

    /** @return array<int,array<string,mixed>> */
    public function ready(int $limit): array
    {
        $this->ensureSchema();
        $limit = max(1, min(100, $limit));
        $stmt = Database::connection()->query(
            "SELECT * FROM sender_email_queue
             WHERE status='queued' AND consent_confirmed=1
               AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP())
             ORDER BY id ASC
             LIMIT " . $limit
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function recent(string $status = 'all', int $limit = 100): array
    {
        $this->ensureSchema();
        $limit = max(1, min(500, $limit));
        $params = [];
        $where = '';

        if (in_array($status, ['queued','processing','sent','blocked','failed'], true)) {
            $where = ' WHERE status=:status';
            $params['status'] = $status;
        }

        $stmt = Database::connection()->prepare(
            'SELECT * FROM sender_email_queue' . $where . ' ORDER BY id DESC LIMIT ' . $limit
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,int> */
    public function stats(string $startUtc, string $endUtc): array
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) total,
                SUM(status='queued') queued,
                SUM(status='processing') processing,
                SUM(status='sent') sent_total,
                SUM(status='blocked') blocked,
                SUM(status='failed') failed,
                SUM(status='sent' AND sent_at>=:start_utc AND sent_at<:end_utc) sent_today
             FROM sender_email_queue"
        );
        $stmt->execute(['start_utc' => $startUtc, 'end_utc' => $endUtc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'queued' => (int)($row['queued'] ?? 0),
            'processing' => (int)($row['processing'] ?? 0),
            'sent_total' => (int)($row['sent_total'] ?? 0),
            'blocked' => (int)($row['blocked'] ?? 0),
            'failed' => (int)($row['failed'] ?? 0),
            'sent_today' => (int)($row['sent_today'] ?? 0),
        ];
    }

    public function countSentBetween(string $startUtc, string $endUtc): int
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM sender_email_queue
             WHERE status='sent' AND sent_at>=:start_utc AND sent_at<:end_utc"
        );
        $stmt->execute(['start_utc' => $startUtc, 'end_utc' => $endUtc]);

        return (int)$stmt->fetchColumn();
    }

    public function markProcessing(int $id): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_email_queue
             SET status='processing',attempts=attempts+1,last_error=NULL
             WHERE id=:id AND status='queued'"
        );
        $stmt->execute(['id' => $id]);
    }

    public function markValidation(int $id, string $status, string $reason): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_email_queue
             SET validation_status=:validation_status,
                 validation_reason=:validation_reason,
                 validation_checked_at=UTC_TIMESTAMP()
             WHERE id=:id"
        );
        $stmt->execute([
            'validation_status' => substr($status, 0, 20),
            'validation_reason' => substr(trim($reason), 0, 255) ?: null,
            'id' => $id,
        ]);
    }

    public function markSent(int $id, ?string $providerMessageId): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_email_queue
             SET status='sent',provider_message_id=:provider_message_id,
                 sent_at=UTC_TIMESTAMP(),next_attempt_at=NULL,last_error=NULL
             WHERE id=:id"
        );
        $stmt->execute([
            'provider_message_id' => $providerMessageId !== null ? substr($providerMessageId, 0, 191) : null,
            'id' => $id,
        ]);
    }

    public function markBlocked(int $id, string $error): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_email_queue
             SET status='blocked',last_error=:last_error,next_attempt_at=NULL
             WHERE id=:id"
        );
        $stmt->execute(['last_error' => substr($error, 0, 500), 'id' => $id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_email_queue
             SET status='failed',last_error=:last_error,next_attempt_at=NULL
             WHERE id=:id"
        );
        $stmt->execute(['last_error' => substr($error, 0, 500), 'id' => $id]);
    }

    public function markRetry(int $id, string $error, int $delaySeconds): void
    {
        $this->ensureSchema();
        $delaySeconds = max(60, min(86400, $delaySeconds));
        $next = gmdate('Y-m-d H:i:s', time() + $delaySeconds);
        $stmt = Database::connection()->prepare(
            "UPDATE sender_email_queue
             SET status='queued',last_error=:last_error,next_attempt_at=:next_attempt_at
             WHERE id=:id"
        );
        $stmt->execute([
            'last_error' => substr($error, 0, 500),
            'next_attempt_at' => $next,
            'id' => $id,
        ]);
    }

    public function retry(int $id): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_email_queue
             SET status='queued',next_attempt_at=NULL,last_error=NULL,
                 validation_status=NULL,validation_reason=NULL,validation_checked_at=NULL
             WHERE id=:id AND status IN ('blocked','failed')"
        );
        $stmt->execute(['id' => $id]);
    }

    public function deleteUnsent(int $id): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "DELETE FROM sender_email_queue WHERE id=:id AND status<>'sent'"
        );
        $stmt->execute(['id' => $id]);
    }

    public function acquireWorkerLock(): bool
    {
        $this->ensureSchema();
        $stmt = Database::connection()->query("SELECT GET_LOCK('mediapitch_sender_worker',0)");
        return (int)$stmt->fetchColumn() === 1;
    }

    public function releaseWorkerLock(): void
    {
        try {
            Database::connection()->query("SELECT RELEASE_LOCK('mediapitch_sender_worker')");
        } catch (\Throwable) {
        }
    }
}
