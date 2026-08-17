<?php

declare(strict_types=1);

namespace MediaPitch\Core;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class Auth
{
    private static ?array $userColumns = null;

    public static function attempt(string $email, string $password): bool
    {
        $email=strtolower(trim($email));
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = :email AND active = 1 LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Temporary bootstrap recovery: when both submitted values exactly match
        // the explicitly configured bootstrap credentials, repair/create that one
        // administrator account and continue this same login request. Blank the
        // env password after recovery to disable this path completely.
        if((!$user || !password_verify($password,(string)$user['password_hash'])) && self::matchesBootstrapCredentials($email,$password)){
            $user=self::recoverBootstrapAdministrator($email,$password);
            unset($_SESSION['_login_guard']);
        }

        // A browser/session lockout belongs to the password hash that existed
        // when the failed attempts happened. If an administrator/password-reset
        // workflow changes that hash, automatically discard the stale lockout so
        // the newly issued password can be used immediately without weakening
        // normal brute-force protection.
        if (self::loginBlocked()) {
            $guardFingerprint=(string)($_SESSION['_login_guard']['password_fingerprint'] ?? '');
            $currentFingerprint=$user ? self::passwordFingerprint((string)$user['password_hash']) : '';
            if($user && $guardFingerprint!=='' && !hash_equals($guardFingerprint,$currentFingerprint)){
                unset($_SESSION['_login_guard']);
            }else{
                return false;
            }
        }

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            if ($user && self::hasUserColumn('failed_login_count') && self::hasUserColumn('last_failed_login_at')) {
                $failed=Database::connection()->prepare(
                    'UPDATE users SET failed_login_count=failed_login_count+1,last_failed_login_at=UTC_TIMESTAMP() WHERE id=:id'
                );
                $failed->execute(['id'=>(int)$user['id']]);
            }
            self::recordFailedAttempt($user ? (string)$user['password_hash'] : null);
            return false;
        }

        if(self::hasUserColumn('last_login_at') && self::hasUserColumn('failed_login_count')){
            Database::connection()->prepare(
                'UPDATE users SET last_login_at=UTC_TIMESTAMP(),failed_login_count=0 WHERE id=:id'
            )->execute(['id'=>(int)$user['id']]);
        }

        unset($_SESSION['_login_guard']);
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
        $_SESSION['_auth_last_activity'] = time();
        return true;
    }

    public static function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmation): void
    {
        if ($newPassword !== $confirmation) {
            throw new InvalidArgumentException('The new password and confirmation do not match.');
        }
        if (strlen($newPassword) < 8) {
            throw new InvalidArgumentException('The new password must be at least 8 characters.');
        }
        if (hash_equals($currentPassword, $newPassword)) {
            throw new InvalidArgumentException('Choose a new password different from the current password.');
        }

        $stmt = Database::connection()->prepare('SELECT password_hash FROM users WHERE id=:id AND active=1 LIMIT 1');
        $stmt->execute(['id'=>$userId]);
        $hash = $stmt->fetchColumn();
        if (!$hash || !password_verify($currentPassword, (string)$hash)) {
            throw new RuntimeException('Current password is incorrect.');
        }

        $stmt = Database::connection()->prepare('UPDATE users SET password_hash=:password_hash WHERE id=:id');
        $stmt->execute(['password_hash'=>password_hash($newPassword, PASSWORD_DEFAULT), 'id'=>$userId]);
        session_regenerate_id(true);
        $_SESSION['_auth_last_activity'] = time();
    }

    public static function user(): ?array
    {
        if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
            return null;
        }

        $timeout = max(300, (int) env('SESSION_IDLE_TIMEOUT', 3600));
        $lastActivity = (int) ($_SESSION['_auth_last_activity'] ?? 0);
        if ($lastActivity > 0 && time() - $lastActivity > $timeout) {
            self::logout();
            return null;
        }
        $_SESSION['_auth_last_activity'] = time();
        return $_SESSION['user'];
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function role(): string
    {
        return (string) (self::user()['role'] ?? '');
    }

    public static function isAdministrator(): bool
    {
        return self::role() === 'administrator';
    }

    /** Catalog/product/spec/media administration. */
    public static function canManageProducts(): bool
    {
        return in_array(self::role(), ['administrator', 'editor'], true);
    }

    /** Create and edit editorial drafts. Writers are intentionally included. */
    public static function canEditContent(): bool
    {
        return in_array(self::role(), ['administrator', 'editor', 'writer'], true);
    }

    /** Upload/select media for catalog or editorial work. */
    public static function canUploadMedia(): bool
    {
        return self::canManageProducts() || self::canEditContent();
    }

    /** Publish or schedule editorial content. */
    public static function canPublish(): bool
    {
        return in_array(self::role(), ['administrator', 'editor'], true);
    }

    /** SEO-focused access for future dedicated SEO workflows. */
    public static function canManageSeo(): bool
    {
        return in_array(self::role(), ['administrator', 'editor', 'seo_manager'], true);
    }

    /** Users, credentials, integration settings, audit and sensitive analytics. */
    public static function canManageAdministration(): bool
    {
        return self::isAdministrator();
    }

    public static function loginBlocked(): bool
    {
        $guard = $_SESSION['_login_guard'] ?? null;
        return is_array($guard) && (int) ($guard['blocked_until'] ?? 0) > time();
    }

    public static function retryAfter(): int
    {
        $until = (int) (($_SESSION['_login_guard']['blocked_until'] ?? 0));
        return max(0, $until - time());
    }

    public static function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['_auth_last_activity']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private static function recordFailedAttempt(?string $passwordHash=null): void
    {
        $now = time();
        $window = max(60, (int) env('LOGIN_ATTEMPT_WINDOW', 600));
        $limit = max(3, (int) env('LOGIN_ATTEMPT_LIMIT', 5));
        $blockFor = max(60, (int) env('LOGIN_BLOCK_SECONDS', 900));
        $guard = $_SESSION['_login_guard'] ?? ['count' => 0, 'first_at' => $now, 'blocked_until' => 0];

        if (!is_array($guard) || $now - (int) ($guard['first_at'] ?? 0) > $window) {
            $guard = ['count' => 0, 'first_at' => $now, 'blocked_until' => 0];
        }
        $guard['count'] = (int) ($guard['count'] ?? 0) + 1;
        if($passwordHash!==null && $passwordHash!=='')$guard['password_fingerprint']=self::passwordFingerprint($passwordHash);
        if ($guard['count'] >= $limit) {
            $guard['blocked_until'] = $now + $blockFor;
            $guard['count'] = 0;
            $guard['first_at'] = $now;
        }
        $_SESSION['_login_guard'] = $guard;
    }

    private static function passwordFingerprint(string $passwordHash): string
    {
        return hash('sha256',$passwordHash);
    }

    private static function matchesBootstrapCredentials(string $email,string $password): bool
    {
        $configuredEmail=strtolower(trim((string)env('BOOTSTRAP_ADMIN_EMAIL','admin@mediapitch.in')));
        $configuredPassword=(string)env('BOOTSTRAP_ADMIN_PASSWORD','');
        return $configuredPassword!=='' && strlen($configuredPassword)>=8 && hash_equals($configuredEmail,$email) && hash_equals($configuredPassword,$password);
    }

    /** @return array<string,mixed> */
    private static function recoverBootstrapAdministrator(string $email,string $password): array
    {
        $db=Database::connection();
        $name=trim((string)env('BOOTSTRAP_ADMIN_NAME','MediaPitch Admin')) ?: 'MediaPitch Admin';
        $hash=password_hash($password,PASSWORD_DEFAULT);

        $find=$db->prepare('SELECT id FROM users WHERE email=:email LIMIT 1');
        $find->execute(['email'=>$email]);
        $id=(int)($find->fetchColumn()?:0);
        if(!$id && $email!=='admin@mediapitch.in'){
            $legacy=$db->prepare("SELECT id FROM users WHERE email='admin@mediapitch.in' AND name='MediaPitch Admin' AND role='administrator' LIMIT 1");
            $legacy->execute();$id=(int)($legacy->fetchColumn()?:0);
        }

        $securityColumns=self::hasUserColumn('failed_login_count') && self::hasUserColumn('last_failed_login_at');
        if($id){
            $sql="UPDATE users SET name=:name,email=:email,password_hash=:password_hash,role='administrator',active=1";
            if($securityColumns)$sql.=',failed_login_count=0,last_failed_login_at=NULL';
            $sql.=' WHERE id=:id';
            $update=$db->prepare($sql);
            $update->execute(['name'=>$name,'email'=>$email,'password_hash'=>$hash,'id'=>$id]);
        }else{
            if($securityColumns){
                $insert=$db->prepare("INSERT INTO users (name,email,password_hash,role,active,failed_login_count,last_failed_login_at) VALUES (:name,:email,:password_hash,'administrator',1,0,NULL)");
            }else{
                $insert=$db->prepare("INSERT INTO users (name,email,password_hash,role,active) VALUES (:name,:email,:password_hash,'administrator',1)");
            }
            $insert->execute(['name'=>$name,'email'=>$email,'password_hash'=>$hash]);
            $id=(int)$db->lastInsertId();
        }

        $stmt=$db->prepare('SELECT id,name,email,password_hash,role FROM users WHERE id=:id AND active=1 LIMIT 1');
        $stmt->execute(['id'=>$id]);
        $user=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user)throw new RuntimeException('Bootstrap administrator recovery failed.');
        return $user;
    }

    private static function hasUserColumn(string $column): bool
    {
        if(self::$userColumns===null){
            self::$userColumns=[];
            try{
                $rows=Database::connection()->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_ASSOC);
                foreach($rows as $row){
                    $name=(string)($row['Field']??'');
                    if($name!=='')self::$userColumns[$name]=true;
                }
            }catch(\Throwable){
                // If introspection fails, behave like the oldest supported schema.
            }
        }
        return isset(self::$userColumns[$column]);
    }
}
