<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Services\EmailValidationClient;
use PDO;

final class NewsletterRepository
{
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
        return Database::connection()->query("SELECT COUNT(*) total,SUM(status='active') active,SUM(status='unsubscribed') unsubscribed,SUM(validation_status='risky') risky,SUM(validation_status='invalid') invalid_count,SUM(validation_status='unknown') unknown_count,SUM(validation_status IS NULL) not_checked FROM newsletter_subscribers")->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'active'=>0,'unsubscribed'=>0,'risky'=>0,'invalid_count'=>0,'unknown_count'=>0,'not_checked'=>0];
    }

    public function revalidate(int $id): void
    {
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
        if(!in_array($status,['active','unsubscribed'],true)) throw new \InvalidArgumentException('Invalid subscriber status.');
        $sql=$status==='active'?"UPDATE newsletter_subscribers SET status='active',subscribed_at=NOW(),unsubscribed_at=NULL WHERE id=:id":"UPDATE newsletter_subscribers SET status='unsubscribed',unsubscribed_at=NOW() WHERE id=:id";
        $stmt=Database::connection()->prepare($sql);$stmt->execute(['id'=>$id]);
    }

    public function delete(int $id): void
    {
        $stmt=Database::connection()->prepare('DELETE FROM newsletter_subscribers WHERE id=:id');$stmt->execute(['id'=>$id]);
    }
}
