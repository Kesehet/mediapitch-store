<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Core\Database;
use PDO;
use RuntimeException;

final class ProductAdminActions
{
    public function archive(int $productId): void
    {
        $stmt=Database::connection()->prepare('UPDATE products SET active=0 WHERE id=:id');
        $stmt->execute(['id'=>$productId]);
        if($stmt->rowCount()<1){
            $exists=Database::connection()->prepare('SELECT id FROM products WHERE id=:id LIMIT 1');
            $exists->execute(['id'=>$productId]);
            if(!$exists->fetchColumn()) throw new RuntimeException('Product not found.');
        }
    }

    public function restore(int $productId): void
    {
        $stmt=Database::connection()->prepare('UPDATE products SET active=1 WHERE id=:id');
        $stmt->execute(['id'=>$productId]);
        if($stmt->rowCount()<1){
            $exists=Database::connection()->prepare('SELECT id FROM products WHERE id=:id LIMIT 1');
            $exists->execute(['id'=>$productId]);
            if(!$exists->fetchColumn()) throw new RuntimeException('Product not found.');
        }
    }

    public function duplicate(int $productId): int
    {
        $db=Database::connection();
        $stmt=$db->prepare('SELECT * FROM products WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$productId]);
        $source=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$source) throw new RuntimeException('Product not found.');

        $baseSlug=(string)$source['slug'].'-copy';
        $slug=$baseSlug;
        for($i=2;$this->slugExists($slug);$i++) $slug=$baseSlug.'-'.$i;

        $db->beginTransaction();
        try{
            $columns=[
                'category_id','brand_id','title','display_title','slug','source','short_description','full_description',
                'main_image_url','gallery_json','features_json','pros_json','cons_json','price','previous_price','currency',
                'custom_score','best_for_label','editorial_notes','active'
            ];
            $params=[];
            foreach($columns as $column) $params[$column]=$source[$column]??null;
            $params['title']=(string)$source['title'].' (Copy)';
            $params['display_title']=!empty($source['display_title'])?(string)$source['display_title'].' (Copy)':null;
            $params['slug']=$slug;
            $params['source']='manual';
            $params['active']=0;

            $sql='INSERT INTO products ('.implode(',',$columns).') VALUES ('.implode(',',array_map(static fn(string $c):string=>':'.$c,$columns)).')';
            $insert=$db->prepare($sql);
            $insert->execute($params);
            $newId=(int)$db->lastInsertId();

            $copySpecs=$db->prepare(
                'INSERT INTO product_specifications (product_id,specification_definition_id,value_text,value_number,value_boolean)
                 SELECT :new_id,specification_definition_id,value_text,value_number,value_boolean
                 FROM product_specifications WHERE product_id=:source_id'
            );
            $copySpecs->execute(['new_id'=>$newId,'source_id'=>$productId]);
            $db->commit();
            return $newId;
        }catch(\Throwable $e){
            if($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function slugExists(string $slug): bool
    {
        $stmt=Database::connection()->prepare('SELECT 1 FROM products WHERE slug=:slug LIMIT 1');
        $stmt->execute(['slug'=>$slug]);
        return (bool)$stmt->fetchColumn();
    }
}
