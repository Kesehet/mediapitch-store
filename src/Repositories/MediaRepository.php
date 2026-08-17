<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use PDO;

final class MediaRepository
{
    public function all(int $limit = 100, string $query = ''): array
    {
        $db=Database::connection();
        $query=trim($query);
        if($query===''){
            $stmt=$db->prepare(
                'SELECT m.*, u.name AS uploader_name FROM media m LEFT JOIN users u ON u.id=m.uploaded_by ORDER BY m.created_at DESC LIMIT :limit'
            );
        }else{
            $stmt=$db->prepare(
                'SELECT m.*, u.name AS uploader_name FROM media m LEFT JOIN users u ON u.id=m.uploaded_by
                 WHERE m.original_name LIKE :q1 OR m.alt_text LIKE :q2 OR m.file_name LIKE :q3
                 ORDER BY m.created_at DESC LIMIT :limit'
            );
            $like='%'.$query.'%';
            $stmt->bindValue(':q1',$like);
            $stmt->bindValue(':q2',$like);
            $stmt->bindValue(':q3',$like);
        }
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        $items=$stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($items as &$item){
            $item['usage']=$this->usageForPath((string)$item['file_path']);
        }
        unset($item);
        return $items;
    }

    public function find(int $id): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM media WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
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

    public function deleteIfUnused(int $id): array
    {
        $item=$this->find($id);
        if(!$item) throw new InvalidArgumentException('Media item not found.');
        $usage=$this->usageForPath((string)$item['file_path']);
        if($usage){
            throw new InvalidArgumentException('This image is still in use by: '.implode(', ',$usage).'. Remove those references first.');
        }
        $stmt=Database::connection()->prepare('DELETE FROM media WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        return $item;
    }

    public function usageForPath(string $path): array
    {
        $path=trim($path);
        if($path==='') return [];
        $db=Database::connection();
        $like='%'.$path.'%';
        $checks=[
            'product image'=>"SELECT COUNT(*) FROM products WHERE main_image_url LIKE :path OR gallery_json LIKE :path2",
            'category image'=>"SELECT COUNT(*) FROM categories WHERE image_url LIKE :path",
            'brand logo'=>"SELECT COUNT(*) FROM brands WHERE logo_url LIKE :path",
            'article/guide/review/comparison image'=>"SELECT COUNT(*) FROM content WHERE featured_image_url LIKE :path",
        ];
        $usage=[];
        foreach($checks as $label=>$sql){
            $stmt=$db->prepare($sql);
            $params=['path'=>$like];
            if(str_contains($sql,':path2')) $params['path2']=$like;
            $stmt->execute($params);
            if((int)$stmt->fetchColumn()>0) $usage[]=$label;
        }
        return $usage;
    }
}
