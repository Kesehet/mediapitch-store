<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class AdminRepository
{
    public function dashboard(): array
    {
        $db = Database::connection();
        $counts = [];
        foreach ([
            'products' => 'SELECT COUNT(*) FROM products',
            'manual_products' => "SELECT COUNT(*) FROM products WHERE source = 'manual'",
            'api_products' => "SELECT COUNT(*) FROM products WHERE source = 'amazon_api'",
            'guides' => "SELECT COUNT(*) FROM content WHERE type = 'buying_guide' AND status = 'published'",
            'drafts' => "SELECT COUNT(*) FROM content WHERE status = 'draft'",
            'clicks' => 'SELECT COUNT(*) FROM affiliate_clicks',
        ] as $key => $sql) {
            $counts[$key] = (int) $db->query($sql)->fetchColumn();
        }

        $topProducts = $db->query(
            'SELECT p.id, COALESCE(p.display_title, p.title) AS title, COUNT(ac.id) AS clicks
             FROM products p LEFT JOIN affiliate_clicks ac ON ac.product_id = p.id
             GROUP BY p.id, p.display_title, p.title ORDER BY clicks DESC, p.updated_at DESC LIMIT 5'
        )->fetchAll(PDO::FETCH_ASSOC);

        return ['counts' => $counts, 'topProducts' => $topProducts];
    }

    public function categories(): array
    {
        return Database::connection()->query(
            'SELECT c.*, p.name AS parent_name FROM categories c LEFT JOIN categories p ON p.id = c.parent_id ORDER BY c.sort_order, c.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categoryOptions(): array
    {
        return Database::connection()->query('SELECT id, name FROM categories WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveCategory(array $data, ?int $id = null): int
    {
        $db = Database::connection();
        $params = [
            'parent_id' => $data['parent_id'] ?: null,
            'name' => trim((string) $data['name']),
            'slug' => trim((string) $data['slug']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'seo_title' => trim((string) ($data['seo_title'] ?? '')) ?: null,
            'meta_description' => trim((string) ($data['meta_description'] ?? '')) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => !empty($data['active']) ? 1 : 0,
        ];

        if ($id) {
            $params['id'] = $id;
            $stmt = $db->prepare(
                'UPDATE categories SET parent_id=:parent_id,name=:name,slug=:slug,description=:description,seo_title=:seo_title,
                 meta_description=:meta_description,sort_order=:sort_order,active=:active WHERE id=:id'
            );
            $stmt->execute($params);
            return $id;
        }

        $stmt = $db->prepare(
            'INSERT INTO categories (parent_id,name,slug,description,seo_title,meta_description,sort_order,active)
             VALUES (:parent_id,:name,:slug,:description,:seo_title,:meta_description,:sort_order,:active)'
        );
        $stmt->execute($params);
        return (int) $db->lastInsertId();
    }

    public function brands(): array
    {
        return Database::connection()->query('SELECT id,name,slug,website_url,logo_url,updated_at FROM brands ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function brand(?int $id): ?array
    {
        if (!$id) return null;
        $stmt = Database::connection()->prepare('SELECT * FROM brands WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveBrand(array $data, ?int $id = null): int
    {
        $db = Database::connection();
        $params = [
            'name' => trim((string)($data['name'] ?? '')),
            'slug' => trim((string)($data['slug'] ?? '')),
            'website_url' => trim((string)($data['website_url'] ?? '')) ?: null,
            'logo_url' => trim((string)($data['logo_url'] ?? '')) ?: null,
        ];
        if ($id) {
            $params['id'] = $id;
            $stmt = $db->prepare('UPDATE brands SET name=:name,slug=:slug,website_url=:website_url,logo_url=:logo_url WHERE id=:id');
            $stmt->execute($params);
            return $id;
        }
        $stmt = $db->prepare('INSERT INTO brands (name,slug,website_url,logo_url) VALUES (:name,:slug,:website_url,:logo_url)');
        $stmt->execute($params);
        return (int)$db->lastInsertId();
    }

    public function products(): array
    {
        return Database::connection()->query(
            'SELECT p.id, p.title, p.display_title, p.slug, p.source, p.price, p.currency, p.active, p.updated_at,
                    c.name AS category_name, b.name AS brand_name
             FROM products p LEFT JOIN categories c ON c.id=p.category_id LEFT JOIN brands b ON b.id=p.brand_id
             ORDER BY p.updated_at DESC LIMIT 200'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function product(?int $id): ?array
    {
        if (!$id) return null;
        $stmt = Database::connection()->prepare('SELECT * FROM products WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveProduct(array $data, ?int $id = null): int
    {
        $db = Database::connection();
        $jsonList = static function (?string $value): ?string {
            $items = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', (string) $value) ?: [])));
            return $items ? json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
        };
        $params = [
            'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'brand_id' => !empty($data['brand_id']) ? (int)$data['brand_id'] : null,
            'asin' => trim((string)($data['asin'] ?? '')) ?: null,
            'title' => trim((string)$data['title']),
            'display_title' => trim((string)($data['display_title'] ?? '')) ?: null,
            'slug' => trim((string)$data['slug']),
            'source' => in_array(($data['source'] ?? 'manual'), ['manual','amazon_api','hybrid'], true) ? $data['source'] : 'manual',
            'short_description' => trim((string)($data['short_description'] ?? '')) ?: null,
            'full_description' => trim((string)($data['full_description'] ?? '')) ?: null,
            'main_image_url' => trim((string)($data['main_image_url'] ?? '')) ?: null,
            'features_json' => $jsonList($data['features'] ?? ''),
            'pros_json' => $jsonList($data['pros'] ?? ''),
            'cons_json' => $jsonList($data['cons'] ?? ''),
            'price' => ($data['price'] ?? '') !== '' ? (float)$data['price'] : null,
            'previous_price' => ($data['previous_price'] ?? '') !== '' ? (float)$data['previous_price'] : null,
            'currency' => strtoupper(substr(trim((string)($data['currency'] ?? 'INR')), 0, 3)),
            'amazon_url' => trim((string)($data['amazon_url'] ?? '')) ?: null,
            'affiliate_url' => trim((string)($data['affiliate_url'] ?? '')) ?: null,
            'custom_score' => ($data['custom_score'] ?? '') !== '' ? (float)$data['custom_score'] : null,
            'best_for_label' => trim((string)($data['best_for_label'] ?? '')) ?: null,
            'editorial_notes' => trim((string)($data['editorial_notes'] ?? '')) ?: null,
            'active' => !empty($data['active']) ? 1 : 0,
        ];

        $fields = array_keys($params);
        if ($id) {
            $params['id'] = $id;
            $sets = implode(',', array_map(static fn($f) => "$f=:$f", $fields));
            $stmt = $db->prepare("UPDATE products SET $sets WHERE id=:id");
            $stmt->execute($params);
            return $id;
        }
        $columns = implode(',', $fields);
        $values = implode(',', array_map(static fn($f) => ":$f", $fields));
        $stmt = $db->prepare("INSERT INTO products ($columns) VALUES ($values)");
        $stmt->execute($params);
        return (int)$db->lastInsertId();
    }

    public function guides(): array
    {
        return Database::connection()->query(
            "SELECT c.id,c.title,c.slug,c.status,c.updated_at,cat.name AS category_name,COUNT(cp.id) AS product_count
             FROM content c LEFT JOIN categories cat ON cat.id=c.category_id LEFT JOIN content_products cp ON cp.content_id=c.id
             WHERE c.type='buying_guide' GROUP BY c.id,c.title,c.slug,c.status,c.updated_at,cat.name ORDER BY c.updated_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guide(?int $id): ?array
    {
        if (!$id) return null;
        $stmt = Database::connection()->prepare("SELECT * FROM content WHERE id=:id AND type='buying_guide' LIMIT 1");
        $stmt->execute(['id'=>$id]);
        $guide = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$guide) return null;
        $stmt = Database::connection()->prepare(
            'SELECT cp.*,COALESCE(p.display_title,p.title) AS product_title FROM content_products cp JOIN products p ON p.id=cp.product_id
             WHERE cp.content_id=:id ORDER BY COALESCE(cp.rank_position,999999),cp.sort_order,cp.id'
        );
        $stmt->execute(['id'=>$id]);
        $guide['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $guide;
    }

    public function productOptions(): array
    {
        return Database::connection()->query('SELECT id,COALESCE(display_title,title) AS title FROM products WHERE active=1 ORDER BY title LIMIT 1000')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveGuide(array $data, int $authorId, ?int $id = null): int
    {
        $db = Database::connection();
        $params = [
            'category_id' => !empty($data['category_id']) ? (int)$data['category_id'] : null,
            'author_id' => $authorId,
            'title' => trim((string)$data['title']),
            'slug' => trim((string)$data['slug']),
            'excerpt' => trim((string)($data['excerpt'] ?? '')) ?: null,
            'body' => trim((string)($data['body'] ?? '')) ?: null,
            'featured_image_url' => trim((string)($data['featured_image_url'] ?? '')) ?: null,
            'seo_title' => trim((string)($data['seo_title'] ?? '')) ?: null,
            'meta_description' => trim((string)($data['meta_description'] ?? '')) ?: null,
            'status' => in_array(($data['status'] ?? 'draft'), ['draft','scheduled','published'], true) ? $data['status'] : 'draft',
            'published_at' => !empty($data['published_at']) ? date('Y-m-d H:i:s', strtotime((string)$data['published_at'])) : null,
        ];
        if ($params['status'] === 'published' && !$params['published_at']) $params['published_at'] = gmdate('Y-m-d H:i:s');

        if ($id) {
            $params['id']=$id;
            $stmt=$db->prepare('UPDATE content SET category_id=:category_id,author_id=:author_id,title=:title,slug=:slug,excerpt=:excerpt,body=:body,featured_image_url=:featured_image_url,seo_title=:seo_title,meta_description=:meta_description,status=:status,published_at=:published_at WHERE id=:id');
            $stmt->execute($params);
        } else {
            $stmt=$db->prepare("INSERT INTO content (type,category_id,author_id,title,slug,excerpt,body,featured_image_url,seo_title,meta_description,status,published_at) VALUES ('buying_guide',:category_id,:author_id,:title,:slug,:excerpt,:body,:featured_image_url,:seo_title,:meta_description,:status,:published_at)");
            $stmt->execute($params);
            $id=(int)$db->lastInsertId();
        }

        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM content_products WHERE content_id=:id')->execute(['id'=>$id]);
            $productIds = $data['product_id'] ?? [];
            foreach ($productIds as $i => $productId) {
                $productId=(int)$productId;
                if ($productId < 1) continue;
                $stmt=$db->prepare('INSERT INTO content_products (content_id,product_id,rank_position,score,best_for_label,recommendation,cta_text,sort_order) VALUES (:content_id,:product_id,:rank,:score,:best_for,:recommendation,:cta,:sort_order)');
                $stmt->execute([
                    'content_id'=>$id,'product_id'=>$productId,
                    'rank'=>($data['rank'][$i] ?? '') !== '' ? (int)$data['rank'][$i] : null,
                    'score'=>($data['product_score'][$i] ?? '') !== '' ? (float)$data['product_score'][$i] : null,
                    'best_for'=>trim((string)($data['product_best_for'][$i] ?? '')) ?: null,
                    'recommendation'=>trim((string)($data['recommendation'][$i] ?? '')) ?: null,
                    'cta'=>trim((string)($data['cta_text'][$i] ?? '')) ?: null,
                    'sort_order'=>$i,
                ]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        return $id;
    }
}
