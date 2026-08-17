<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class SearchRepository
{
    public function search(string $query,int $limitPerType=12): array
    {
        $db=Database::connection();
        $like='%'.$query.'%';

        $products=$db->prepare(
            'SELECT p.id,p.title,p.display_title,p.slug,p.main_image_url,p.price,p.currency,p.custom_score,p.best_for_label,b.name AS brand_name,c.name AS category_name
             FROM products p
             LEFT JOIN brands b ON b.id=p.brand_id
             LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.active=1 AND (p.title LIKE :q1 OR p.display_title LIKE :q2 OR p.short_description LIKE :q3 OR b.name LIKE :q4 OR c.name LIKE :q5)
             ORDER BY p.custom_score DESC,p.updated_at DESC LIMIT :limit'
        );
        foreach([':q1',':q2',':q3',':q4',':q5'] as $key)$products->bindValue($key,$like);
        $products->bindValue(':limit',$limitPerType,PDO::PARAM_INT);
        $products->execute();

        $categories=$db->prepare(
            'SELECT id,name,slug,description,image_url FROM categories
             WHERE active=1 AND (name LIKE :q1 OR description LIKE :q2)
             ORDER BY sort_order,name LIMIT :limit'
        );
        $categories->bindValue(':q1',$like);$categories->bindValue(':q2',$like);$categories->bindValue(':limit',$limitPerType,PDO::PARAM_INT);$categories->execute();

        $content=$db->prepare(
            "SELECT id,type,title,slug,excerpt,featured_image_url,published_at
             FROM content
             WHERE type IN ('buying_guide','comparison','blog','review')
               AND status IN ('published','scheduled')
               AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP()
               AND (title LIKE :q1 OR excerpt LIKE :q2 OR body LIKE :q3)
             ORDER BY published_at DESC LIMIT :limit"
        );
        $content->bindValue(':q1',$like);$content->bindValue(':q2',$like);$content->bindValue(':q3',$like);$content->bindValue(':limit',$limitPerType*2,PDO::PARAM_INT);$content->execute();
        $contentRows=$content->fetchAll(PDO::FETCH_ASSOC);

        $grouped=['guides'=>[],'comparisons'=>[],'articles'=>[],'reviews'=>[]];
        foreach($contentRows as $row){
            match($row['type']){
                'buying_guide'=>$grouped['guides'][]=$row,
                'comparison'=>$grouped['comparisons'][]=$row,
                'blog'=>$grouped['articles'][]=$row,
                'review'=>$grouped['reviews'][]=$row,
                default=>null,
            };
        }

        return [
            'products'=>$products->fetchAll(PDO::FETCH_ASSOC),
            'categories'=>$categories->fetchAll(PDO::FETCH_ASSOC),
            ...$grouped,
        ];
    }
}
