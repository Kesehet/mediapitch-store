<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use PDO;
use RuntimeException;

final class ProductAuthoring
{
    private static mixed $pendingOverrides = null;

    public function prepare(array $data, ?int $productId = null): array
    {
        $title=trim((string)($data['title']??''));
        if($title==='') throw new InvalidArgumentException('Product title is required.');

        $slug=$this->slugify((string)($data['slug']??''));
        if($slug==='') $slug=$this->slugify($title);
        if($slug==='') throw new InvalidArgumentException('Could not generate a valid product slug.');

        $db=Database::connection();
        $currentMarketplace='';
        if($productId){
            $marketStmt=$db->prepare('SELECT api_marketplace FROM products WHERE id=:id LIMIT 1');
            $marketStmt->execute(['id'=>$productId]);
            $currentMarketplace=strtolower(trim((string)($marketStmt->fetchColumn()?:'')));
        }

        $sql='SELECT id,title FROM products WHERE slug=:slug';
        $params=['slug'=>$slug];
        if($productId){$sql.=' AND id<>:id';$params['id']=$productId;}
        $sql.=' LIMIT 1';
        $stmt=$db->prepare($sql);$stmt->execute($params);
        $duplicate=$stmt->fetch(PDO::FETCH_ASSOC);
        if($duplicate)throw new InvalidArgumentException('The slug “'.$slug.'” is already used by “'.$duplicate['title'].'”. Choose a different slug.');

        $asin=strtoupper(trim((string)($data['asin']??'')));
        if($asin!==''){
            if(!preg_match('/^[A-Z0-9]{10}$/',$asin))throw new InvalidArgumentException('ASIN must contain exactly 10 letters/numbers.');
            $params=['asin'=>$asin];
            if($currentMarketplace!==''){
                $sql="SELECT id,title FROM products WHERE asin=:asin AND (api_marketplace=:marketplace OR api_marketplace IS NULL OR api_marketplace='')";
                $params['marketplace']=$currentMarketplace;
            }else{
                $sql='SELECT id,title FROM products WHERE asin=:asin';
            }
            if($productId){$sql.=' AND id<>:id';$params['id']=$productId;}
            $sql.=' LIMIT 1';
            $stmt=$db->prepare($sql);$stmt->execute($params);
            $duplicate=$stmt->fetch(PDO::FETCH_ASSOC);
            if($duplicate){
                $suffix=$currentMarketplace!==''?' in '.$currentMarketplace:'';
                throw new InvalidArgumentException('ASIN '.$asin.' is already assigned to “'.$duplicate['title'].'”'.$suffix.'.');
            }
            $data['asin']=$asin;
        }

        self::$pendingOverrides=$data['amazon_override']??[];
        $data['title']=$title;$data['slug']=$slug;return $data;
    }

    public static function persistPendingOverrides(int $productId): void
    {
        if(self::$pendingOverrides===null)return;
        $submitted=self::$pendingOverrides;
        self::$pendingOverrides=null;
        (new ProductOverrides())->save($productId,$submitted);
    }

    public function archive(int $productId): void
    {
        $stmt=Database::connection()->prepare('UPDATE products SET active=0 WHERE id=:id');$stmt->execute(['id'=>$productId]);if($stmt->rowCount()===0)throw new RuntimeException('Product not found or already archived.');
    }

    public function duplicate(int $productId): int
    {
        $db=Database::connection();$stmt=$db->prepare('SELECT * FROM products WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$productId]);$source=$stmt->fetch(PDO::FETCH_ASSOC);if(!$source)throw new RuntimeException('Product not found.');
        $baseSlug=$this->slugify((string)$source['slug'].'-copy');$slug=$baseSlug;$suffix=2;$check=$db->prepare('SELECT COUNT(*) FROM products WHERE slug=:slug');while(true){$check->execute(['slug'=>$slug]);if((int)$check->fetchColumn()===0)break;$slug=$baseSlug.'-'.$suffix++;}
        $db->beginTransaction();
        try{
            $insert=$db->prepare('INSERT INTO products (category_id,brand_id,asin,title,display_title,slug,source,api_marketplace,short_description,full_description,main_image_url,gallery_json,features_json,pros_json,cons_json,price,previous_price,currency,amazon_url,affiliate_url,custom_score,best_for_label,editorial_notes,manual_override_json,last_synced_at,active) VALUES (:category_id,:brand_id,NULL,:title,:display_title,:slug,\'manual\',NULL,:short_description,:full_description,:main_image_url,:gallery_json,:features_json,:pros_json,:cons_json,:price,:previous_price,:currency,NULL,NULL,:custom_score,:best_for_label,:editorial_notes,NULL,NULL,0)');
            $insert->execute(['category_id'=>$source['category_id'],'brand_id'=>$source['brand_id'],'title'=>'Copy of '.$source['title'],'display_title'=>$source['display_title']?'Copy of '.$source['display_title']:null,'slug'=>$slug,'short_description'=>$source['short_description'],'full_description'=>$source['full_description'],'main_image_url'=>$source['main_image_url'],'gallery_json'=>$source['gallery_json'],'features_json'=>$source['features_json'],'pros_json'=>$source['pros_json'],'cons_json'=>$source['cons_json'],'price'=>$source['price'],'previous_price'=>$source['previous_price'],'currency'=>$source['currency'],'custom_score'=>$source['custom_score'],'best_for_label'=>$source['best_for_label'],'editorial_notes'=>$source['editorial_notes']]);
            $newId=(int)$db->lastInsertId();$copySpecs=$db->prepare('INSERT INTO product_specifications (product_id,specification_definition_id,value_text,value_number,value_boolean) SELECT :new_id,specification_definition_id,value_text,value_number,value_boolean FROM product_specifications WHERE product_id=:source_id');$copySpecs->execute(['new_id'=>$newId,'source_id'=>$productId]);$db->commit();return $newId;
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    private function slugify(string $value): string
    {
        $value=trim($value);if($value==='')return '';if(function_exists('iconv')){$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);if(is_string($ascii)&&$ascii!=='')$value=$ascii;}$value=strtolower($value);$value=preg_replace('/[^a-z0-9]+/','-',$value)??'';return trim($value,'-');
    }
}
