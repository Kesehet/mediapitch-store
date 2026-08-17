<?php

declare(strict_types=1);

namespace MediaPitch\Repositories;

use MediaPitch\Core\Database;
use PDO;

final class MerchandisingRepository
{
    public function settings(): array
    {
        $stmt=Database::connection()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('merch.featured_ids','merch.deal_ids','merch.deals_title')");
        $values=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$values[(string)$row['setting_key']]=(string)($row['setting_value']??'');
        return [
            'featured_ids'=>$this->decodeIds($values['merch.featured_ids']??''),
            'deal_ids'=>$this->decodeIds($values['merch.deal_ids']??''),
            'deals_title'=>trim($values['merch.deals_title']??'') ?: 'Deals worth a look',
        ];
    }

    public function productOptions(): array
    {
        return Database::connection()->query(
            "SELECT p.id,COALESCE(p.display_title,p.title) AS title,p.source,p.active,b.name AS brand_name,c.name AS category_name
             FROM products p LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN categories c ON c.id=p.category_id
             ORDER BY p.active DESC,COALESCE(p.display_title,p.title)"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function save(array $data): void
    {
        $featured=$this->cleanIds($data['featured_ids']??[]);
        $deals=$this->cleanIds($data['deal_ids']??[]);
        $title=trim((string)($data['deals_title']??''));
        if($title==='')$title='Deals worth a look';
        $this->put('merch.featured_ids',json_encode($featured,JSON_THROW_ON_ERROR));
        $this->put('merch.deal_ids',json_encode($deals,JSON_THROW_ON_ERROR));
        $this->put('merch.deals_title',substr($title,0,150));
    }

    public function homepage(): array
    {
        $settings=$this->settings();
        return [
            'featured'=>$this->productsByIds($settings['featured_ids']),
            'deals'=>$this->productsByIds($settings['deal_ids']),
            'deals_title'=>$settings['deals_title'],
        ];
    }

    private function productsByIds(array $ids): array
    {
        if(!$ids)return [];
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $stmt=Database::connection()->prepare(
            "SELECT p.id,p.title,p.display_title,p.slug,p.main_image_url,p.price,p.currency,p.custom_score,p.best_for_label,p.affiliate_url,p.source,p.last_synced_at,b.name AS brand_name,c.name AS category_name
             FROM products p LEFT JOIN brands b ON b.id=p.brand_id LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.active=1 AND p.id IN ($placeholders)"
        );
        $stmt->execute($ids);
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $byId=[];foreach($rows as $row)$byId[(int)$row['id']]=$row;
        $ordered=[];foreach($ids as $id)if(isset($byId[$id]))$ordered[]=$byId[$id];
        return $ordered;
    }

    private function decodeIds(string $json): array
    {
        $decoded=json_decode($json,true);
        return is_array($decoded)?$this->cleanIds($decoded):[];
    }

    private function cleanIds(mixed $values): array
    {
        if(!is_array($values))return [];
        return array_values(array_unique(array_filter(array_map('intval',$values),static fn(int $id):bool=>$id>0)));
    }

    private function put(string $key,string $value): void
    {
        $stmt=Database::connection()->prepare(
            'INSERT INTO settings (setting_key,setting_value,encrypted) VALUES (:k,:v,0)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),encrypted=0'
        );
        $stmt->execute(['k'=>$key,'v'=>$value]);
    }
}
