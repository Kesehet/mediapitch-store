<?php

declare(strict_types=1);

namespace MediaPitch\Core;

use PDO;
use RuntimeException;

final class SchemaRepair
{
    public static function ensureFeatureSchema(): void
    {
        $db=Database::connection();
        if(self::isCurrent($db))return;

        $file=dirname(__DIR__,2).'/database/migrations/012_repair_all_feature_schema.sql';
        $sql=trim((string)@file_get_contents($file));
        if($sql==='')throw new RuntimeException('Database repair migration is unavailable.');

        $db->exec($sql);
        $db->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                migration VARCHAR(255) PRIMARY KEY,
                applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        $stmt=$db->prepare('INSERT IGNORE INTO schema_migrations (migration) VALUES (:migration)');
        $stmt->execute(['migration'=>'012_repair_all_feature_schema.sql']);

        if(!self::isCurrent($db))throw new RuntimeException('Database repair ran but required CMS schema is still incomplete.');
    }

    private static function isCurrent(PDO $db): bool
    {
        $checks=[
            'SELECT failed_login_count,last_login_at,last_failed_login_at FROM users LIMIT 1',
            'SELECT thumbnail_path,optimized FROM media LIMIT 1',
            'SELECT active FROM specification_definitions LIMIT 1',
            'SELECT active FROM brands LIMIT 1',
            'SELECT 1 FROM search_queries LIMIT 1',
            'SELECT 1 FROM admin_audit_log LIMIT 1',
            'SELECT 1 FROM password_reset_tokens LIMIT 1',
            'SELECT 1 FROM tags LIMIT 1',
            'SELECT 1 FROM content_tags LIMIT 1',
        ];
        foreach($checks as $sql){
            try{$db->query($sql);}catch(\Throwable){return false;}
        }
        return true;
    }
}
