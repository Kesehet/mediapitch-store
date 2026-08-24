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

/**
 * Automatic production migrations are append-only.
 *
 * Deploys may create missing schema objects or add columns/indexes, but they
 * must never silently rewrite or remove live production data. Destructive
 * changes belong in a separately reviewed maintenance script.
 */
$assertNonDestructiveMigration = static function (string $sql, string $name): void {
    $normalized = preg_replace('/\/\*.*?\*\//s', ' ', $sql) ?? $sql;
    $normalized = preg_replace('/^\s*--.*$/m', ' ', $normalized) ?? $normalized;
    $normalized = preg_replace('/^\s*#.*$/m', ' ', $normalized) ?? $normalized;

    $forbidden = [
        '/\bDROP\s+(?:DATABASE|SCHEMA|TABLE|COLUMN|INDEX|KEY|FOREIGN\s+KEY)\b/i' => 'DROP',
        '/\bTRUNCATE\b/i' => 'TRUNCATE',
        '/\bDELETE\s+FROM\b/i' => 'DELETE',
        '/\bUPDATE\s+[A-Za-z0-9_`]+\s+SET\b/i' => 'UPDATE',
        '/\bREPLACE\s+INTO\b/i' => 'REPLACE',
        '/\bRENAME\s+TABLE\b/i' => 'RENAME TABLE',
        '/\bCREATE\s+OR\s+REPLACE\s+TABLE\b/i' => 'CREATE OR REPLACE TABLE',
        '/\bALTER\s+TABLE\b[\s\S]*?\b(?:CHANGE|MODIFY)\s+(?:COLUMN\s+)?/i' => 'ALTER CHANGE/MODIFY',
    ];

    foreach ($forbidden as $pattern => $operation) {
        if (preg_match($pattern, $normalized) === 1) {
            fwrite(
                STDERR,
                "blocked {$name}: destructive operation {$operation} is not allowed in automatic migrations.\n" .
                "Use an explicitly reviewed maintenance script for destructive production changes.\n"
            );
            exit(1);
        }
    }
};

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

    $assertNonDestructiveMigration($sql, $name);

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
