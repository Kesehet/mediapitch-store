<?php

declare(strict_types=1);

namespace MediaPitch\Amazon;

use MediaPitch\Core\Database;
use PDO;
use RuntimeException;

final class AmazonProductImporter
{
    public function import(array $item,array $settings,?int $categoryId=null): int
    {
        $asin=strtoupper(trim((string)($item['asin']??'')));
        if(!preg_match('/^[A-Z0-9]{10}$/',$asin))throw new RuntimeException('Amazon result does not contain a valid ASIN.');
        $title=trim((string)($item['itemInfo']['title']['displayValue']??''));
        if($title==='')throw new RuntimeException('Amazon result does not contain a product title.');

        $detailUrl=trim((string)($item['detailPageURL']??''))?:null;
        $image=$this->image($item);
        $features=$this->features($item);
        [$price,$currency]=$this->price($item);
        $marketplace=trim((string)($settings['marketplace']??''))?:null;
        $db=Database::connection();

        $stmt=$db->prepare('SELECT * FROM products WHERE asin=:asin LIMIT 1');
        $stmt->execute(['asin'=>$asin]);
        $existing=$stmt->fetch(PDO::FETCH_ASSOC);
        if($existing){
            $hybrid=in_array((string)$existing['source'],['manual','hybrid'],true);
            $update=$db->prepare(
                'UPDATE products SET title=:title,source=:source,api_marketplace=:marketplace,
                 main_image_url=:image,features_json=:features,price=:price,currency=:currency,
                 amazon_url=:amazon_url,affiliate_url=:affiliate_url,last_synced_at=UTC_TIMESTAMP(),
                 category_id=COALESCE(:category_id,category_id)
                 WHERE id=:id'
            );
            $update->execute([
                'title'=>$title,
                'source'=>$hybrid?'hybrid':'amazon_api',
                'marketplace'=>$marketplace,
                'image'=>$hybrid&&!empty($existing['main_image_url'])?$existing['main_image_url']:$image,
                'features'=>$hybrid&&!empty($existing['features_json'])?$existing['features_json']:$features,
                'price'=>$price,'currency'=>$currency?:($existing['currency']?:'INR'),
                'amazon_url'=>$detailUrl,'affiliate_url'=>$detailUrl,'category_id'=>$categoryId,'id'=>(int)$existing['id'],
            ]);
            return (int)$existing['id'];
        }

        $slug=$this->uniqueSlug($title,$asin);
        $insert=$db->prepare(
            "INSERT INTO products (category_id,asin,title,slug,source,api_marketplace,main_image_url,features_json,price,currency,amazon_url,affiliate_url,last_synced_at,active)
             VALUES (:category_id,:asin,:title,:slug,'amazon_api',:marketplace,:image,:features,:price,:currency,:amazon_url,:affiliate_url,UTC_TIMESTAMP(),0)"
        );
        $insert->execute([
            'category_id'=>$categoryId,'asin'=>$asin,'title'=>$title,'slug'=>$slug,'marketplace'=>$marketplace,'image'=>$image,
            'features'=>$features,'price'=>$price,'currency'=>$currency?:'INR','amazon_url'=>$detailUrl,'affiliate_url'=>$detailUrl,
        ]);
        return (int)$db->lastInsertId();
    }

    public function normalizeForPreview(array $item): array
    {
        [$price,$currency,$displayPrice]=$this->price($item,true);
        return [
            'asin'=>(string)($item['asin']??''),
            'title'=>(string)($item['itemInfo']['title']['displayValue']??'Untitled Amazon product'),
            'image'=>$this->image($item),
            'features'=>json_decode((string)$this->features($item),true)?:[],
            'price'=>$price,'currency'=>$currency,'display_price'=>$displayPrice,
            'detail_url'=>(string)($item['detailPageURL']??''),
            'availability'=>(string)($item['offersV2']['listings'][0]['availability']['message']??''),
        ];
    }

    private function image(array $item): ?string
    {
        foreach(['large','medium','small'] as $size){$url=trim((string)($item['images']['primary'][$size]['url']??''));if($url!=='')return $url;}
        return null;
    }

    private function features(array $item): ?string
    {
        $values=$item['itemInfo']['features']['displayValues']??[];
        if(!is_array($values))return null;
        $values=array_values(array_filter(array_map(static fn($v)=>trim((string)$v),$values)));
        return $values?json_encode($values,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):null;
    }

    private function price(array $item,bool $withDisplay=false): array
    {
        $money=$item['offersV2']['listings'][0]['price']['money']??[];
        $amount=isset($money['amount'])&&is_numeric($money['amount'])?(float)$money['amount']:null;
        $currency=trim((string)($money['currency']??''))?:null;
        $display=trim((string)($money['displayAmount']??''))?:null;
        return $withDisplay?[$amount,$currency,$display]:[$amount,$currency];
    }

    private function uniqueSlug(string $title,string $asin): string
    {
        $value=$title;
        if(function_exists('iconv')){$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);if(is_string($ascii)&&$ascii!=='')$value=$ascii;}
        $base=trim(preg_replace('/[^a-z0-9]+/','-',strtolower($value))??'','-');
        if($base==='')$base='amazon-product';
        $base=substr($base,0,220);
        $candidate=$base;
        $db=Database::connection();$n=1;
        while(true){
            $stmt=$db->prepare('SELECT 1 FROM products WHERE slug=:slug LIMIT 1');$stmt->execute(['slug'=>$candidate]);
            if(!$stmt->fetchColumn())return $candidate;
            $candidate=substr($base,0,205).'-'.strtolower($asin).($n>1?'-'.$n:'');$n++;
        }
    }
}
