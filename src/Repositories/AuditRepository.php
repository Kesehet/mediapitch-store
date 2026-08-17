<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class AuditRepository
{
    public function recent(int $limit=200): array
    {
        $limit=max(25,min(500,$limit));
        $stmt=Database::connection()->prepare(
            'SELECT a.*,u.name AS user_name,u.email AS user_email
             FROM admin_audit_log a LEFT JOIN users u ON u.id=a.user_id
             ORDER BY a.created_at DESC,a.id DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function forEntity(string $entityType,int $entityId,int $limit=25): array
    {
        $limit=max(1,min(100,$limit));
        $stmt=Database::connection()->prepare(
            'SELECT a.*,u.name AS user_name,u.email AS user_email
             FROM admin_audit_log a LEFT JOIN users u ON u.id=a.user_id
             WHERE a.entity_type=:entity_type AND a.entity_id=:entity_id
             ORDER BY a.created_at DESC,a.id DESC LIMIT :limit'
        );
        $stmt->bindValue(':entity_type',$entityType);
        $stmt->bindValue(':entity_id',$entityId,PDO::PARAM_INT);
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
