<?php

declare(strict_types=1);

use MediaPitch\Core\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    Database::connection()->query('SELECT 1');
} catch (Throwable $e) {
    $production=strtolower((string)env('APP_ENV','production'))==='production';
    $message="Database is not available during the Composer deployment phase.\n";
    fwrite($production ? STDERR : STDOUT,$message);
    if ((bool) env('APP_DEBUG', false)) {
        fwrite($production ? STDERR : STDOUT, 'Database connection detail: ' . $e->getMessage() . "\n");
    }
    fwrite($production ? STDERR : STDOUT, "Run `composer deploy-db` after the production DB environment is available.\n");

    // In production, shipping code without its required schema is unsafe: the
    // application can immediately fatal on missing tables/columns. Fail the
    // deployment so the hosting platform cannot report success while leaving
    // the database behind. Local/dev installs remain tolerant for setup flows.
    exit($production ? 1 : 0);
}

$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/deploy.php');
passthru($command, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Database was reachable, but deployment migrations/seeding failed.\n");
    exit($exitCode ?: 1);
}

exit(0);
