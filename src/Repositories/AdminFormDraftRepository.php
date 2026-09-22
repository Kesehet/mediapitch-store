<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class AdminFormDraftRepository
{
    public function save(int $userId,string $key,array $payload): void
    {
        $key=substr(trim($key),0,190);
        if($userId<1||$key==='') return;
        $json=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $stmt=Database::connection()->prepare(
            'INSERT INTO admin_form_drafts (user_id,draft_key,payload_json) VALUES (:user_id,:draft_key,:payload_json)
             ON DUPLICATE KEY UPDATE payload_json=VALUES(payload_json),updated_at=CURRENT_TIMESTAMP'
        );
        $stmt->execute(['user_id'=>$userId,'draft_key'=>$key,'payload_json'=>$json]);
    }

    public function find(int $userId,string $key): ?array
    {
        $stmt=Database::connection()->prepare(
            'SELECT payload_json,updated_at FROM admin_form_drafts WHERE user_id=:user_id AND draft_key=:draft_key LIMIT 1'
        );
        $stmt->execute(['user_id'=>$userId,'draft_key'=>substr(trim($key),0,190)]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) return null;
        $payload=json_decode((string)$row['payload_json'],true);
        if(!is_array($payload)) return null;
        return ['payload'=>$payload,'updated_at'=>$row['updated_at']];
    }

    public function delete(int $userId,string $key): void
    {
        $stmt=Database::connection()->prepare('DELETE FROM admin_form_drafts WHERE user_id=:user_id AND draft_key=:draft_key');
        $stmt->execute(['user_id'=>$userId,'draft_key'=>substr(trim($key),0,190)]);
    }
}
