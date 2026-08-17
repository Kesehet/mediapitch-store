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

/**
 * Return a price that is safe to display publicly.
 * Amazon offer data is treated as fresh for one hour. Manual products are
 * unaffected. The helper lazily looks up source/sync metadata when compact
 * repository rows do not include it, and caches that lookup for the request.
 */
function public_product_price(array $product): ?float
{
    if (!isset($product['price']) || $product['price'] === null || $product['price'] === '') {
        return null;
    }

    $source = isset($product['source']) ? (string) $product['source'] : null;
    $lastSynced = isset($product['last_synced_at']) ? (string) $product['last_synced_at'] : null;
    $lookupId = !empty($product['product_id']) ? (int) $product['product_id'] : (!empty($product['id']) ? (int) $product['id'] : 0);

    if (($source === null || $lastSynced === null) && $lookupId > 0) {
        static $metadata = [];
        if (!array_key_exists($lookupId, $metadata)) {
            try {
                $stmt = Database::connection()->prepare('SELECT source,last_synced_at FROM products WHERE id=:id LIMIT 1');
                $stmt->execute(['id' => $lookupId]);
                $metadata[$lookupId] = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (Throwable) {
                $metadata[$lookupId] = [];
            }
        }
        $source ??= isset($metadata[$lookupId]['source']) ? (string) $metadata[$lookupId]['source'] : null;
        $lastSynced ??= isset($metadata[$lookupId]['last_synced_at']) ? (string) $metadata[$lookupId]['last_synced_at'] : null;
    }

    if (in_array($source, ['amazon_api', 'hybrid'], true)) {
        if ($lastSynced === null || $lastSynced === '') {
            return null;
        }
        $stamp = strtotime($lastSynced . ' UTC');
        if ($stamp === false || $stamp < time() - 3600) {
            return null;
        }
    }

    return (float) $product['price'];
}

function request_base_url(): ?string
{
    if (PHP_SAPI === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return null;
    }

    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) $_SERVER['HTTP_HOST']) ?: '';
    if ($host === '') {
        return null;
    }

    $proto = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwarded = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
        if (in_array($forwarded, ['http', 'https'], true)) {
            $proto = $forwarded;
        }
    }
    if ($proto === '') {
        $https = $_SERVER['HTTPS'] ?? '';
        $proto = ($https !== '' && strtolower((string) $https) !== 'off') ? 'https' : 'http';
    }

    return $proto . '://' . $host;
}

function url(string $path = ''): string
{
    $configured = rtrim((string) env('APP_URL', ''), '/');
    $requestBase = request_base_url();

    $configuredHost = $configured !== '' ? (string) parse_url($configured, PHP_URL_HOST) : '';
    $configuredIsLocal = in_array(strtolower($configuredHost), ['localhost', '127.0.0.1', '::1'], true);
    $requestHost = $requestBase ? (string) parse_url($requestBase, PHP_URL_HOST) : '';
    $requestIsLocal = in_array(strtolower($requestHost), ['localhost', '127.0.0.1', '::1'], true);

    if ($requestBase !== null && ($configured === '' || ($configuredIsLocal && !$requestIsLocal))) {
        $base = $requestBase;
    } else {
        $base = $configured;
    }

    return rtrim($base, '/') . '/' . ltrim($path, '/');
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
