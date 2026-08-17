<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use InvalidArgumentException;
use MediaPitch\Core\Database;
use PDO;

final class BrandRepository
{
    public function all(): array
    {
        return Database::connection()->query('SELECT id,name,slug,website_url,logo_url,active,updated_at FROM brands ORDER BY active DESC,name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function activeOptions(): array
    {
        return Database::connection()->query('SELECT id,name FROM brands WHERE active=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(?int $id): ?array
    {
        if(!$id)return null;
        $stmt=Database::connection()->prepare('SELECT * FROM brands WHERE id=:id LIMIT 1');
        $stmt->execute(['id'=>$id]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row?:null;
    }

    public function save(array $data,?int $id=null): int
    {
        $name=trim((string)($data['name']??''));
        $slug=trim((string)($data['slug']??''));
        $website=trim((string)($data['website_url']??''));
        $logo=trim((string)($data['logo_url']??''));
        if($name===''||$slug==='')throw new InvalidArgumentException('Brand name and slug are required.');
        if($website!==''&&!filter_var($website,FILTER_VALIDATE_URL))throw new InvalidArgumentException('Brand website URL is invalid.');
        if($logo!==''&&!filter_var($logo,FILTER_VALIDATE_URL))throw new InvalidArgumentException('Brand logo URL is invalid.');
        $params=['name'=>$name,'slug'=>$slug,'website_url'=>$website?:null,'logo_url'=>$logo?:null];
        $db=Database::connection();
        if($id){$params['id']=$id;$db->prepare('UPDATE brands SET name=:name,slug=:slug,website_url=:website_url,logo_url=:logo_url WHERE id=:id')->execute($params);return $id;}
        $db->prepare('INSERT INTO brands (name,slug,website_url,logo_url,active) VALUES (:name,:slug,:website_url,:logo_url,1)')->execute($params);
        return (int)$db->lastInsertId();
    }

    public function setActive(int $id,bool $active): void
    {
        $stmt=Database::connection()->prepare('UPDATE brands SET active=:active WHERE id=:id');
        $stmt->execute(['active'=>$active?1:0,'id'=>$id]);
    }
}
