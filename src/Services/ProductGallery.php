<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use InvalidArgumentException;
use MediaPitch\Core\Database;

final class ProductGallery
{
    public function saveFromInput(int $productId,string $input): void
    {
        $urls=[];
        foreach(preg_split('/\r?\n/',$input)?:[] as $line){
            $url=trim($line);
            if($url==='')continue;
            if(!str_starts_with($url,'/') && !filter_var($url,FILTER_VALIDATE_URL)){
                throw new InvalidArgumentException('Gallery images must be valid URLs or site-relative paths.');
            }
            $urls[]=$url;
        }
        $urls=array_values(array_unique($urls));
        if(count($urls)>20)throw new InvalidArgumentException('A product gallery can contain up to 20 images.');
        $stmt=Database::connection()->prepare('UPDATE products SET gallery_json=:gallery WHERE id=:id');
        $stmt->execute(['gallery'=>$urls?json_encode($urls,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null,'id'=>$productId]);
        ProductAuthoring::persistPendingOverrides($productId);
    }
}
