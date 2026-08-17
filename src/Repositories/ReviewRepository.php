<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class ReviewRepository
{
    public function adminList(): array
    {
        return Database::connection()->query(
            "SELECT c.id,c.title,c.slug,c.status,c.updated_at,COALESCE(p.display_title,p.title) AS product_title,cp.score
             FROM content c LEFT JOIN content_products cp ON cp.content_id=c.id
             LEFT JOIN products p ON p.id=cp.product_id
             WHERE c.type='review' ORDER BY c.updated_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminReview(?int $id): ?array
    {
        if (!$id) return null;
        $stmt=Database::connection()->prepare(
            "SELECT c.*,cp.product_id,cp.score FROM content c LEFT JOIN content_products cp ON cp.content_id=c.id
             WHERE c.id=:id AND c.type='review' LIMIT 1"
        );
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function save(array $data, int $authorId, ?int $id=null): int
    {
        $db=Database::connection();
        $productId=(int)($data['product_id'] ?? 0);
        if($productId<1) throw new \InvalidArgumentException('Choose a product to review.');
        $score=($data['score'] ?? '')!=='' ? (float)$data['score'] : null;
        if($score!==null && ($score<0 || $score>10)) throw new \InvalidArgumentException('Review score must be between 0 and 10.');
        $status=in_array(($data['status']??'draft'),['draft','scheduled','published'],true)?(string)$data['status']:'draft';
        $publishedAt=!empty($data['published_at'])?date('Y-m-d H:i:s',strtotime((string)$data['published_at'])):null;
        if($status==='published'&&!$publishedAt)$publishedAt=gmdate('Y-m-d H:i:s');
        if($status==='scheduled'&&!$publishedAt)$status='draft';
        $params=[
            'category_id'=>!empty($data['category_id'])?(int)$data['category_id']:null,
            'author_id'=>$authorId,'title'=>trim((string)($data['title']??'')),'slug'=>trim((string)($data['slug']??'')),
            'excerpt'=>trim((string)($data['excerpt']??''))?:null,'body'=>trim((string)($data['body']??''))?:null,
            'featured_image_url'=>trim((string)($data['featured_image_url']??''))?:null,
            'seo_title'=>trim((string)($data['seo_title']??''))?:null,'meta_description'=>trim((string)($data['meta_description']??''))?:null,
            'canonical_url'=>trim((string)($data['canonical_url']??''))?:null,'robots_index'=>!empty($data['robots_index'])?1:0,
            'status'=>$status,'published_at'=>$publishedAt,
        ];
        if($params['title']===''||$params['slug']==='')throw new \InvalidArgumentException('Title and slug are required.');

        $db->beginTransaction();
        try{
            if($id){
                $params['id']=$id;
                $stmt=$db->prepare('UPDATE content SET category_id=:category_id,author_id=:author_id,title=:title,slug=:slug,excerpt=:excerpt,body=:body,featured_image_url=:featured_image_url,seo_title=:seo_title,meta_description=:meta_description,canonical_url=:canonical_url,robots_index=:robots_index,status=:status,published_at=:published_at WHERE id=:id AND type=\'review\'');
                $stmt->execute($params);
            }else{
                $stmt=$db->prepare("INSERT INTO content (type,category_id,author_id,title,slug,excerpt,body,featured_image_url,seo_title,meta_description,canonical_url,robots_index,status,published_at) VALUES ('review',:category_id,:author_id,:title,:slug,:excerpt,:body,:featured_image_url,:seo_title,:meta_description,:canonical_url,:robots_index,:status,:published_at)");
                $stmt->execute($params); $id=(int)$db->lastInsertId();
            }
            $db->prepare('DELETE FROM content_products WHERE content_id=:id')->execute(['id'=>$id]);
            $db->prepare('INSERT INTO content_products (content_id,product_id,score,sort_order) VALUES (:content_id,:product_id,:score,0)')->execute(['content_id'=>$id,'product_id'=>$productId,'score'=>$score]);
            $db->commit(); return $id;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public function publishedBySlug(string $slug): ?array
    {
        $db=Database::connection();
        $stmt=$db->prepare(
            "SELECT c.*,cp.score AS review_score,p.id AS product_id,p.category_id AS product_category_id,p.title AS product_title,p.display_title,p.slug AS product_slug,p.main_image_url,p.price,p.currency,p.affiliate_url,b.name AS brand_name,u.name AS author_name
             FROM content c JOIN content_products cp ON cp.content_id=c.id JOIN products p ON p.id=cp.product_id AND p.active=1
             LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN users u ON u.id=c.author_id
             WHERE c.type='review' AND c.slug=:slug AND c.status IN ('published','scheduled') AND c.published_at IS NOT NULL AND c.published_at<=UTC_TIMESTAMP() LIMIT 1"
        );
        $stmt->execute(['slug'=>$slug]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row)return null;
        $categoryId=(int)($row['product_category_id']?:$row['category_id']);
        $row['related_products']=[];
        if($categoryId>0){
            $related=$db->prepare(
                'SELECT p.id,p.title,p.display_title,p.slug,p.main_image_url,p.price,p.currency,p.custom_score,p.best_for_label,b.name AS brand_name
                 FROM products p LEFT JOIN brands b ON b.id=p.brand_id
                 WHERE p.active=1 AND p.category_id=:category_id AND p.id<>:product_id
                 ORDER BY p.custom_score IS NULL,p.custom_score DESC,p.updated_at DESC LIMIT 4'
            );
            $related->execute(['category_id'=>$categoryId,'product_id'=>(int)$row['product_id']]);
            $row['related_products']=$related->fetchAll(PDO::FETCH_ASSOC);
        }
        return $row;
    }
}
