<?php

declare(strict_types=1);

use MediaPitch\Core\Database;
use MediaPitch\Core\Html;

spl_autoload_register(static function (string $class): void {
    $prefix = 'MediaPitch\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

function load_env(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }

    return match (strtolower($value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function safe_html(?string $value): string
{
    return Html::sanitize($value);
}

function url(string $path = ''): string
{
    $base = rtrim((string) env('APP_URL', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

load_env(dirname(__DIR__) . '/.env');
date_default_timezone_set('UTC');

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; frame-ancestors 'self'; form-action 'self'; img-src 'self' https: data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; script-src 'self' 'unsafe-inline'; connect-src 'self'");
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (bool) env('SESSION_SECURE_COOKIE', true);
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $secure = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
    } elseif (isset($_SERVER['HTTPS'])) {
        $secure = $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_name((string) env('SESSION_NAME', 'mediapitch_session'));
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

Database::configure([
    'host' => (string) env('DB_HOST', '127.0.0.1'),
    'port' => (string) env('DB_PORT', '3306'),
    'database' => (string) env('DB_DATABASE', 'mediapitch_store'),
    'username' => (string) env('DB_USERNAME', 'root'),
    'password' => (string) env('DB_PASSWORD', ''),
    'charset' => (string) env('DB_CHARSET', 'utf8mb4'),
]);
