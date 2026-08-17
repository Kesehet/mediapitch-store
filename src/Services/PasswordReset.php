<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Core\Database;
use RuntimeException;
use PDO;

final class PasswordReset
{
    public function request(string $email): void
    {
        $email=strtolower(trim($email));
        if($email==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) return;

        $db=Database::connection();
        $stmt=$db->prepare('SELECT id,name,email FROM users WHERE email=:email AND active=1 LIMIT 1');
        $stmt->execute(['email'=>$email]);
        $user=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user) return;

        $db->prepare('DELETE FROM password_reset_tokens WHERE user_id=:user_id OR expires_at<UTC_TIMESTAMP()')->execute(['user_id'=>(int)$user['id']]);

        $token=bin2hex(random_bytes(32));
        $hash=hash('sha256',$token);
        $expires=gmdate('Y-m-d H:i:s',time()+max(600,(int)env('PASSWORD_RESET_TTL',3600)));
        $stmt=$db->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (:user_id,:token_hash,:expires_at)');
        $stmt->execute(['user_id'=>(int)$user['id'],'token_hash'=>$hash,'expires_at'=>$expires]);

        $link=url('admin/reset-password?token='.urlencode($token));
        $this->send((string)$user['email'],(string)$user['name'],$link);
    }

    public function reset(string $token,string $password,string $confirmation): void
    {
        $token=trim($token);
        if(strlen($token)<32) throw new RuntimeException('This reset link is invalid or expired.');
        if($password!==$confirmation) throw new RuntimeException('The new password and confirmation do not match.');
        if(strlen($password)<8) throw new RuntimeException('The new password must be at least 8 characters.');

        $db=Database::connection();
        $stmt=$db->prepare(
            'SELECT pr.id,pr.user_id FROM password_reset_tokens pr JOIN users u ON u.id=pr.user_id AND u.active=1
             WHERE pr.token_hash=:token_hash AND pr.used_at IS NULL AND pr.expires_at>=UTC_TIMESTAMP() LIMIT 1'
        );
        $stmt->execute(['token_hash'=>hash('sha256',$token)]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) throw new RuntimeException('This reset link is invalid or expired.');

        $db->beginTransaction();
        try{
            $db->prepare('UPDATE users SET password_hash=:password_hash,failed_login_count=0,last_failed_login_at=NULL WHERE id=:id')
                ->execute(['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'id'=>(int)$row['user_id']]);
            $db->prepare('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE id=:id')->execute(['id'=>(int)$row['id']]);
            $db->prepare('DELETE FROM password_reset_tokens WHERE user_id=:user_id AND id<>:id')->execute(['user_id'=>(int)$row['user_id'],'id'=>(int)$row['id']]);
            $db->commit();
        }catch(\Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }

    private function send(string $email,string $name,string $link): void
    {
        $transport=strtolower((string)env('MAIL_TRANSPORT','mail'));
        $subject='Reset your MediaPitch CMS password';
        $body="Hello {$name},\n\nA password reset was requested for your MediaPitch CMS account.\n\nReset your password:\n{$link}\n\nThis link expires in one hour. If you did not request this, you can ignore this email.\n";

        if($transport==='log'){
            error_log("MediaPitch password reset for {$email}: {$link}");
            return;
        }

        $from=(string)env('MAIL_FROM','admin@mediapitch.in');
        $headers=['From: MediaPitch <'.$from.'>','Content-Type: text/plain; charset=UTF-8'];
        if(!mail($email,$subject,$body,implode("\r\n",$headers))){
            throw new RuntimeException('The reset email could not be sent. Please contact an administrator.');
        }
    }
}
