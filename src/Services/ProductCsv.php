<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use PDO;
use RuntimeException;

final class ProductCsv
{
    public const HEADERS=['id','title','display_title','slug','source','asin','category_id','brand_id','short_description','price','currency','custom_score','best_for_label','amazon_url','affiliate_url','main_image_url','active'];

    public function exportRows(): array
    {
        return Database::connection()->query(
            'SELECT id,title,display_title,slug,source,asin,category_id,brand_id,short_description,price,currency,custom_score,best_for_label,amazon_url,affiliate_url,main_image_url,active FROM products ORDER BY id'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function importFile(string $path): array
    {
        if(!is_file($path))throw new InvalidArgumentException('Choose a CSV file to import.');
        $handle=fopen($path,'rb');
        if($handle===false)throw new RuntimeException('CSV file could not be opened.');
        try{
            $headers=fgetcsv($handle);
            if(!is_array($headers))throw new InvalidArgumentException('CSV file is empty.');
            $headers=array_map(static fn($v)=>strtolower(trim((string)$v)),$headers);
            foreach(['title','slug'] as $required)if(!in_array($required,$headers,true))throw new InvalidArgumentException("CSV requires a {$required} column.");
            $created=0;$updated=0;$errors=[];$line=1;
            while(($values=fgetcsv($handle))!==false){
                $line++;
                if(count(array_filter($values,static fn($v)=>trim((string)$v)!==''))===0)continue;
                $row=[];foreach($headers as $i=>$header)$row[$header]=$values[$i]??'';
                try{$result=$this->importRow($row);$result==='created'?$created++:$updated++;}
                catch(\Throwable $e){$errors[]='Line '.$line.': '.substr($e->getMessage(),0,250);}
                if($line>1001){$errors[]='Import stopped after 1000 data rows.';break;}
            }
            return ['created'=>$created,'updated'=>$updated,'errors'=>$errors];
        }finally{fclose($handle);}
    }

    private function importRow(array $row): string
    {
        $db=Database::connection();
        $id=(int)($row['id']??0);
        $title=trim((string)($row['title']??''));
        $slug=trim((string)($row['slug']??''));
        if($title===''||$slug==='')throw new InvalidArgumentException('Title and slug are required.');
        if(!preg_match('/^[a-z0-9-]+$/',$slug))throw new InvalidArgumentException('Slug must contain lowercase letters, numbers and hyphens only.');
        $source=in_array(($row['source']??'manual'),['manual','amazon_api','hybrid'],true)?(string)$row['source']:'manual';
        $asin=strtoupper(trim((string)($row['asin']??'')));
        if($asin!==''&&!preg_match('/^[A-Z0-9]{10}$/',$asin))throw new InvalidArgumentException('ASIN must be 10 letters/numbers.');
        $categoryId=$this->nullableId($row['category_id']??null,'categories');
        $brandId=$this->nullableId($row['brand_id']??null,'brands');
        $active=$this->boolValue($row['active']??'0');

        $conflict=$db->prepare('SELECT id FROM products WHERE slug=:slug AND id<>:id LIMIT 1');$conflict->execute(['slug'=>$slug,'id'=>$id]);if($conflict->fetchColumn())throw new InvalidArgumentException('Slug already belongs to another product.');
        if($asin!==''){$conflict=$db->prepare('SELECT id FROM products WHERE asin=:asin AND id<>:id LIMIT 1');$conflict->execute(['asin'=>$asin,'id'=>$id]);if($conflict->fetchColumn())throw new InvalidArgumentException('ASIN already belongs to another product.');}

        $params=[
            'title'=>$title,'display_title'=>trim((string)($row['display_title']??''))?:null,'slug'=>$slug,'source'=>$source,'asin'=>$asin?:null,
            'category_id'=>$categoryId,'brand_id'=>$brandId,'short_description'=>trim((string)($row['short_description']??''))?:null,
            'price'=>$this->decimal($row['price']??null),'currency'=>strtoupper(substr(trim((string)($row['currency']??'INR')),0,3))?:'INR',
            'custom_score'=>$this->decimal($row['custom_score']??null),'best_for_label'=>trim((string)($row['best_for_label']??''))?:null,
            'amazon_url'=>$this->urlValue($row['amazon_url']??null),'affiliate_url'=>$this->urlValue($row['affiliate_url']??null),'main_image_url'=>$this->urlValue($row['main_image_url']??null),'active'=>$active,
        ];

        $exists=false;
        if($id>0){$stmt=$db->prepare('SELECT 1 FROM products WHERE id=:id');$stmt->execute(['id'=>$id]);$exists=(bool)$stmt->fetchColumn();}
        if($exists){
            $params['id']=$id;
            $db->prepare('UPDATE products SET title=:title,display_title=:display_title,slug=:slug,source=:source,asin=:asin,category_id=:category_id,brand_id=:brand_id,short_description=:short_description,price=:price,currency=:currency,custom_score=:custom_score,best_for_label=:best_for_label,amazon_url=:amazon_url,affiliate_url=:affiliate_url,main_image_url=:main_image_url,active=:active WHERE id=:id')->execute($params);
            return 'updated';
        }
        $db->prepare('INSERT INTO products (title,display_title,slug,source,asin,category_id,brand_id,short_description,price,currency,custom_score,best_for_label,amazon_url,affiliate_url,main_image_url,active) VALUES (:title,:display_title,:slug,:source,:asin,:category_id,:brand_id,:short_description,:price,:currency,:custom_score,:best_for_label,:amazon_url,:affiliate_url,:main_image_url,:active)')->execute($params);
        return 'created';
    }

    private function nullableId(mixed $value,string $table): ?int
    {
        $id=(int)$value;if($id<1)return null;
        $allowed=['categories','brands'];if(!in_array($table,$allowed,true))return null;
        $stmt=Database::connection()->prepare("SELECT 1 FROM {$table} WHERE id=:id LIMIT 1");$stmt->execute(['id'=>$id]);
        if(!$stmt->fetchColumn())throw new InvalidArgumentException(ucfirst(rtrim($table,'s')).' ID '.$id.' does not exist.');
        return $id;
    }
    private function decimal(mixed $value): ?float{$value=trim((string)$value);if($value==='')return null;if(!is_numeric($value))throw new InvalidArgumentException('Numeric value is invalid.');return (float)$value;}
    private function urlValue(mixed $value): ?string{$value=trim((string)$value);if($value==='')return null;if(!filter_var($value,FILTER_VALIDATE_URL))throw new InvalidArgumentException('URL value is invalid.');return $value;}
    private function boolValue(mixed $value): int{return in_array(strtolower(trim((string)$value)),['1','true','yes','active'],true)?1:0;}
}
