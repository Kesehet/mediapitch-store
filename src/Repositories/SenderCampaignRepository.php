<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class SenderCampaignRepository
{
    private static bool $schemaReady = false;

    private function ensureSchema(): void
    {
        if (self::$schemaReady) return;

        $pdo = Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS sender_campaign_runs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            source_campaign_id VARCHAR(100) NOT NULL,
            source_title VARCHAR(255) NULL,
            subject VARCHAR(255) NOT NULL,
            preheader VARCHAR(255) NULL,
            from_name VARCHAR(190) NOT NULL,
            reply_to VARCHAR(190) NOT NULL,
            content_type VARCHAR(20) NOT NULL DEFAULT 'html',
            content LONGTEXT NOT NULL,
            status ENUM('queued','active','paused','completed','cancelled','failed') NOT NULL DEFAULT 'queued',
            auto_continue TINYINT(1) NOT NULL DEFAULT 1,
            total_recipients INT UNSIGNED NOT NULL DEFAULT 0,
            dispatched_recipients INT UNSIGNED NOT NULL DEFAULT 0,
            blocked_recipients INT UNSIGNED NOT NULL DEFAULT 0,
            failed_recipients INT UNSIGNED NOT NULL DEFAULT 0,
            last_error VARCHAR(500) NULL,
            created_by BIGINT UNSIGNED NULL,
            completed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_sender_campaign_runs_status (status, auto_continue, id),
            KEY idx_sender_campaign_runs_source (source_campaign_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sender_campaign_recipients (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            run_id BIGINT UNSIGNED NOT NULL,
            newsletter_subscriber_id BIGINT UNSIGNED NULL,
            recipient_email VARCHAR(190) NOT NULL,
            recipient_name VARCHAR(190) NULL,
            status ENUM('queued','processing','dispatched','blocked','failed') NOT NULL DEFAULT 'queued',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            next_attempt_at DATETIME NULL,
            validation_status VARCHAR(20) NULL,
            validation_reason VARCHAR(255) NULL,
            validation_checked_at DATETIME NULL,
            batch_id BIGINT UNSIGNED NULL,
            last_error VARCHAR(500) NULL,
            dispatched_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sender_campaign_recipient (run_id, recipient_email),
            KEY idx_sender_campaign_recipient_ready (run_id, status, next_attempt_at, id),
            KEY idx_sender_campaign_recipient_batch (batch_id),
            KEY idx_sender_campaign_recipient_dispatched (dispatched_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS sender_campaign_batches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            run_id BIGINT UNSIGNED NOT NULL,
            batch_no INT UNSIGNED NOT NULL,
            group_title VARCHAR(255) NOT NULL,
            provider_group_id VARCHAR(100) NULL,
            provider_campaign_id VARCHAR(100) NULL,
            recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
            status ENUM('preparing','sent','failed') NOT NULL DEFAULT 'preparing',
            last_error VARCHAR(500) NULL,
            sent_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_sender_campaign_batch_no (run_id, batch_no),
            KEY idx_sender_campaign_batch_sent (sent_at),
            KEY idx_sender_campaign_batch_status (status, run_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        self::$schemaReady = true;
    }

    /** @param array<string,mixed> $snapshot @param array<int,array<string,mixed>> $recipients */
    public function createRun(array $snapshot, array $recipients, int $createdBy, bool $autoContinue): array
    {
        $this->ensureSchema();
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO sender_campaign_runs
                (source_campaign_id,source_title,subject,preheader,from_name,reply_to,content_type,content,status,auto_continue,total_recipients,created_by)
                VALUES
                (:source_campaign_id,:source_title,:subject,:preheader,:from_name,:reply_to,:content_type,:content,'queued',:auto_continue,:total_recipients,:created_by)"
            );
            $stmt->execute([
                'source_campaign_id' => substr((string)$snapshot['source_campaign_id'], 0, 100),
                'source_title' => substr((string)($snapshot['source_title'] ?? ''), 0, 255) ?: null,
                'subject' => substr((string)$snapshot['subject'], 0, 255),
                'preheader' => substr((string)($snapshot['preheader'] ?? ''), 0, 255) ?: null,
                'from_name' => substr((string)$snapshot['from_name'], 0, 190),
                'reply_to' => substr((string)$snapshot['reply_to'], 0, 190),
                'content_type' => in_array((string)$snapshot['content_type'], ['html','text'], true) ? (string)$snapshot['content_type'] : 'html',
                'content' => (string)$snapshot['content'],
                'auto_continue' => $autoContinue ? 1 : 0,
                'total_recipients' => count($recipients),
                'created_by' => $createdBy > 0 ? $createdBy : null,
            ]);

            $runId = (int)$pdo->lastInsertId();
            $insert = $pdo->prepare(
                "INSERT IGNORE INTO sender_campaign_recipients
                (run_id,newsletter_subscriber_id,recipient_email,recipient_name,status)
                VALUES (:run_id,:subscriber_id,:email,:name,'queued')"
            );

            foreach ($recipients as $recipient) {
                $email = strtolower(trim((string)($recipient['email'] ?? '')));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) continue;
                $insert->execute([
                    'run_id' => $runId,
                    'subscriber_id' => !empty($recipient['id']) ? (int)$recipient['id'] : null,
                    'email' => $email,
                    'name' => substr(trim((string)($recipient['name'] ?? '')), 0, 190) ?: null,
                ]);
            }

            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM sender_campaign_recipients WHERE run_id=:run_id');
            $countStmt->execute(['run_id' => $runId]);
            $actual = (int)$countStmt->fetchColumn();
            $pdo->prepare('UPDATE sender_campaign_runs SET total_recipients=:total WHERE id=:id')->execute(['total' => $actual, 'id' => $runId]);
            $pdo->commit();

            return $this->run($runId) ?? [];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<string,mixed>|null */
    public function run(int $id): ?array
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare('SELECT * FROM sender_campaign_runs WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string,mixed>|null */
    public function latestSourceSnapshot(string $sourceCampaignId): ?array
    {
        $this->ensureSchema();
        $sourceCampaignId=trim($sourceCampaignId);
        if($sourceCampaignId==='')return null;

        $stmt=Database::connection()->prepare(
            "SELECT source_campaign_id,source_title,subject,preheader,from_name,reply_to,content_type,content
             FROM sender_campaign_runs
             WHERE source_campaign_id=:source_campaign_id
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute(['source_campaign_id'=>$sourceCampaignId]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    /** @return array<int,array<string,mixed>> */
    public function runs(int $limit = 50): array
    {
        $this->ensureSchema();
        $limit = max(1, min(200, $limit));
        $sql = "SELECT r.*,
                    (r.total_recipients-r.dispatched_recipients-r.blocked_recipients-r.failed_recipients) AS remaining_recipients,
                    (SELECT COUNT(*) FROM sender_campaign_batches b WHERE b.run_id=r.id) AS batch_count,
                    (SELECT COUNT(*) FROM sender_campaign_recipients cr
                     WHERE cr.run_id=r.id AND cr.status='queued'
                       AND (cr.next_attempt_at IS NULL OR cr.next_attempt_at<=UTC_TIMESTAMP())) AS ready_recipients,
                    (SELECT COUNT(*) FROM sender_campaign_recipients cw
                     WHERE cw.run_id=r.id AND cw.status='queued'
                       AND cw.next_attempt_at>UTC_TIMESTAMP()) AS waiting_recipients,
                    (SELECT COUNT(*) FROM sender_campaign_recipients cp
                     WHERE cp.run_id=r.id AND cp.status='processing') AS processing_recipients,
                    (SELECT MIN(cn.next_attempt_at) FROM sender_campaign_recipients cn
                     WHERE cn.run_id=r.id AND cn.status='queued'
                       AND cn.next_attempt_at>UTC_TIMESTAMP()) AS next_retry_at
                FROM sender_campaign_runs r
                ORDER BY r.id DESC
                LIMIT {$limit}";
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,array<string,mixed>> */
    public function activeRuns(): array
    {
        $this->ensureSchema();
        return Database::connection()->query(
            "SELECT * FROM sender_campaign_runs
             WHERE status IN ('queued','active') AND auto_continue=1
             ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recoverStaleProcessing(int $runId): int
    {
        $this->ensureSchema();
        // The caller holds the global Sender worker lock. Therefore any row still
        // marked processing belongs to a previous interrupted worker and is safe
        // to return to the ready queue.
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET status='queued',
                 next_attempt_at=NULL,
                 last_error=COALESCE(last_error,'Recovered after an interrupted worker run')
             WHERE run_id=:run_id
               AND status='processing'
               AND (
                    batch_id IS NULL OR NOT EXISTS (
                        SELECT 1 FROM sender_campaign_batches b
                        WHERE b.id=sender_campaign_recipients.batch_id
                          AND b.status='preparing'
                    )
               )"
        );
        $stmt->execute(['run_id' => $runId]);
        return $stmt->rowCount();
    }

    /** @return array{queued_ready:int,queued_waiting:int,processing:int,next_retry_at:?string} */
    public function recipientState(int $runId): array
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "SELECT
                SUM(status='queued' AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP())) AS queued_ready,
                SUM(status='queued' AND next_attempt_at>UTC_TIMESTAMP()) AS queued_waiting,
                SUM(status='processing') AS processing,
                MIN(CASE WHEN status='queued' AND next_attempt_at>UTC_TIMESTAMP() THEN next_attempt_at END) AS next_retry_at
             FROM sender_campaign_recipients
             WHERE run_id=:run_id"
        );
        $stmt->execute(['run_id' => $runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'queued_ready' => (int)($row['queued_ready'] ?? 0),
            'queued_waiting' => (int)($row['queued_waiting'] ?? 0),
            'processing' => (int)($row['processing'] ?? 0),
            'next_retry_at' => !empty($row['next_retry_at']) ? (string)$row['next_retry_at'] : null,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function readyRecipients(int $runId, int $limit): array
    {
        $this->ensureSchema();
        $limit = max(1, min(500, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT * FROM sender_campaign_recipients
             WHERE run_id=:run_id AND status='queued'
               AND (next_attempt_at IS NULL OR next_attempt_at<=UTC_TIMESTAMP())
             ORDER BY id ASC LIMIT {$limit}"
        );
        $stmt->execute(['run_id' => $runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markProcessing(int $id): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET status='processing',attempts=attempts+1,last_error=NULL
             WHERE id=:id AND status='queued'"
        );
        $stmt->execute(['id' => $id]);
    }

    public function markValidation(int $id, string $status, string $reason): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET validation_status=:status,validation_reason=:reason,validation_checked_at=UTC_TIMESTAMP()
             WHERE id=:id"
        );
        $stmt->execute([
            'status' => substr($status, 0, 20),
            'reason' => substr(trim($reason), 0, 255) ?: null,
            'id' => $id,
        ]);
    }

    public function markRetry(int $id, string $error, int $delaySeconds = 1800): void
    {
        $this->ensureSchema();
        $next = gmdate('Y-m-d H:i:s', time() + max(60, min(86400, $delaySeconds)));
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET status='queued',next_attempt_at=:next_attempt_at,last_error=:error
             WHERE id=:id"
        );
        $stmt->execute(['next_attempt_at' => $next, 'error' => substr($error, 0, 500), 'id' => $id]);
    }

    public function markBlocked(int $id, string $error): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET status='blocked',next_attempt_at=NULL,last_error=:error
             WHERE id=:id"
        );
        $stmt->execute(['error' => substr($error, 0, 500), 'id' => $id]);
    }

    /** @param array<int,int> $ids */
    public function markFailedMany(array $ids, string $error): void
    {
        $this->ensureSchema();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET status='failed',next_attempt_at=NULL,last_error=?
             WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([substr($error, 0, 500)], $ids));
    }

    /** @param array<int,int> $ids */
    public function returnToQueueMany(array $ids, string $error): void
    {
        $this->ensureSchema();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) return;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET status='queued',next_attempt_at=NULL,last_error=?
             WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([substr($error, 0, 500)], $ids));
    }

    /** @param array<int,int> $ids */
    public function requeueMany(array $ids, string $error, int $delaySeconds = 900): void
    {
        $this->ensureSchema();
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) return;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $next = gmdate('Y-m-d H:i:s', time() + max(60, min(86400, $delaySeconds)));
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET status='queued',next_attempt_at=?,last_error=?
             WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$next, substr($error, 0, 500)], $ids));
    }

    public function nextBatchNo(int $runId): int
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare('SELECT COALESCE(MAX(batch_no),0)+1 FROM sender_campaign_batches WHERE run_id=:run_id');
        $stmt->execute(['run_id' => $runId]);
        return max(1, (int)$stmt->fetchColumn());
    }

    public function createBatch(int $runId, int $batchNo, string $groupTitle, int $recipientCount): int
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "INSERT INTO sender_campaign_batches (run_id,batch_no,group_title,recipient_count,status)
             VALUES (:run_id,:batch_no,:group_title,:recipient_count,'preparing')"
        );
        $stmt->execute([
            'run_id' => $runId,
            'batch_no' => $batchNo,
            'group_title' => substr($groupTitle, 0, 255),
            'recipient_count' => max(0, $recipientCount),
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    /** @param array<int,int> $recipientIds */
    public function attachRecipientsToBatch(int $batchId, array $recipientIds): void
    {
        $this->ensureSchema();
        $ids=array_values(array_unique(array_filter(array_map('intval',$recipientIds))));
        if($ids===[])return;
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $stmt=Database::connection()->prepare(
            "UPDATE sender_campaign_recipients
             SET batch_id=?
             WHERE id IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$batchId],$ids));
    }

    /** @return array<int,array<string,mixed>> */
    public function uncertainBatches(int $runId): array
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare(
            "SELECT * FROM sender_campaign_batches
             WHERE run_id=:run_id AND status='preparing'
             ORDER BY id ASC"
        );
        $stmt->execute(['run_id'=>$runId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int,int> */
    public function batchRecipientIds(int $batchId): array
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare(
            "SELECT id FROM sender_campaign_recipients
             WHERE batch_id=:batch_id AND status='processing'
             ORDER BY id ASC"
        );
        $stmt->execute(['batch_id'=>$batchId]);
        return array_map('intval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function markAttachedBatchSent(int $batchId): int
    {
        $this->ensureSchema();
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare(
                "UPDATE sender_campaign_recipients
                 SET status='dispatched',dispatched_at=UTC_TIMESTAMP(),next_attempt_at=NULL,last_error=NULL
                 WHERE batch_id=:batch_id AND status='processing'"
            );
            $stmt->execute(['batch_id'=>$batchId]);
            $count=$stmt->rowCount();

            $pdo->prepare(
                "UPDATE sender_campaign_batches
                 SET status='sent',sent_at=UTC_TIMESTAMP(),last_error=NULL
                 WHERE id=:id"
            )->execute(['id'=>$batchId]);

            $pdo->commit();
            return $count;
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    public function requeueAttachedBatch(int $batchId,string $error,int $delaySeconds=60): int
    {
        $this->ensureSchema();
        $pdo=Database::connection();
        $pdo->beginTransaction();
        try{
            $next=gmdate('Y-m-d H:i:s',time()+max(60,min(86400,$delaySeconds)));
            $stmt=$pdo->prepare(
                "UPDATE sender_campaign_recipients
                 SET status='queued',next_attempt_at=:next,last_error=:error
                 WHERE batch_id=:batch_id AND status='processing'"
            );
            $stmt->execute([
                'next'=>$next,
                'error'=>substr($error,0,500),
                'batch_id'=>$batchId,
            ]);
            $count=$stmt->rowCount();

            $pdo->prepare(
                "UPDATE sender_campaign_batches
                 SET status='failed',last_error=:error
                 WHERE id=:id"
            )->execute(['error'=>substr($error,0,500),'id'=>$batchId]);

            $pdo->commit();
            return $count;
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }

    public function setBatchRecipientCount(int $batchId,int $recipientCount):void
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare(
            'UPDATE sender_campaign_batches SET recipient_count=:recipient_count WHERE id=:id'
        );
        $stmt->execute([
            'recipient_count'=>max(0,$recipientCount),
            'id'=>$batchId,
        ]);
    }

    public function setBatchGroup(int $batchId, string $groupId): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare('UPDATE sender_campaign_batches SET provider_group_id=:group_id WHERE id=:id');
        $stmt->execute(['group_id' => substr($groupId, 0, 100), 'id' => $batchId]);
    }

    public function setBatchCampaign(int $batchId, string $campaignId): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare('UPDATE sender_campaign_batches SET provider_campaign_id=:campaign_id WHERE id=:id');
        $stmt->execute(['campaign_id' => substr($campaignId, 0, 100), 'id' => $batchId]);
    }

    /** @param array<int,int> $recipientIds */
    public function markBatchSent(int $batchId, array $recipientIds): void
    {
        $this->ensureSchema();
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "UPDATE sender_campaign_batches SET status='sent',sent_at=UTC_TIMESTAMP(),last_error=NULL WHERE id=:id"
            )->execute(['id' => $batchId]);

            $ids = array_values(array_unique(array_filter(array_map('intval', $recipientIds))));
            if ($ids !== []) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = $pdo->prepare(
                    "UPDATE sender_campaign_recipients
                     SET status='dispatched',batch_id=?,dispatched_at=UTC_TIMESTAMP(),next_attempt_at=NULL,last_error=NULL
                     WHERE id IN ({$placeholders})"
                );
                $stmt->execute(array_merge([$batchId], $ids));
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public function markBatchIssue(int $batchId, string $error): void
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare(
            "UPDATE sender_campaign_batches
             SET last_error=:error
             WHERE id=:id"
        );
        $stmt->execute([
            'error'=>substr($error,0,500),
            'id'=>$batchId,
        ]);
    }

    public function markBatchFailed(int $batchId, string $error): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "UPDATE sender_campaign_batches SET status='failed',last_error=:error WHERE id=:id"
        );
        $stmt->execute(['error' => substr($error, 0, 500), 'id' => $batchId]);
    }

    public function refreshRunStats(int $runId): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "SELECT
                COUNT(*) total,
                SUM(status='dispatched') dispatched,
                SUM(status='blocked') blocked,
                SUM(status='failed') failed,
                SUM(status IN ('queued','processing')) pending
             FROM sender_campaign_recipients WHERE run_id=:run_id"
        );
        $stmt->execute(['run_id' => $runId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $pending = (int)($stats['pending'] ?? 0);
        $statusSql = $pending === 0
            ? ",status=IF(status IN ('cancelled','failed','paused'),status,'completed'),completed_at=IF(status IN ('cancelled','failed','paused'),completed_at,UTC_TIMESTAMP())"
            : ",status=IF(status='queued','active',status)";
        $update = Database::connection()->prepare(
            "UPDATE sender_campaign_runs SET
                total_recipients=:total,
                dispatched_recipients=:dispatched,
                blocked_recipients=:blocked,
                failed_recipients=:failed
                {$statusSql}
             WHERE id=:id"
        );
        $update->execute([
            'total' => (int)($stats['total'] ?? 0),
            'dispatched' => (int)($stats['dispatched'] ?? 0),
            'blocked' => (int)($stats['blocked'] ?? 0),
            'failed' => (int)($stats['failed'] ?? 0),
            'id' => $runId,
        ]);
    }

    public function setRunStatus(int $id, string $status, ?string $error = null): void
    {
        $this->ensureSchema();
        if (!in_array($status, ['queued','active','paused','completed','cancelled','failed'], true)) {
            throw new \InvalidArgumentException('Invalid campaign queue status.');
        }
        $stmt = Database::connection()->prepare(
            'UPDATE sender_campaign_runs SET status=:status,last_error=:error WHERE id=:id'
        );
        $stmt->execute(['status' => $status, 'error' => $error !== null ? substr($error, 0, 500) : null, 'id' => $id]);
    }

    public function setAutoContinue(int $id, bool $enabled): void
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare('UPDATE sender_campaign_runs SET auto_continue=:enabled WHERE id=:id');
        $stmt->execute(['enabled' => $enabled ? 1 : 0, 'id' => $id]);
    }

    public function countDispatchedBetween(string $startUtc, string $endUtc): int
    {
        $this->ensureSchema();
        $stmt = Database::connection()->prepare(
            "SELECT COALESCE(SUM(recipient_count),0) FROM sender_campaign_batches
             WHERE status='sent' AND sent_at>=:start_utc AND sent_at<:end_utc"
        );
        $stmt->execute(['start_utc' => $startUtc, 'end_utc' => $endUtc]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array<string,int> */
    public function stats(): array
    {
        $this->ensureSchema();
        $row = Database::connection()->query(
            "SELECT
                COUNT(*) runs,
                SUM(status IN ('queued','active')) active_runs,
                SUM(status='paused') paused_runs,
                SUM(status='completed') completed_runs,
                COALESCE(SUM(total_recipients),0) recipients,
                COALESCE(SUM(dispatched_recipients),0) dispatched,
                COALESCE(SUM(blocked_recipients),0) blocked,
                COALESCE(SUM(failed_recipients),0) failed
             FROM sender_campaign_runs"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        return array_map('intval', $row);
    }

    public function acquireWorkerLock(): bool
    {
        $this->ensureSchema();
        $stmt = Database::connection()->query("SELECT GET_LOCK('mediapitch_sender_worker',0)");
        return (int)$stmt->fetchColumn() === 1;
    }

    public function releaseWorkerLock(): void
    {
        try { Database::connection()->query("SELECT RELEASE_LOCK('mediapitch_sender_worker')"); } catch (\Throwable) {}
    }
}
