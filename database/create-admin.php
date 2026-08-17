<?php

declare(strict_types=1);

use MediaPitch\Core\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$name = trim((string)($argv[1] ?? ''));
$email = strtolower(trim((string)($argv[2] ?? '')));
$password = (string)($argv[3] ?? '');

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(STDERR, "Usage: php database/create-admin.php \"Name\" email@example.com \"password-at-least-12-chars\"\n");
    exit(1);
}

$stmt = Database::connection()->prepare(
    "INSERT INTO users (name,email,password_hash,role,active) VALUES (:name,:email,:password_hash,'administrator',1)
     ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role='administrator',active=1"
);
$stmt->execute([
    'name'=>$name,
    'email'=>$email,
    'password_hash'=>password_hash($password, PASSWORD_DEFAULT),
]);

fwrite(STDOUT, "Administrator ready: {$email}\n");
