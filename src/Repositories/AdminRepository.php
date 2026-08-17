<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use InvalidArgumentException;
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

    public function category(?int $id): ?array
    {
        if(!$id)return null;
        $stmt=Database::connection()->prepare('SELECT * FROM categories WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    public function categoryOptions(): array
    {
        return Database::connection()->query('SELECT id, name FROM categories WHERE active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setCategoryActive(int $id,bool $active): void
    {
        $stmt=Database::connection()->prepare('UPDATE categories SET active=:active WHERE id=:id');
        $stmt->execute(['active'=>$active?1:0,'id'=>$id]);
    }

    public function saveCategory(array $data, ?int $id = null): int
    {
        $db = Database::connection();
        $parent=!empty($data['parent_id'])?(int)$data['parent_id']:null;
        if($id && $parent===$id) throw new InvalidArgumentException('A category cannot be its own parent.');
        $params = [
            'parent_id' => $parent,
            'name' => trim((string) $data['name']),
            'slug' => trim((string) $data['slug']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'seo_title' => trim((string) ($data['seo_title'] ?? '')) ?: null,
            'meta_description' => trim((string) ($data['meta_description'] ?? '')) ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'active' => !empty($data['active']) ? 1 : 0,
        ];
        if($params['name']===''||$params['slug']==='') throw new InvalidArgumentException('Category name and slug are required.');

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

    public function specificationDefinitions(): array
    {
        return Database::connection()->query(
            'SELECT sd.*, c.name AS category_name
             FROM specification_definitions sd
             JOIN categories c ON c.id=sd.category_id
             ORDER BY c.name, sd.sort_order, sd.name'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function specificationDefinition(?int $id): ?array
    {
        if (!$id) return null;
        $stmt = Database::connection()->prepare('SELECT * FROM specification_definitions WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function saveSpecificationDefinition(array $data, ?int $id = null): int
    {
        $db = Database::connection();
        $type = (string)($data['data_type'] ?? 'text');
        if (!in_array($type, ['text','number','boolean','select'], true)) {
            $type = 'text';
        }
        $options = array_values(array_unique(array_filter(array_map('trim', preg_split('/\r?\n/', (string)($data['options'] ?? '')) ?: []))));
        if ($type === 'select' && !$options) {
            throw new InvalidArgumentException('Select specifications require at least one option.');
        }
        $params = [
            'category_id' => (int)($data['category_id'] ?? 0),
            'name' => trim((string)($data['name'] ?? '')),
            'slug' => trim((string)($data['slug'] ?? '')),
            'unit' => trim((string)($data['unit'] ?? '')) ?: null,
            'data_type' => $type,
            'options_json' => $type === 'select' ? json_encode($options, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'filterable' => !empty($data['filterable']) ? 1 : 0,
            'comparable' => !empty($data['comparable']) ? 1 : 0,
            'sort_order' => (int)($data['sort_order'] ?? 0),
        ];
        if ($params['category_id'] < 1 || $params['name'] === '' || $params['slug'] === '') {
            throw new InvalidArgumentException('Category, name and slug are required.');
        }

        if ($id) {
            $params['id'] = $id;
            $stmt = $db->prepare(
                'UPDATE specification_definitions SET category_id=:category_id,name=:name,slug=:slug,unit=:unit,data_type=:data_type,
                 options_json=:options_json,filterable=:filterable,comparable=:comparable,sort_order=:sort_order WHERE id=:id'
            );
            $stmt->execute($params);
            return $id;
        }

        $stmt = $db->prepare(
            'INSERT INTO specification_definitions (category_id,name,slug,unit,data_type,options_json,filterable,comparable,sort_order)
             VALUES (:category_id,:name,:slug,:unit,:data_type,:options_json,:filterable,:comparable,:sort_order)'
        );
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

    public function productSpecificationValues(?int $productId): array
    {
        if (!$productId) return [];
        $stmt = Database::connection()->prepare(
            'SELECT specification_definition_id, value_text, value_number, value_boolean FROM product_specifications WHERE product_id=:id'
        );
        $stmt->execute(['id'=>$productId]);
        $values = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $values[(int)$row['specification_definition_id']] = $row;
        }
        return $values;
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

        $db->beginTransaction();
        try {
            $fields = array_keys($params);
            if ($id) {
                $params['id'] = $id;
                $sets = implode(',', array_map(static fn($f) => "$f=:$f", $fields));
                $stmt = $db->prepare("UPDATE products SET $sets WHERE id=:id");
                $stmt->execute($params);
            } else {
                $columns = implode(',', $fields);
                $values = implode(',', array_map(static fn($f) => ":$f", $fields));
                $stmt = $db->prepare("INSERT INTO products ($columns) VALUES ($values)");
                $stmt->execute($params);
                $id = (int)$db->lastInsertId();
            }

            $this->saveProductSpecifications($id, $params['category_id'], is_array($data['spec'] ?? null) ? $data['spec'] : []);
            $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function saveProductSpecifications(int $productId, ?int $categoryId, array $submitted): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM product_specifications WHERE product_id=:id')->execute(['id'=>$productId]);
        if (!$categoryId) return;

        $stmt = $db->prepare(
            'SELECT id,data_type,options_json FROM specification_definitions WHERE category_id=:category_id ORDER BY sort_order,id'
        );
        $stmt->execute(['category_id'=>$categoryId]);
        $insert = $db->prepare(
            'INSERT INTO product_specifications (product_id,specification_definition_id,value_text,value_number,value_boolean)
             VALUES (:product_id,:definition_id,:value_text,:value_number,:value_boolean)'
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $definition) {
            $definitionId = (int)$definition['id'];
            $raw = $submitted[$definitionId] ?? null;
            if ($raw === null || $raw === '') continue;

            $valueText = null;
            $valueNumber = null;
            $valueBoolean = null;
            switch ($definition['data_type']) {
                case 'number':
                    if (!is_numeric($raw)) throw new InvalidArgumentException('A numeric specification contains an invalid value.');
                    $valueNumber = (float)$raw;
                    break;
                case 'boolean':
                    if (!in_array((string)$raw, ['0','1'], true)) throw new InvalidArgumentException('A yes/no specification contains an invalid value.');
                    $valueBoolean = (int)$raw;
                    break;
                case 'select':
                    $options = json_decode((string)$definition['options_json'], true);
                    if (!is_array($options) || !in_array((string)$raw, $options, true)) {
                        throw new InvalidArgumentException('A selected specification option is invalid.');
                    }
                    $valueText = (string)$raw;
                    break;
                default:
                    $valueText = trim((string)$raw);
                    if ($valueText === '') continue 2;
            }

            $insert->execute([
                'product_id'=>$productId,
                'definition_id'=>$definitionId,
                'value_text'=>$valueText,
                'value_number'=>$valueNumber,
                'value_boolean'=>$valueBoolean,
            ]);
        }
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
            'status' => in_array(($data['status'] ?? 'draft'), ['draft','scheduled','published','archived'], true) ? $data['status'] : 'draft',
            'published_at' => !empty($data['published_at']) ? date('Y-m-d H:i:s', strtotime((string)$data['published_at'])) : null,
            'seo_title' => trim((string)($data['seo_title'] ?? '')) ?: null,
            'meta_description' => trim((string)($data['meta_description'] ?? '')) ?: null,
            'canonical_url' => trim((string)($data['canonical_url'] ?? '')) ?: null,
            'robots_index' => !empty($data['robots_index']) ? 1 : 0,
        ];

        $db->beginTransaction();
        try {
            if ($id) {
                $params['id']=$id;
                $stmt=$db->prepare(
                    "UPDATE content SET category_id=:category_id,author_id=:author_id,title=:title,slug=:slug,excerpt=:excerpt,body=:body,
                     featured_image_url=:featured_image_url,status=:status,published_at=:published_at,seo_title=:seo_title,
                     meta_description=:meta_description,canonical_url=:canonical_url,robots_index=:robots_index WHERE id=:id AND type='buying_guide'"
                );
                $stmt->execute($params);
            } else {
                $stmt=$db->prepare(
                    "INSERT INTO content (type,category_id,author_id,title,slug,excerpt,body,featured_image_url,status,published_at,seo_title,meta_description,canonical_url,robots_index)
                     VALUES ('buying_guide',:category_id,:author_id,:title,:slug,:excerpt,:body,:featured_image_url,:status,:published_at,:seo_title,:meta_description,:canonical_url,:robots_index)"
                );
                $stmt->execute($params);
                $id=(int)$db->lastInsertId();
            }

            $db->prepare('DELETE FROM content_products WHERE content_id=:id')->execute(['id'=>$id]);
            $productIds=$data['product_id'] ?? [];
            if (!is_array($productIds)) $productIds=[];
            $rank=$data['rank_position'] ?? [];
            $score=$data['score'] ?? [];
            $bestFor=$data['product_best_for'] ?? [];
            $recommendation=$data['recommendation'] ?? [];
            $cta=$data['cta_text'] ?? [];
            $insert=$db->prepare(
                'INSERT INTO content_products (content_id,product_id,rank_position,score,best_for_label,recommendation,cta_text,sort_order)
                 VALUES (:content_id,:product_id,:rank_position,:score,:best_for_label,:recommendation,:cta_text,:sort_order)'
            );
            $seen=[];
            foreach($productIds as $i=>$productId){
                $productId=(int)$productId;
                if($productId<1 || isset($seen[$productId])) continue;
                $seen[$productId]=true;
                $insert->execute([
                    'content_id'=>$id,'product_id'=>$productId,
                    'rank_position'=>isset($rank[$i]) && $rank[$i]!=='' ? (int)$rank[$i] : $i+1,
                    'score'=>isset($score[$i]) && $score[$i]!=='' ? (float)$score[$i] : null,
                    'best_for_label'=>trim((string)($bestFor[$i] ?? '')) ?: null,
                    'recommendation'=>trim((string)($recommendation[$i] ?? '')) ?: null,
                    'cta_text'=>trim((string)($cta[$i] ?? '')) ?: null,
                    'sort_order'=>$i,
                ]);
            }
            $db->commit();
            return (int)$id;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
