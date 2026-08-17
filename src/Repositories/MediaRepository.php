<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class MediaRepository
{
    public function all(int $limit = 100): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT m.*, u.name AS uploader_name FROM media m LEFT JOIN users u ON u.id=m.uploaded_by ORDER BY m.created_at DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO media (uploaded_by,original_name,file_name,file_path,thumbnail_path,optimized,mime_type,file_size,width,height,alt_text)
             VALUES (:uploaded_by,:original_name,:file_name,:file_path,:thumbnail_path,:optimized,:mime_type,:file_size,:width,:height,:alt_text)'
        );
        $stmt->execute([
            'uploaded_by'=>$data['uploaded_by'] ?? null,
            'original_name'=>$data['original_name'],
            'file_name'=>$data['file_name'],
            'file_path'=>$data['file_path'],
            'thumbnail_path'=>$data['thumbnail_path'] ?? null,
            'optimized'=>!empty($data['optimized']) ? 1 : 0,
            'mime_type'=>$data['mime_type'],
            'file_size'=>$data['file_size'],
            'width'=>$data['width'] ?? null,
            'height'=>$data['height'] ?? null,
            'alt_text'=>$data['alt_text'] ?? null,
        ]);
        return (int) Database::connection()->lastInsertId();
    }
}
