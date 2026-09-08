<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Services\EmailValidationClient;
use PDO;

final class NewsletterRepository
{
    private static bool $schemaReady=false;

    private function ensureSchema(): void
    {
        if(self::$schemaReady) return;

        $pdo=Database::connection();
        $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL,
            status ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
            source VARCHAR(100) NOT NULL DEFAULT 'popup',
            validation_status VARCHAR(20) NULL,
            validation_reason VARCHAR(255) NULL,
            validation_checked_at DATETIME NULL,
            subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            unsubscribed_at DATETIME NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_newsletter_subscribers_email (email),
            KEY idx_newsletter_subscribers_status_date (status, subscribed_at),
            KEY idx_newsletter_validation_status (validation_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $hasColumn=static function(string $column) use ($pdo): bool {
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND COLUMN_NAME=:column");
            $stmt->execute(['column'=>$column]);
            return (int)$stmt->fetchColumn()>0;
        };
        $hasIndex=static function(string $index) use ($pdo): bool {
            $stmt=$pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='newsletter_subscribers' AND INDEX_NAME=:index_name");
            $stmt->execute(['index_name'=>$index]);
            return (int)$stmt->fetchColumn()>0;
        };

        if(!$hasColumn('validation_status')) {
            $pdo->exec("ALTER TABLE newsletter_subscribers ADD COLUMN validation_status VARCHAR(20) NULL AFTER source");
        }
        if(!$hasColumn('validation_reason')) {
            $pdo->exec("ALTER TABLE newsletter_subscribers ADD COLUMN validation_reason VARCHAR(255) NULL AFTER validation_status");
        }
        if(!$hasColumn('validation_checked_at')) {
            $pdo->exec("ALTER TABLE newsletter_subscribers ADD COLUMN validation_checked_at DATETIME NULL AFTER validation_reason");
        }
        if(!$hasIndex('idx_newsletter_validation_status')) {
            $pdo->exec("ALTER TABLE newsletter_subscribers ADD KEY idx_newsletter_validation_status (validation_status)");
        }

        self::$schemaReady=true;
    }

    public function subscribe(string $email, string $source='popup'): array
    {
        $email=strtolower(trim($email));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) {
            throw new \InvalidArgumentException('Please enter a valid email address.');
        }

        // The central validator blocks definitively undeliverable domains (including Null MX)
        // and catches likely provider typos. Risky role/disposable addresses and UNKNOWN
        // results are still allowed so a temporary validator outage never loses a signup.
        $validation=(new EmailValidationClient())->validate($email);
        $validationStatus=(string)($validation['status']??'unknown');
        $validationReason=trim((string)($validation['reason']??''));
        $suggestion=trim((string)($validation['suggestion']??''));

        if($suggestion!=='') {
            $at=strrpos($email,'@');
            $local=$at===false?$email:substr($email,0,$at);
            throw new \InvalidArgumentException('Please check your email address. Did you mean '.$local.'@'.$suggestion.'?');
        }

        if($validationStatus==='invalid') {
            throw new \InvalidArgumentException('Please enter an email address that can receive mail.');
        }

        if(!in_array($validationStatus,['clean','risky','unknown'],true)) {
            $validationStatus='unknown';
        }
        $validationReason=substr($validationReason,0,255);

        $this->ensureSchema();
        $stmt=Database::connection()->prepare("INSERT INTO newsletter_subscribers(email,status,source,validation_status,validation_reason,validation_checked_at,subscribed_at,unsubscribed_at) VALUES(:email,'active',:source,:validation_status,:validation_reason,NOW(),NOW(),NULL) ON DUPLICATE KEY UPDATE status='active',source=VALUES(source),validation_status=VALUES(validation_status),validation_reason=VALUES(validation_reason),validation_checked_at=VALUES(validation_checked_at),subscribed_at=IF(status='unsubscribed',NOW(),subscribed_at),unsubscribed_at=NULL,updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([
            'email'=>$email,
            'source'=>substr(trim($source)?:'popup',0,100),
            'validation_status'=>$validationStatus,
            'validation_reason'=>$validationReason!==''?$validationReason:null,
        ]);
        return ['ok'=>true,'message'=>'You’re in. We’ll send you useful product picks and buying advice.'];
    }

    public function all(string $query='', string $status='all', string $validation='all'): array
    {
        $this->ensureSchema();
        $where=[];$params=[];
        if($query!==''){$where[]='email LIKE :query';$params['query']='%'.$query.'%';}
        if(in_array($status,['active','unsubscribed'],true)){$where[]='status=:status';$params['status']=$status;}
        if(in_array($validation,['clean','risky','unknown','invalid'],true)){$where[]='validation_status=:validation';$params['validation']=$validation;}
        elseif($validation==='not_checked'){$where[]='validation_status IS NULL';}
        $sql='SELECT * FROM newsletter_subscribers'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY subscribed_at DESC,id DESC LIMIT 1000';
        $stmt=Database::connection()->prepare($sql);$stmt->execute($params);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function stats(): array
    {
        $this->ensureSchema();
        return Database::connection()->query("SELECT COUNT(*) total,SUM(status='active') active,SUM(status='unsubscribed') unsubscribed,SUM(validation_status='risky') risky,SUM(validation_status='invalid') invalid_count,SUM(validation_status='unknown') unknown_count,SUM(validation_status IS NULL) not_checked FROM newsletter_subscribers")->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'active'=>0,'unsubscribed'=>0,'risky'=>0,'invalid_count'=>0,'unknown_count'=>0,'not_checked'=>0];
    }

    public function revalidate(int $id): void
    {
        $this->ensureSchema();
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT email FROM newsletter_subscribers WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$id]);
        $email=$stmt->fetchColumn();
        if(!is_string($email) || $email==='') return;

        $validation=(new EmailValidationClient())->validate($email);
        $validationStatus=(string)($validation['status']??'unknown');
        if(!in_array($validationStatus,['clean','risky','unknown','invalid'],true))$validationStatus='unknown';
        $validationReason=substr(trim((string)($validation['reason']??'')),0,255);
        $update=$pdo->prepare('UPDATE newsletter_subscribers SET validation_status=:validation_status,validation_reason=:validation_reason,validation_checked_at=NOW() WHERE id=:id');
        $update->execute([
            'validation_status'=>$validationStatus,
            'validation_reason'=>$validationReason!==''?$validationReason:null,
            'id'=>$id,
        ]);
    }

    public function setStatus(int $id,string $status): void
    {
        $this->ensureSchema();
        if(!in_array($status,['active','unsubscribed'],true)) throw new \InvalidArgumentException('Invalid subscriber status.');
        $sql=$status==='active'?"UPDATE newsletter_subscribers SET status='active',subscribed_at=NOW(),unsubscribed_at=NULL WHERE id=:id":"UPDATE newsletter_subscribers SET status='unsubscribed',unsubscribed_at=NOW() WHERE id=:id";
        $stmt=Database::connection()->prepare($sql);$stmt->execute(['id'=>$id]);
    }

    public function delete(int $id): void
    {
        $this->ensureSchema();
        $stmt=Database::connection()->prepare('DELETE FROM newsletter_subscribers WHERE id=:id');$stmt->execute(['id'=>$id]);
    }
}
