<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Services\ContentVisibility;
use PDO;

final class ComparisonCatalogRepository
{
    public function published(int $limit=50): array
    {
        $limit=max(1,min(100,$limit));
        $visibility=ContentVisibility::sql('c');
        $stmt=Database::connection()->prepare(
            "SELECT c.id,c.title,c.slug,c.excerpt,c.featured_image_url,c.published_at,cat.name AS category_name,cat.slug AS category_slug,
                    COUNT(cp.id) AS product_count
             FROM content c
             LEFT JOIN categories cat ON cat.id=c.category_id
             LEFT JOIN content_products cp ON cp.content_id=c.id
             WHERE c.type='comparison' AND $visibility
             GROUP BY c.id,c.title,c.slug,c.excerpt,c.featured_image_url,c.published_at,cat.name,cat.slug
             HAVING COUNT(cp.id)>=2
             ORDER BY COALESCE(c.published_at,c.created_at) DESC,c.updated_at DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function latest(int $limit=3): array
    {
        return $this->published($limit);
    }
}
