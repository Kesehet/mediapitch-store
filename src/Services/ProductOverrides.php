<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Core\Database;
use PDO;

final class ProductOverrides
{
    public const FIELDS=['title','main_image_url','features_json','price','amazon_url','affiliate_url'];

    public function save(int $productId,mixed $submitted): array
    {
        $requested=is_array($submitted)?$submitted:[];
        $flags=[];
        foreach(self::FIELDS as $field)$flags[$field]=!empty($requested[$field]);
        $json=json_encode($flags,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $stmt=Database::connection()->prepare('UPDATE products SET manual_override_json=:json WHERE id=:id');
        $stmt->execute(['json'=>$json,'id'=>$productId]);
        return $flags;
    }

    public function forProduct(array $product): array
    {
        $raw=$product['manual_override_json']??null;
        $decoded=is_string($raw)?json_decode($raw,true):[];
        if(!is_array($decoded))$decoded=[];
        $flags=[];
        foreach(self::FIELDS as $field)$flags[$field]=!empty($decoded[$field]);
        return $flags;
    }
}
