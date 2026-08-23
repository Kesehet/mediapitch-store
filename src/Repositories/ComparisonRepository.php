<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use MediaPitch\Services\ContentVisibility;
use PDO;
use RuntimeException;

final class ComparisonRepository
{
    public function adminList(): array
    {
        return Database::connection()->query(
            "SELECT c.id,c.title,c.slug,c.status,c.updated_at,cat.name AS category_name,COUNT(cp.id) AS product_count
             FROM content c
             LEFT JOIN categories cat ON cat.id=c.category_id
             LEFT JOIN content_products cp ON cp.content_id=c.id
             WHERE c.type='comparison'
             GROUP BY c.id,c.title,c.slug,c.status,c.updated_at,cat.name
             ORDER BY c.updated_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function adminComparison(?int $id): ?array
    {
        if (!$id) return null;
        $db=Database::connection();
        $stmt=$db->prepare("SELECT * FROM content WHERE id=:id AND type='comparison' LIMIT 1");
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) return null;
        $stmt=$db->prepare(
            'SELECT cp.product_id,cp.sort_order,COALESCE(p.display_title,p.title) AS product_title
             FROM content_products cp JOIN products p ON p.id=cp.product_id
             WHERE cp.content_id=:id ORDER BY cp.sort_order,cp.id'
        );
        $stmt->execute(['id'=>$id]);
        $row['products']=$stmt->fetchAll(PDO::FETCH_ASSOC);
        return $row;
    }

    public function save(array $data,int $authorId,?int $id=null): int
    {
        $db=Database::connection();
        $status=in_array(($data['status']??'draft'),['draft','scheduled','published'],true)?(string)$data['status']:'draft';
        $publishedAt=ContentVisibility::publishAtFromInput($data['published_at'] ?? null);
        if($status==='published'&&!$publishedAt)$publishedAt=gmdate('Y-m-d H:i:s');
        if($status==='scheduled'&&!$publishedAt)$status='draft';

        $productIds=array_values(array_unique(array_filter(array_map('intval',$data['product_id']??[]),static fn(int $v):bool=>$v>0)));
        if(count($productIds)<2){
            throw new RuntimeException('Select at least two products for a comparison.');
        }

        $params=[
            'category_id'=>!empty($data['category_id'])?(int)$data['category_id']:null,
            'author_id'=>$authorId,
            'title'=>trim((string)($data['title']??'')),
            'slug'=>trim((string)($data['slug']??'')),
            'excerpt'=>trim((string)($data['excerpt']??''))?:null,
            'body'=>trim((string)($data['body']??''))?:null,
            'featured_image_url'=>trim((string)($data['featured_image_url']??''))?:null,
            'seo_title'=>trim((string)($data['seo_title']??''))?:null,
            'meta_description'=>trim((string)($data['meta_description']??''))?:null,
            'canonical_url'=>trim((string)($data['canonical_url']??''))?:null,
            'robots_index'=>!empty($data['robots_index'])?1:0,
            'status'=>$status,
            'published_at'=>$publishedAt,
        ];

        $db->beginTransaction();
        try{
            if($id){
                $params['id']=$id;
                $stmt=$db->prepare(
                    "UPDATE content SET category_id=:category_id,author_id=:author_id,title=:title,slug=:slug,excerpt=:excerpt,body=:body,
                     featured_image_url=:featured_image_url,seo_title=:seo_title,meta_description=:meta_description,canonical_url=:canonical_url,
                     robots_index=:robots_index,status=:status,published_at=:published_at WHERE id=:id AND type='comparison'"
                );
                $stmt->execute($params);
            }else{
                $stmt=$db->prepare(
                    "INSERT INTO content (type,category_id,author_id,title,slug,excerpt,body,featured_image_url,seo_title,meta_description,canonical_url,robots_index,status,published_at)
                     VALUES ('comparison',:category_id,:author_id,:title,:slug,:excerpt,:body,:featured_image_url,:seo_title,:meta_description,:canonical_url,:robots_index,:status,:published_at)"
                );
                $stmt->execute($params);
                $id=(int)$db->lastInsertId();
            }

            $db->prepare('DELETE FROM content_products WHERE content_id=:id')->execute(['id'=>$id]);
            $insert=$db->prepare('INSERT INTO content_products (content_id,product_id,sort_order) VALUES (:content_id,:product_id,:sort_order)');
            foreach($productIds as $i=>$productId){
                $insert->execute(['content_id'=>$id,'product_id'=>$productId,'sort_order'=>$i]);
            }
            $db->commit();
            return (int)$id;
        }catch(\Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
    }

    public function publishedBySlug(string $slug): ?array
    {
        $db=Database::connection();
        $visibility=ContentVisibility::sql('c');
        $stmt=$db->prepare(
            "SELECT c.*,cat.name AS category_name,cat.slug AS category_slug,u.name AS author_name
             FROM content c
             LEFT JOIN categories cat ON cat.id=c.category_id
             LEFT JOIN users u ON u.id=c.author_id
             WHERE c.slug=:slug AND c.type='comparison' AND $visibility LIMIT 1"
        );
        $stmt->execute(['slug'=>$slug]);
        $comparison=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$comparison)return null;

        $stmt=$db->prepare(
            'SELECT p.id,p.title,p.display_title,p.slug,p.main_image_url,p.price,p.currency,p.custom_score,p.best_for_label,p.affiliate_url,p.category_id,b.name AS brand_name
             FROM content_products cp
             JOIN products p ON p.id=cp.product_id AND p.active=1
             LEFT JOIN brands b ON b.id=p.brand_id
             WHERE cp.content_id=:id ORDER BY cp.sort_order,cp.id'
        );
        $stmt->execute(['id'=>$comparison['id']]);
        $products=$stmt->fetchAll(PDO::FETCH_ASSOC);
        if(count($products)<2)return null;

        $ids=array_map(static fn(array $p):int=>(int)$p['id'],$products);
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $specStmt=$db->prepare(
            "SELECT ps.product_id,sd.id AS definition_id,sd.name,sd.unit,sd.data_type,sd.sort_order,
                    ps.value_text,ps.value_number,ps.value_boolean
             FROM product_specifications ps
             JOIN specification_definitions sd ON sd.id=ps.specification_definition_id
             WHERE ps.product_id IN ($placeholders) AND sd.comparable=1
             ORDER BY sd.sort_order,sd.name"
        );
        $specStmt->execute($ids);
        $definitions=[];
        foreach($specStmt->fetchAll(PDO::FETCH_ASSOC) as $row){
            $defId=(int)$row['definition_id'];
            if(!isset($definitions[$defId])){
                $definitions[$defId]=['id'=>$defId,'name'=>$row['name'],'unit'=>$row['unit'],'values'=>[]];
            }
            $value=match($row['data_type']){
                'number'=>$row['value_number']!==null?rtrim(rtrim((string)$row['value_number'],'0'),'.'):null,
                'boolean'=>$row['value_boolean']===null?null:((int)$row['value_boolean']===1?'Yes':'No'),
                default=>$row['value_text'],
            };
            $definitions[$defId]['values'][(int)$row['product_id']]=$value;
        }
        $comparison['products']=$products;
        $comparison['specifications']=array_values($definitions);
        return $comparison;
    }
}
