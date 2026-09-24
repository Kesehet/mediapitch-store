<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Ai\OllamaClient;
use MediaPitch\Ai\WebResearcher;
use MediaPitch\Core\Database;
use MediaPitch\Repositories\AiSettingsRepository;
use PDO;
use Throwable;

final class ProductBackfillService
{
    private ProductLinkMetadata $metadata;
    private WebResearcher $research;

    public function __construct(
        ?ProductLinkMetadata $metadata = null,
        ?WebResearcher $research = null
    ) {
        $this->metadata = $metadata ?? new ProductLinkMetadata();
        $this->research = $research ?? new WebResearcher();
    }

    /** @return array{total:int,needs_backfill:int,with_link:int,with_asin:int,recent_applied:int} */
    public function stats(): array
    {
        $db=Database::connection();
        $row=$db->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN brand_id IS NULL OR category_id IS NULL OR asin IS NULL OR asin='' OR short_description IS NULL OR short_description='' OR main_image_url IS NULL OR main_image_url='' OR features_json IS NULL OR features_json='' THEN 1 ELSE 0 END) AS needs_backfill,
                SUM(CASE WHEN COALESCE(NULLIF(amazon_url,''),NULLIF(affiliate_url,'')) IS NOT NULL THEN 1 ELSE 0 END) AS with_link,
                SUM(CASE WHEN asin IS NOT NULL AND asin<>'' THEN 1 ELSE 0 END) AS with_asin
             FROM products"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $recent=0;
        try{
            $recent=(int)$db->query("SELECT COUNT(*) FROM product_enrichment_log WHERE status='applied' AND created_at>=UTC_TIMESTAMP()-INTERVAL 7 DAY")->fetchColumn();
        }catch(Throwable){}
        return [
            'total'=>(int)($row['total']??0),
            'needs_backfill'=>(int)($row['needs_backfill']??0),
            'with_link'=>(int)($row['with_link']??0),
            'with_asin'=>(int)($row['with_asin']??0),
            'recent_applied'=>$recent,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function candidates(int $limit=50): array
    {
        $limit=max(1,min(200,$limit));
        $sql="SELECT p.id,p.title,p.display_title,p.asin,p.brand_id,p.category_id,p.short_description,p.main_image_url,p.features_json,p.price,p.amazon_url,p.affiliate_url,
                    b.name AS brand_name,c.name AS category_name
              FROM products p
              LEFT JOIN brands b ON b.id=p.brand_id
              LEFT JOIN categories c ON c.id=p.category_id
              WHERE p.brand_id IS NULL OR p.category_id IS NULL OR p.asin IS NULL OR p.asin='' OR p.short_description IS NULL OR p.short_description='' OR p.main_image_url IS NULL OR p.main_image_url='' OR p.features_json IS NULL OR p.features_json=''
              ORDER BY
                (CASE WHEN COALESCE(NULLIF(p.amazon_url,''),NULLIF(p.affiliate_url,'')) IS NOT NULL THEN 0 ELSE 1 END),
                p.updated_at DESC
              LIMIT ".$limit;
        $rows=Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as &$row){
            $present=0;$total=8;
            foreach(['brand_id','category_id','asin','short_description','main_image_url','features_json','price'] as $field){
                if(isset($row[$field])&&$row[$field]!==null&&$row[$field]!=='')$present++;
            }
            if(!empty($row['amazon_url'])||!empty($row['affiliate_url']))$present++;
            $row['completeness']=(int)round(($present/$total)*100);
            $row['source_url']=(string)($row['amazon_url']?:$row['affiliate_url']?:'');
        }
        unset($row);
        return $rows;
    }

    /** @return array{processed:int,updated:int,fields:int,failed:int,results:array<int,array<string,mixed>>} */
    public function backfillBatch(array $ids=[],int $limit=3,bool $useAi=true): array
    {
        $limit=max(1,min(10,$limit));
        if($ids){
            $ids=array_slice(array_values(array_unique(array_filter(array_map('intval',$ids)))),0,$limit);
        }else{
            $ids=array_map(static fn(array $row)=>(int)$row['id'],$this->candidates($limit));
        }

        $summary=['processed'=>0,'updated'=>0,'fields'=>0,'failed'=>0,'results'=>[]];
        foreach($ids as $id){
            $summary['processed']++;
            try{
                $result=$this->backfillProduct($id,$useAi);
                if(($result['fields_updated']??0)>0)$summary['updated']++;
                $summary['fields']+=(int)($result['fields_updated']??0);
                $summary['results'][]=$result;
            }catch(Throwable $e){
                $summary['failed']++;
                $summary['results'][]=['product_id'=>$id,'status'=>'failed','fields_updated'=>0,'error'=>$e->getMessage()];
                $this->log($id,'_job',null,null,'system',null,null,'failed');
            }
        }
        return $summary;
    }

    /** @return array<string,mixed> */
    public function backfillProduct(int $productId,bool $useAi=true): array
    {
        $db=Database::connection();
        $stmt=$db->prepare(
            'SELECT p.*,b.name AS brand_name,c.name AS category_name
             FROM products p
             LEFT JOIN brands b ON b.id=p.brand_id
             LEFT JOIN categories c ON c.id=p.category_id
             WHERE p.id=:id LIMIT 1'
        );
        $stmt->execute(['id'=>$productId]);
        $product=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$product)throw new \RuntimeException('Product not found.');

        $changes=[];
        $evidence=[];
        $metadata=[];
        $sourceUrl=trim((string)($product['amazon_url']?:$product['affiliate_url']?:''));

        if($sourceUrl!==''){
            try{
                $metadata=$this->metadata->fetch($sourceUrl);
                $evidence[]=[
                    'type'=>'page_metadata',
                    'url'=>(string)($metadata['source_url']??$sourceUrl),
                    'title'=>(string)($metadata['title']??''),
                    'brand'=>(string)($metadata['brand']??''),
                    'description'=>(string)($metadata['short_description']??''),
                    'features'=>$metadata['features']??[],
                ];
            }catch(Throwable $e){
                $evidence[]=['type'=>'metadata_error','url'=>$sourceUrl,'error'=>$e->getMessage()];
            }
        }

        $asin=$this->cleanAsin((string)($metadata['asin']??$product['asin']??''));
        $query=trim(implode(' ',array_filter([
            (string)($product['brand_name']??''),
            (string)($product['title']??''),
            $asin!==''?$asin:'',
        ])));

        if($this->metadataSparse($metadata)&&$query!==''){
            try{
                $results=$this->research->search($query,3);
                foreach($results as $result){
                    $entry=['type'=>'web_search','url'=>$result['url'],'title'=>$result['title'],'excerpt'=>$result['excerpt']??''];
                    try{$entry['text']=$this->research->read((string)$result['url'],4500);}catch(Throwable $e){$entry['read_error']=$e->getMessage();}
                    $evidence[]=$entry;
                }
            }catch(Throwable $e){
                $evidence[]=['type'=>'research_error','error'=>$e->getMessage()];
            }
        }

        $ai=[];
        if($useAi){
            try{$ai=$this->aiEnrichment($product,$evidence);}catch(Throwable $e){$evidence[]=['type'=>'ai_error','error'=>$e->getMessage()];}
        }

        $this->queueBlankChange($changes,$product,'asin',$this->cleanAsin((string)($metadata['asin']??$ai['asin']??'')),'metadata',($metadata['source_url']??$sourceUrl)?:null,0.98);
        $this->queueBlankChange($changes,$product,'short_description',$this->cleanText((string)($metadata['short_description']??$ai['short_description']??''),4000),'metadata',($metadata['source_url']??$sourceUrl)?:null,0.90);
        $this->queueBlankChange($changes,$product,'full_description',$this->cleanText((string)($ai['full_description']??''),20000),'ai',null,0.78);
        $this->queueBlankChange($changes,$product,'main_image_url',$this->safeUrl((string)($metadata['main_image_url']??'')),'metadata',($metadata['source_url']??$sourceUrl)?:null,0.95);

        $features=$metadata['features']??[];
        if((!is_array($features)||!$features)&&isset($ai['features'])&&is_array($ai['features']))$features=$ai['features'];
        $features=$this->cleanList(is_array($features)?$features:[],20,500);
        if($features&&!$this->hasValue($product['features_json']??null)){
            $changes['features_json']=[
                'value'=>json_encode($features,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
                'source'=>!empty($metadata['features'])?'metadata':'ai',
                'url'=>($metadata['source_url']??$sourceUrl)?:null,
                'confidence'=>!empty($metadata['features'])?0.92:0.78,
            ];
        }

        if(!$this->hasValue($product['price']??null)&&isset($metadata['price'])&&is_numeric($metadata['price'])&&(float)$metadata['price']>0){
            $changes['price']=['value'=>(float)$metadata['price'],'source'=>'metadata','url'=>($metadata['source_url']??$sourceUrl)?:null,'confidence'=>0.97];
        }
        if(!$this->hasValue($product['currency']??null)&&!empty($metadata['currency'])){
            $currency=strtoupper(substr(trim((string)$metadata['currency']),0,3));
            if(preg_match('/^[A-Z]{3}$/',$currency))$changes['currency']=['value'=>$currency,'source'=>'metadata','url'=>($metadata['source_url']??$sourceUrl)?:null,'confidence'=>0.99];
        }
        if(!$this->hasValue($product['amazon_url']??null)&&!empty($metadata['amazon_url'])){
            $url=$this->safeUrl((string)$metadata['amazon_url']);if($url!=='')$changes['amazon_url']=['value'=>$url,'source'=>'metadata','url'=>$url,'confidence'=>0.99];
        }

        if(!$this->hasValue($product['brand_id']??null)){
            $brand=$this->cleanText((string)($metadata['brand']??$ai['brand']??''),150);
            if($brand!==''){
                $brandId=$this->findOrCreateBrand($brand);
                if($brandId)$changes['brand_id']=['value'=>$brandId,'source'=>!empty($metadata['brand'])?'metadata':'ai','url'=>($metadata['source_url']??null),'confidence'=>!empty($metadata['brand'])?0.95:0.80];
            }
        }

        if(!$this->hasValue($product['category_id']??null)&&!empty($ai['category_id'])){
            $categoryId=(int)$ai['category_id'];
            if($this->validCategoryId($categoryId))$changes['category_id']=['value'=>$categoryId,'source'=>'ai','url'=>null,'confidence'=>0.72];
        }

        if(!$changes){
            return ['product_id'=>$productId,'title'=>$product['title'],'status'=>'no_change','fields_updated'=>0,'evidence_count'=>count($evidence)];
        }

        $sets=[];$params=['id'=>$productId];
        foreach($changes as $field=>$change){$sets[]=$field.'=:'.$field;$params[$field]=$change['value'];}
        if(($product['source']??'manual')==='manual')$sets[]="source='hybrid'";
        if(($metadata['provider']??'')==='amazon_creators_api'){$sets[]='last_synced_at=UTC_TIMESTAMP()';}
        $db->beginTransaction();
        try{
            $update=$db->prepare('UPDATE products SET '.implode(',',$sets).' WHERE id=:id');
            $update->execute($params);
            foreach($changes as $field=>$change){
                $this->log($productId,$field,$product[$field]??null,$change['value'],(string)$change['source'],$change['url']??null,(float)$change['confidence'],'applied');
            }
            $this->applySpecifications($productId,(int)($changes['category_id']['value']??$product['category_id']??0),$ai['specifications']??[]);
            $db->commit();
        }catch(Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }

        return [
            'product_id'=>$productId,
            'title'=>$product['title'],
            'status'=>'updated',
            'fields_updated'=>count($changes),
            'fields'=>array_keys($changes),
            'evidence_count'=>count($evidence),
        ];
    }

    /** @return array<string,mixed> */
    private function aiEnrichment(array $product,array $evidence): array
    {
        $settings=(new AiSettingsRepository())->get();
        if(empty($settings['enabled'])||empty($settings['ollama_url'])||empty($settings['api_key']))return [];

        $categories=Database::connection()->query('SELECT id,name FROM categories WHERE active=1 ORDER BY name LIMIT 200')->fetchAll(PDO::FETCH_ASSOC);
        $categoryId=(int)($product['category_id']??0);
        $specs=[];
        if($categoryId>0){
            $stmt=Database::connection()->prepare('SELECT id,name,unit,data_type,options_json FROM specification_definitions WHERE category_id=:id AND active=1 ORDER BY sort_order,id');
            try{$stmt->execute(['id'=>$categoryId]);$specs=$stmt->fetchAll(PDO::FETCH_ASSOC);}catch(Throwable){
                $stmt=Database::connection()->prepare('SELECT id,name,unit,data_type,options_json FROM specification_definitions WHERE category_id=:id ORDER BY sort_order,id');
                $stmt->execute(['id'=>$categoryId]);$specs=$stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $schema=[
            'type'=>'object',
            'properties'=>[
                'asin'=>['type'=>'string'],
                'brand'=>['type'=>'string'],
                'category_id'=>['type'=>'integer'],
                'short_description'=>['type'=>'string'],
                'full_description'=>['type'=>'string'],
                'features'=>['type'=>'array','items'=>['type'=>'string']],
                'specifications'=>['type'=>'object','additionalProperties'=>['type'=>['string','number','boolean']]],
            ],
            'required'=>['asin','brand','category_id','short_description','full_description','features','specifications'],
        ];

        $system='You normalize consumer product data for an editorial product database. Use ONLY facts explicitly supported by the supplied existing record or evidence. Never invent specifications, model numbers, prices, dimensions, ratings, claims, or compatibility. Use empty strings, 0, empty arrays or empty objects when evidence is insufficient. Keep descriptions factual and concise. Select category_id only from the supplied category list. For specifications, use only the supplied specification definition IDs as object keys.';
        $user=json_encode([
            'existing_product'=>[
                'title'=>$product['title']??'','display_title'=>$product['display_title']??'','asin'=>$product['asin']??'','brand'=>$product['brand_name']??'','category_id'=>(int)($product['category_id']??0),
                'short_description'=>$product['short_description']??'','features'=>$this->decodeList($product['features_json']??null),
            ],
            'allowed_categories'=>$categories,
            'allowed_specifications'=>$specs,
            'evidence'=>array_slice($evidence,0,5),
        ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);

        return (new OllamaClient((string)$settings['ollama_url'],(string)$settings['model'],(string)$settings['api_key']))->json($system,$user,$schema);
    }

    private function applySpecifications(int $productId,int $categoryId,mixed $values): void
    {
        if($categoryId<1||!is_array($values)||!$values)return;
        $db=Database::connection();
        $stmt=$db->prepare('SELECT id,data_type,options_json FROM specification_definitions WHERE category_id=:category_id');
        $stmt->execute(['category_id'=>$categoryId]);
        $defs=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$defs[(int)$row['id']]=$row;

        $existing=$db->prepare('SELECT specification_definition_id FROM product_specifications WHERE product_id=:product_id');
        $existing->execute(['product_id'=>$productId]);
        $filled=array_fill_keys(array_map('intval',$existing->fetchAll(PDO::FETCH_COLUMN)),true);

        $insert=$db->prepare('INSERT INTO product_specifications (product_id,specification_definition_id,value_text,value_number,value_boolean) VALUES (:product_id,:definition_id,:value_text,:value_number,:value_boolean)');
        foreach($values as $definitionId=>$raw){
            $id=(int)$definitionId;if($id<1||isset($filled[$id])||!isset($defs[$id]))continue;
            $def=$defs[$id];$text=null;$number=null;$boolean=null;
            if($def['data_type']==='number'){
                if(!is_numeric($raw))continue;$number=(float)$raw;
            }elseif($def['data_type']==='boolean'){
                if(is_bool($raw))$boolean=$raw?1:0;
                elseif(in_array((string)$raw,['0','1'],true))$boolean=(int)$raw;
                else continue;
            }elseif($def['data_type']==='select'){
                $options=json_decode((string)$def['options_json'],true);
                if(!is_array($options)||!in_array((string)$raw,$options,true))continue;
                $text=(string)$raw;
            }else{
                $text=$this->cleanText((string)$raw,1000);if($text==='')continue;
            }
            $insert->execute(['product_id'=>$productId,'definition_id'=>$id,'value_text'=>$text,'value_number'=>$number,'value_boolean'=>$boolean]);
            $this->log($productId,'spec:'.$id,null,$raw,'ai',null,0.72,'applied');
        }
    }

    private function queueBlankChange(array &$changes,array $product,string $field,mixed $value,string $source,?string $url,float $confidence): void
    {
        if($this->hasValue($product[$field]??null)||!$this->hasValue($value))return;
        $changes[$field]=['value'=>$value,'source'=>$source,'url'=>$url,'confidence'=>$confidence];
    }

    private function findOrCreateBrand(string $name): ?int
    {
        $db=Database::connection();
        $stmt=$db->prepare('SELECT id FROM brands WHERE LOWER(name)=LOWER(:name) LIMIT 1');$stmt->execute(['name'=>$name]);
        $id=$stmt->fetchColumn();if($id)return (int)$id;
        $base=$this->slugify($name);if($base==='')return null;$slug=$base;$n=2;
        $check=$db->prepare('SELECT id FROM brands WHERE slug=:slug LIMIT 1');
        while(true){$check->execute(['slug'=>$slug]);if(!$check->fetchColumn())break;$suffix='-'.$n++;$slug=substr($base,0,180-strlen($suffix)).$suffix;}
        $insert=$db->prepare('INSERT INTO brands (name,slug,website_url,logo_url) VALUES (:name,:slug,NULL,NULL)');
        $insert->execute(['name'=>$name,'slug'=>$slug]);return (int)$db->lastInsertId();
    }

    private function validCategoryId(int $id): bool
    {
        if($id<1)return false;$stmt=Database::connection()->prepare('SELECT COUNT(*) FROM categories WHERE id=:id AND active=1');$stmt->execute(['id'=>$id]);return (int)$stmt->fetchColumn()>0;
    }

    private function log(int $productId,string $field,mixed $old,mixed $new,string $source,?string $url,?float $confidence,string $status): void
    {
        try{
            $encode=static function(mixed $value): ?string{
                if($value===null)return null;if(is_scalar($value))return (string)$value;
                return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            };
            $stmt=Database::connection()->prepare('INSERT INTO product_enrichment_log (product_id,field_name,old_value,new_value,source_type,source_url,confidence,status) VALUES (:product_id,:field_name,:old_value,:new_value,:source_type,:source_url,:confidence,:status)');
            $stmt->execute(['product_id'=>$productId,'field_name'=>$field,'old_value'=>$encode($old),'new_value'=>$encode($new),'source_type'=>$source,'source_url'=>$url,'confidence'=>$confidence,'status'=>$status]);
        }catch(Throwable){}
    }

    private function metadataSparse(array $metadata): bool
    {
        $count=0;foreach(['title','brand','main_image_url','short_description'] as $key)if($this->hasValue($metadata[$key]??null))$count++;
        if(!empty($metadata['features'])&&is_array($metadata['features']))$count++;
        return $count<3;
    }

    private function cleanAsin(string $value): string
    {
        $value=strtoupper(trim($value));return preg_match('/^[A-Z0-9]{10}$/',$value)?$value:'';
    }

    private function safeUrl(string $value): string
    {
        $value=trim($value);if($value===''||filter_var($value,FILTER_VALIDATE_URL)===false)return '';
        $scheme=strtolower((string)(parse_url($value,PHP_URL_SCHEME)?:''));return in_array($scheme,['http','https'],true)?$value:'';
    }

    private function cleanText(string $value,int $max): string
    {
        $value=html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $value=trim(preg_replace('/\s+/u',' ',$value)??$value);
        return mb_substr($value,0,$max);
    }

    private function cleanList(array $items,int $maxItems,int $maxChars): array
    {
        $out=[];foreach($items as $item){$item=$this->cleanText((string)$item,$maxChars);if($item!==''&&!in_array($item,$out,true))$out[]=$item;if(count($out)>=$maxItems)break;}return $out;
    }

    private function decodeList(mixed $json): array
    {
        if(!is_string($json)||trim($json)==='')return [];$data=json_decode($json,true);return is_array($data)?$data:[];
    }

    private function hasValue(mixed $value): bool
    {
        return !($value===null||$value===''||(is_array($value)&&$value===[]));
    }

    private function slugify(string $value): string
    {
        if(function_exists('iconv')){$ascii=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);if(is_string($ascii)&&$ascii!=='')$value=$ascii;}
        return trim(substr(preg_replace('/[^a-z0-9]+/','-',strtolower($value))??'',0,180),'-');
    }
}
