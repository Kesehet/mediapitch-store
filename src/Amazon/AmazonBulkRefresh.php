<?php

declare(strict_types=1);

namespace MediaPitch\Amazon;

use MediaPitch\Core\Database;
use PDO;

final class AmazonBulkRefresh
{
    public function refresh(array $settings,int $limit=50,bool $staleOnly=true): array
    {
        $limit=max(1,min(100,$limit));
        $marketplace=trim((string)($settings['marketplace']??''));
        if($marketplace==='')throw new \InvalidArgumentException('Amazon marketplace is required for product refresh.');
        $allowLegacyUnscoped=!empty($settings['allow_legacy_unscoped']);

        $sql="SELECT id,asin,category_id,last_synced_at,api_marketplace FROM products
              WHERE asin IS NOT NULL AND asin<>'' AND source IN ('amazon_api','hybrid')
                AND api_marketplace=:marketplace";
        if($allowLegacyUnscoped)$sql.=" OR (asin IS NOT NULL AND asin<>'' AND source IN ('amazon_api','hybrid') AND (api_marketplace IS NULL OR api_marketplace=''))";
        if($staleOnly)$sql.=" AND (last_synced_at IS NULL OR last_synced_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 55 MINUTE))";
        $sql.=' ORDER BY COALESCE(last_synced_at,\'1970-01-01 00:00:00\') ASC,id ASC LIMIT :limit';
        $stmt=Database::connection()->prepare($sql);
        $stmt->bindValue(':marketplace',$marketplace);
        $stmt->bindValue(':limit',$limit,PDO::PARAM_INT);
        $stmt->execute();
        $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

        $client=new CreatorsApiClient();
        $importer=new AmazonProductImporter();
        $refreshed=0;$missing=[];$errors=[];

        foreach(array_chunk($rows,10) as $batch){
            $asins=array_values(array_map(static fn(array $row)=>(string)$row['asin'],$batch));
            try{
                $items=$client->getItems($settings,$asins);
                $byAsin=[];
                foreach($items as $item){$asin=strtoupper(trim((string)($item['asin']??'')));if($asin!=='')$byAsin[$asin]=$item;}
                foreach($batch as $row){
                    $asin=strtoupper((string)$row['asin']);
                    if(!isset($byAsin[$asin])){$missing[]=$asin;continue;}
                    try{
                        $importer->import($byAsin[$asin],$settings,!empty($row['category_id'])?(int)$row['category_id']:null);
                        $refreshed++;
                    }catch(\Throwable $e){$errors[$asin]=substr($e->getMessage(),0,300);}
                }
            }catch(\Throwable $e){
                foreach($batch as $row)$errors[(string)$row['asin']]=substr($e->getMessage(),0,300);
            }
        }

        return ['selected'=>count($rows),'refreshed'=>$refreshed,'missing'=>$missing,'errors'=>$errors,'marketplace'=>$marketplace];
    }
}
