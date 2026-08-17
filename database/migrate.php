<?php

declare(strict_types=1);

use MediaPitch\Core\Database;

require dirname(__DIR__) . '/src/bootstrap.php';

$db = Database::connection();
$db->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        migration VARCHAR(255) PRIMARY KEY,
        applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$applied = array_fill_keys(
    $db->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN),
    true
);

$files = glob(__DIR__ . '/migrations/*.sql') ?: [];
sort($files, SORT_NATURAL);

foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        fwrite(STDOUT, "skip  {$name}\n");
        continue;
    }

    $sql = trim((string) file_get_contents($file));
    if ($sql === '') {
        continue;
    }

    try {
        // MySQL DDL statements such as CREATE/ALTER TABLE implicitly commit.
        // Do not wrap schema migrations in a PDO transaction or commit() can
        // fail after otherwise-successful DDL. Migration SQL must therefore
        // be written idempotently so a partially-applied deploy can be retried.
        $db->exec($sql);
        $stmt = $db->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (:migration)');
        $stmt->execute(['migration' => $name]);
        fwrite(STDOUT, "apply {$name}\n");
    } catch (Throwable $e) {
        fwrite(STDERR, "failed {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Migrations complete.\n");
