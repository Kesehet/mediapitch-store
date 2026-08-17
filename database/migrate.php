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

    $db->beginTransaction();
    try {
        $db->exec($sql);
        $stmt = $db->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
        $stmt->execute(['migration' => $name]);
        $db->commit();
        fwrite(STDOUT, "apply {$name}\n");
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        fwrite(STDERR, "failed {$name}: {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "Migrations complete.\n");
