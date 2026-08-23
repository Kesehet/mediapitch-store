<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Services\ContentVisibility;
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

    public function categoryBySlug(string $slug, ?array $filters = null): ?array
    {
        $db = Database::connection();
        $stmt=$db->prepare('SELECT * FROM categories WHERE slug=:slug AND active=1 LIMIT 1');
        $stmt->execute(['slug'=>$slug]);
        $category=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$category) return null;

        $filters ??= $_GET;
        $page=max(1,(int)($filters['page'] ?? 1));
        $perPage=12;
        $brandId=max(0,(int)($filters['brand'] ?? 0));
        $minPrice=isset($filters['min_price']) && $filters['min_price'] !== '' && is_numeric($filters['min_price']) ? (float)$filters['min_price'] : null;
        $maxPrice=isset($filters['max_price']) && $filters['max_price'] !== '' && is_numeric($filters['max_price']) ? (float)$filters['max_price'] : null;
        $minScore=isset($filters['min_score']) && $filters['min_score'] !== '' && is_numeric($filters['min_score']) ? max(0,min(10,(float)$filters['min_score'])) : null;
        $sort=(string)($filters['sort'] ?? 'score');
        $allowedSorts=[
            'score'=>'p.custom_score DESC,p.updated_at DESC',
            'price_asc'=>'p.price IS NULL,p.price ASC,p.title ASC',
            'price_desc'=>'p.price IS NULL,p.price DESC,p.title ASC',
            'newest'=>'p.updated_at DESC',
            'title'=>'COALESCE(p.display_title,p.title) ASC',
        ];
        if(!isset($allowedSorts[$sort])) $sort='score';

        $definitionsStmt=$db->prepare(
            'SELECT id,name,slug,unit,data_type,options_json,sort_order FROM specification_definitions
             WHERE category_id=:category_id AND filterable=1 ORDER BY sort_order,name'
        );
        $definitionsStmt->execute(['category_id'=>$category['id']]);
        $filterDefinitions=$definitionsStmt->fetchAll(PDO::FETCH_ASSOC);
        $definitionBySlug=[];
        foreach($filterDefinitions as $definition) $definitionBySlug[$definition['slug']]=$definition;

        $where=['p.category_id=:category_id','p.active=1'];
        $params=['category_id'=>(int)$category['id']];
        if($brandId>0){ $where[]='p.brand_id=:brand_id'; $params['brand_id']=$brandId; }
        if($minPrice!==null){ $where[]='p.price>=:min_price'; $params['min_price']=$minPrice; }
        if($maxPrice!==null){ $where[]='p.price<=:max_price'; $params['max_price']=$maxPrice; }
        if($minScore!==null){ $where[]='p.custom_score>=:min_score'; $params['min_score']=$minScore; }

        $activeSpecs=[];
        $submittedSpecs=is_array($filters['spec'] ?? null) ? $filters['spec'] : [];
        $specIndex=0;
        foreach($submittedSpecs as $specSlug=>$raw){
            if(!isset($definitionBySlug[$specSlug]) || $raw==='') continue;
            $definition=$definitionBySlug[$specSlug];
            $value=is_scalar($raw) ? trim((string)$raw) : '';
            if($value==='') continue;
            $prefix='spec_' . $specIndex++;
            $params[$prefix . '_id']=(int)$definition['id'];
            $condition='';
            if($definition['data_type']==='number' && is_numeric($value)){
                $condition='ps.value_number=:' . $prefix . '_value';
                $params[$prefix . '_value']=(float)$value;
            }elseif($definition['data_type']==='boolean' && in_array($value,['0','1'],true)){
                $condition='ps.value_boolean=:' . $prefix . '_value';
                $params[$prefix . '_value']=(int)$value;
            }elseif(in_array($definition['data_type'],['text','select'],true)){
                if($definition['data_type']==='select'){
                    $options=json_decode((string)$definition['options_json'],true);
                    if(!is_array($options) || !in_array($value,$options,true)) continue;
                }
                $condition='ps.value_text=:' . $prefix . '_value';
                $params[$prefix . '_value']=$value;
            }
            if($condition==='') continue;
            $where[]='EXISTS (SELECT 1 FROM product_specifications ps WHERE ps.product_id=p.id AND ps.specification_definition_id=:' . $prefix . '_id AND ' . $condition . ')';
            $activeSpecs[$specSlug]=$value;
        }

        $whereSql=implode(' AND ',$where);
        $count=$db->prepare('SELECT COUNT(*) FROM products p WHERE ' . $whereSql);
        foreach($params as $key=>$value) $count->bindValue(':' . $key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $count->execute();
        $total=(int)$count->fetchColumn();
        $pages=max(1,(int)ceil($total/$perPage));
        $page=min($page,$pages);
        $offset=($page-1)*$perPage;

        $products=$db->prepare(
            'SELECT p.id,p.title,p.display_title,p.slug,p.main_image_url,p.price,p.currency,p.custom_score,p.best_for_label,b.name AS brand_name
             FROM products p LEFT JOIN brands b ON b.id=p.brand_id
             WHERE ' . $whereSql . ' ORDER BY ' . $allowedSorts[$sort] . ' LIMIT :limit OFFSET :offset'
        );
        foreach($params as $key=>$value) $products->bindValue(':' . $key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $products->bindValue(':limit',$perPage,PDO::PARAM_INT);
        $products->bindValue(':offset',$offset,PDO::PARAM_INT);
        $products->execute();
        $category['products']=$products->fetchAll(PDO::FETCH_ASSOC);

        $brands=$db->prepare(
            'SELECT DISTINCT b.id,b.name FROM brands b JOIN products p ON p.brand_id=b.id
             WHERE p.category_id=:category_id AND p.active=1 ORDER BY b.name'
        );
        $brands->execute(['category_id'=>$category['id']]);

        $category['filter_options']=[
            'brands'=>$brands->fetchAll(PDO::FETCH_ASSOC),
            'specifications'=>$filterDefinitions,
        ];
        $category['active_filters']=[
            'brand'=>$brandId,
            'min_price'=>$minPrice,
            'max_price'=>$maxPrice,
            'min_score'=>$minScore,
            'sort'=>$sort,
            'spec'=>$activeSpecs,
        ];
        $category['pagination']=['page'=>$page,'pages'=>$pages,'total'=>$total,'per_page'=>$perPage];

        $visibility=ContentVisibility::sql('');
        $content=$db->prepare(
            "SELECT id,type,title,slug,excerpt,featured_image_url,published_at
             FROM content WHERE category_id=:category_id AND $visibility
             ORDER BY COALESCE(published_at,created_at) DESC"
        );
        $content->execute(['category_id'=>$category['id']]);
        $category['guides']=[];
        $category['articles']=[];
        foreach($content->fetchAll(PDO::FETCH_ASSOC) as $item){
            if($item['type']==='buying_guide') $category['guides'][]=$item;
            elseif($item['type']==='blog') $category['articles'][]=$item;
        }
        return $category;
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
        $visibility=ContentVisibility::sql('');
        $stmt = Database::connection()->prepare(
            "SELECT id, title, slug, excerpt, featured_image_url, published_at FROM content
             WHERE type = :type AND $visibility
             ORDER BY COALESCE(published_at, created_at) DESC LIMIT :limit"
        );
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function productBySlug(string $slug): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT p.*, b.name AS brand_name, c.name AS category_name, c.slug AS category_slug
             FROM products p
             LEFT JOIN brands b ON b.id = p.brand_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.slug = :slug AND p.active = 1 LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $specs = $db->prepare(
            'SELECT sd.name,sd.slug,sd.unit,sd.data_type,sd.sort_order,
                    ps.value_text,ps.value_number,ps.value_boolean
             FROM product_specifications ps
             JOIN specification_definitions sd ON sd.id=ps.specification_definition_id
             WHERE ps.product_id=:product_id
             ORDER BY sd.sort_order,sd.name'
        );
        $specs->execute(['product_id'=>$row['id']]);
        $row['specifications'] = $specs->fetchAll(PDO::FETCH_ASSOC);
        return $row;
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
        $visibility=ContentVisibility::sql('');
        $stmt = Database::connection()->prepare(
            "SELECT * FROM content WHERE slug = :slug AND type = 'buying_guide' AND $visibility LIMIT 1"
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
             WHERE active = 1 AND (title LIKE :q1 OR display_title LIKE :q2 OR short_description LIKE :q3)
             ORDER BY custom_score DESC, title ASC LIMIT :limit'
        );
        $stmt->bindValue(':q1', $like);
        $stmt->bindValue(':q2', $like);
        $stmt->bindValue(':q3', $like);
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
            'referrer'=>$referrer,
            'user_agent'=>$userAgent,
            'campaign'=>$campaign,
        ]);
    }
}
