<?php

declare(strict_types=1);

namespace MediaPitch\Core;

use PDO;

final class Auth
{
    public static function attempt(string $email, string $password): bool
    {
        if (self::loginBlocked()) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, password_hash, role FROM users WHERE email = :email AND active = 1 LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            self::recordFailedAttempt();
            return false;
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

    public static function canManageProducts(): bool
    {
        return in_array((string) (self::user()['role'] ?? ''), ['administrator', 'editor'], true);
    }

    public static function canPublish(): bool
    {
        return in_array((string) (self::user()['role'] ?? ''), ['administrator', 'editor'], true);
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

    private static function recordFailedAttempt(): void
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
        if ($guard['count'] >= $limit) {
            $guard['blocked_until'] = $now + $blockFor;
            $guard['count'] = 0;
            $guard['first_at'] = $now;
        }
        $_SESSION['_login_guard'] = $guard;
    }
}
