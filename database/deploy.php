<?php

declare(strict_types=1);

use MediaPitch\Core\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

$db = Database::connection();

$hasProducts = false;
try {
    $db->query('SELECT 1 FROM products LIMIT 1');
    $hasProducts = true;
} catch (Throwable) {
    $hasProducts = false;
}

if (!$hasProducts) {
    $schema = trim((string) file_get_contents(__DIR__ . '/schema.sql'));
    if ($schema === '') {
        fwrite(STDERR, "Base schema is empty or unavailable.\n");
        exit(1);
    }

    try {
        $db->exec($schema);
        fwrite(STDOUT, "Base schema ensured.\n");
    } catch (Throwable $e) {
        fwrite(STDERR, 'Base schema failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
} else {
    fwrite(STDOUT, "Base schema already present.\n");
}

$run = static function (string $script): void {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $script);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Deployment database step failed: {$script}\n");
        exit($exitCode ?: 1);
    }
};

$run('migrate.php');
$run('seed-defaults.php');
$run('bootstrap-admin.php');

fwrite(STDOUT, "Database deployment tasks complete.\n");
