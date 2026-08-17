<?php

declare(strict_types=1);

use MediaPitch\Core\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    Database::connection()->query('SELECT 1');
} catch (Throwable $e) {
    fwrite(STDOUT, "Database is not available during the Composer phase; skipping automatic DB deployment.\n");
    if ((bool) env('APP_DEBUG', false)) {
        fwrite(STDOUT, 'Database connection detail: ' . $e->getMessage() . "\n");
    }
    fwrite(STDOUT, "Run `composer deploy-db` after production DB environment variables are available.\n");
    exit(0);
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/deploy.php');
passthru($command, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Database was reachable, but deployment migrations/seeding failed.\n");
    exit($exitCode ?: 1);
}

exit(0);
