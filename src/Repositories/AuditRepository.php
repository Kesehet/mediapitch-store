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
}
