<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Services\ContentVisibility;
use PDO;

final class RelatedContentRepository
{
    public function forProduct(int $productId, ?int $categoryId, int $limit = 4): array
    {
        $db=Database::connection();
        $products=[];
        if($categoryId){
            $stmt=$db->prepare(
                'SELECT p.id,p.title,p.display_title,p.slug,p.main_image_url,p.price,p.currency,p.custom_score,p.best_for_label,b.name AS brand_name
                 FROM products p LEFT JOIN brands b ON b.id=p.brand_id
                 WHERE p.active=1 AND p.category_id=:category_id AND p.id<>:product_id
                 ORDER BY p.custom_score IS NULL,p.custom_score DESC,p.updated_at DESC LIMIT :limit'
            );
            $stmt->bindValue(':category_id',$categoryId,PDO::PARAM_INT);
            $stmt->bindValue(':product_id',$productId,PDO::PARAM_INT);
            $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
            $stmt->execute();
            $products=$stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $visibility=ContentVisibility::sql('c');
        $guides=$db->prepare(
            "SELECT DISTINCT c.id,c.title,c.slug,c.excerpt,c.featured_image_url,c.published_at
             FROM content c JOIN content_products cp ON cp.content_id=c.id
             WHERE cp.product_id=:product_id AND c.type='buying_guide' AND $visibility
             ORDER BY COALESCE(c.published_at,c.created_at) DESC LIMIT :limit"
        );
        $guides->bindValue(':product_id',$productId,PDO::PARAM_INT);
        $guides->bindValue(':limit',$limit,PDO::PARAM_INT);
        $guides->execute();

        return ['products'=>$products,'guides'=>$guides->fetchAll(PDO::FETCH_ASSOC)];
    }

    public function forContent(int $contentId, ?int $categoryId, int $limit = 6): array
    {
        if(!$categoryId)return [];
        $visibility=ContentVisibility::sql('c');
        $stmt=Database::connection()->prepare(
            "SELECT c.id,c.type,c.title,c.slug,c.excerpt,c.featured_image_url,c.published_at
             FROM content c
             WHERE c.category_id=:category_id AND c.id<>:content_id
               AND c.type IN ('buying_guide','blog','comparison','review') AND $visibility
             ORDER BY COALESCE(c.published_at,c.created_at) DESC LIMIT :limit"
        );
        $stmt->bindValue(':category_id',$categoryId,PDO::PARAM_INT);
        $stmt->bindValue(':content_id',$contentId,PDO::PARAM_INT);
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
