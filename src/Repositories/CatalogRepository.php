<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class CatalogRepository
{
    public function featuredCategories(int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, slug, description, image_url FROM categories WHERE active = 1 ORDER BY sort_order, name LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function featuredProducts(int $limit = 8): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.id, p.title, p.display_title, p.slug, p.main_image_url, p.price, p.currency, p.custom_score, p.best_for_label, p.affiliate_url, b.name AS brand_name, c.name AS category_name
             FROM products p
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.active = 1
             ORDER BY p.updated_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function latestContent(string $type, int $limit = 6): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT id, title, slug, excerpt, featured_image_url, published_at FROM content
             WHERE type = :type AND status = \'published\' AND (published_at IS NULL OR published_at <= UTC_TIMESTAMP())
             ORDER BY COALESCE(published_at, created_at) DESC LIMIT :limit'
        );
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function productBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT p.*, b.name AS brand_name, c.name AS category_name, c.slug AS category_slug
             FROM products p
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.slug = :slug AND p.active = 1 LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function productById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM products WHERE id = :id AND active = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function buyingGuideBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM content WHERE slug = :slug AND type = \'buying_guide\' AND status = \'published\' LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $guide = $stmt->fetch();
        if (!$guide) {
            return null;
        }

        $products = Database::connection()->prepare(
            'SELECT cp.rank_position, cp.score AS guide_score, cp.best_for_label AS guide_best_for, cp.recommendation, cp.cta_text,
                    p.id, p.title, p.display_title, p.slug, p.main_image_url, p.short_description, p.features_json, p.pros_json, p.cons_json,
                    p.price, p.currency, p.custom_score, p.best_for_label, p.affiliate_url, b.name AS brand_name
             FROM content_products cp
             JOIN products p ON p.id = cp.product_id AND p.active = 1
             LEFT JOIN brands b ON b.id = p.brand_id
             WHERE cp.content_id = :content_id
             ORDER BY COALESCE(cp.rank_position, 999999), cp.sort_order, cp.id'
        );
        $products->execute(['content_id' => $guide['id']]);
        $guide['products'] = $products->fetchAll();
        return $guide;
    }

    public function search(string $query, int $limit = 30): array
    {
        $like = '%' . $query . '%';
        $stmt = Database::connection()->prepare(
            'SELECT id, title, display_title, slug, main_image_url, price, currency, custom_score, best_for_label
             FROM products
             WHERE active = 1 AND (title LIKE :q OR display_title LIKE :q OR short_description LIKE :q)
             ORDER BY custom_score DESC, title ASC LIMIT :limit'
        );
        $stmt->bindValue(':q', $like);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function recordAffiliateClick(int $productId, ?int $contentId, ?int $rank, ?string $ctaLocation, ?string $referrer, ?string $userAgent, ?string $campaign): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO affiliate_clicks (product_id, content_id, rank_position, cta_location, referring_url, user_agent, campaign)
             VALUES (:product_id, :content_id, :rank_position, :cta_location, :referring_url, :user_agent, :campaign)'
        );
        $stmt->execute([
            'product_id' => $productId,
            'content_id' => $contentId,
            'rank_position' => $rank,
            'cta_location' => $ctaLocation,
            'referring_url' => $referrer,
            'user_agent' => $userAgent,
            'campaign' => $campaign,
        ]);
    }
}
