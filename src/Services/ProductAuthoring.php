<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use PDO;

final class ProductAuthoring
{
    public function prepare(array $data, ?int $productId = null): array
    {
        $title=trim((string)($data['title']??''));
        if($title==='') throw new InvalidArgumentException('Product title is required.');

        $slug=$this->slugify((string)($data['slug']??''));
        if($slug==='') $slug=$this->slugify($title);
        if($slug==='') throw new InvalidArgumentException('Could not generate a valid product slug.');

        $db=Database::connection();
        $sql='SELECT id,title FROM products WHERE slug=:slug';
        $params=['slug'=>$slug];
        if($productId){$sql.=' AND id<>:id';$params['id']=$productId;}
        $sql.=' LIMIT 1';
        $stmt=$db->prepare($sql);$stmt->execute($params);
        $duplicate=$stmt->fetch(PDO::FETCH_ASSOC);
        if($duplicate){
            throw new InvalidArgumentException('The slug “'.$slug.'” is already used by “'.$duplicate['title'].'”. Choose a different slug.');
        }

        $asin=strtoupper(trim((string)($data['asin']??'')));
        if($asin!==''){
            if(!preg_match('/^[A-Z0-9]{10}$/',$asin)){
                throw new InvalidArgumentException('ASIN must contain exactly 10 letters/numbers.');
            }
            $sql='SELECT id,title FROM products WHERE asin=:asin';
            $params=['asin'=>$asin];
            if($productId){$sql.=' AND id<>:id';$params['id']=$productId;}
            $sql.=' LIMIT 1';
            $stmt=$db->prepare($sql);$stmt->execute($params);
            $duplicate=$stmt->fetch(PDO::FETCH_ASSOC);
            if($duplicate){
                throw new InvalidArgumentException('ASIN '.$asin.' is already assigned to “'.$duplicate['title'].'”.');
            }
            $data['asin']=$asin;
        }

        $data['title']=$title;
        $data['slug']=$slug;
        return $data;
    }

    private function slugify(string $value): string
    {
        $value=trim($value);
        if($value==='') return '';
        if(function_exists('iconv')){
            $ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
            if(is_string($ascii)&&$ascii!=='') $value=$ascii;
        }
        $value=strtolower($value);
        $value=preg_replace('/[^a-z0-9]+/','-',$value)??'';
        return trim($value,'-');
    }
}
