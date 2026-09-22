<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Amazon\CreatorsApiClient;
use MediaPitch\Repositories\SettingsRepository;
use RuntimeException;
use Throwable;

final class ProductLinkMetadata
{
    private const MAX_BYTES = 2097152;
    private const MAX_REDIRECTS = 5;

    public function fetch(string $url): array
    {
        $requestedUrl=$this->normalizeUrl($url);
        if($this->isAmazonUrl($requestedUrl)){
            return $this->amazonMetadata($requestedUrl);
        }

        $current=$requestedUrl;
        for($redirects=0;$redirects<=self::MAX_REDIRECTS;$redirects++){
            if($this->isAmazonUrl($current)){
                return $this->amazonMetadata($current,$requestedUrl);
            }

            $response=$this->request($current,false);
            $status=$response['status'];
            if($status>=300&&$status<400&&!empty($response['location'])){
                if($redirects===self::MAX_REDIRECTS)throw new RuntimeException('The product page redirected too many times.');
                $current=$this->resolveUrl($current,(string)$response['location']);
                continue;
            }
            if($status<200||$status>=400)throw new RuntimeException('The product page returned HTTP '.$status.'.');
            $contentType=strtolower((string)$response['content_type']);
            if($contentType!==''&&!str_contains($contentType,'text/html')&&!str_contains($contentType,'application/xhtml+xml')){
                throw new RuntimeException('The product URL did not return an HTML page.');
            }

            return $this->parseHtml((string)$response['body'],$current,$requestedUrl);
        }

        throw new RuntimeException('Could not resolve the product page.');
    }

    private function amazonMetadata(string $url,?string $requestedUrl=null): array
    {
        $requestedUrl=$requestedUrl?:$url;
        $resolved=$url;
        $asin=$this->asinFromUrl($resolved);

        if($asin===''){
            $resolved=$this->resolveAmazonShortUrl($resolved);
            $asin=$this->asinFromUrl($resolved);
        }
        if($asin==='')throw new RuntimeException('Could not find an ASIN in that Amazon link. Paste the full Amazon product URL and try again.');

        $base=[
            'provider'=>'amazon_link',
            'source_url'=>$requestedUrl,
            'resolved_url'=>$resolved,
            'asin'=>$asin,
            'title'=>'',
            'brand'=>'',
            'main_image_url'=>'',
            'short_description'=>'',
            'features'=>[],
            'price'=>null,
            'currency'=>'',
            'amazon_url'=>$resolved,
            'affiliate_url'=>$requestedUrl,
            'marketplace'=>'',
            'warning'=>'',
        ];

        try{
            $settingsRepo=new SettingsRepository();
            $settings=$this->amazonSettingsForUrl($settingsRepo,$resolved);
            $base['marketplace']=(string)($settings['marketplace']??'');
            if(empty($settings['enabled'])){
                $base['warning']='ASIN detected, but the matching Amazon Creators API profile is disabled. Enable it to fetch Amazon product details.';
                return $base;
            }

            $items=(new CreatorsApiClient())->getItems($settings,[$asin]);
            if(!$items){
                $base['warning']='ASIN detected, but Amazon Creators API did not return product details for it.';
                return $base;
            }

            $item=$items[0];
            $title=trim((string)($item['itemInfo']['title']['displayValue']??''));
            $brand=trim((string)($item['itemInfo']['byLineInfo']['brand']['displayValue']??$item['itemInfo']['byLineInfo']['manufacturer']['displayValue']??''));
            $image='';
            foreach(['large','medium','small'] as $size){
                $candidate=trim((string)($item['images']['primary'][$size]['url']??''));
                if($candidate!==''){$image=$candidate;break;}
            }
            $features=$item['itemInfo']['features']['displayValues']??[];
            if(!is_array($features))$features=[];
            $features=array_values(array_filter(array_map(static fn($v)=>trim((string)$v),$features)));
            $money=$item['offersV2']['listings'][0]['price']['money']??[];
            $price=isset($money['amount'])&&is_numeric($money['amount'])?(float)$money['amount']:null;
            $currency=trim((string)($money['currency']??''));
            $detailUrl=trim((string)($item['detailPageURL']??''));

            return array_merge($base,[
                'provider'=>'amazon_creators_api',
                'title'=>$title,
                'brand'=>$brand,
                'main_image_url'=>$image,
                'short_description'=>$features?implode(' ',array_slice($features,0,2)):'',
                'features'=>$features,
                'price'=>$price,
                'currency'=>$currency,
                'amazon_url'=>$detailUrl!==''?$detailUrl:$resolved,
                'affiliate_url'=>$detailUrl!==''?$detailUrl:$requestedUrl,
                'warning'=>'',
            ]);
        }catch(Throwable $e){
            $base['warning']='ASIN detected, but Amazon metadata could not be loaded from Creators API: '.$e->getMessage();
            return $base;
        }
    }

    private function amazonSettingsForUrl(SettingsRepository $repo,string $url): array
    {
        $host=strtolower((string)(parse_url($url,PHP_URL_HOST)?:''));
        $needle=preg_replace('/^www\./','',$host)??$host;
        foreach($repo->amazonProfiles() as $profile){
            $marketplace=strtolower((string)($profile['marketplace']??''));
            $candidate=preg_replace('/^www\./','',$marketplace)??$marketplace;
            if($candidate===$needle)return $repo->amazon($marketplace);
        }
        return $repo->amazon();
    }

    private function resolveAmazonShortUrl(string $url): string
    {
        $current=$url;
        for($redirects=0;$redirects<=self::MAX_REDIRECTS;$redirects++){
            $response=$this->request($current,true);
            $status=$response['status'];
            if($status>=300&&$status<400&&!empty($response['location'])){
                if($redirects===self::MAX_REDIRECTS)break;
                $current=$this->resolveUrl($current,(string)$response['location']);
                continue;
            }
            break;
        }
        return $current;
    }

    private function parseHtml(string $html,string $resolvedUrl,string $requestedUrl): array
    {
        $meta=$this->metaTags($html);
        $product=$this->jsonLdProduct($html);
        $canonical=$this->canonicalUrl($html,$resolvedUrl);

        $title=$this->stringValue($product['name']??null)
            ?: $this->firstMeta($meta,['og:title','twitter:title','title']);
        if($title===''){
            if(preg_match('/<title\b[^>]*>(.*?)<\/title>/is',$html,$m))$title=$this->cleanText($m[1]);
        }

        $description=$this->stringValue($product['description']??null)
            ?: $this->firstMeta($meta,['description','og:description','twitter:description']);

        $brand='';
        if(isset($product['brand'])){
            if(is_array($product['brand']))$brand=$this->stringValue($product['brand']['name']??$product['brand']['brand']??null);
            else $brand=$this->stringValue($product['brand']);
        }
        if($brand==='')$brand=$this->firstMeta($meta,['product:brand','brand','og:brand']);

        $image=$this->imageValue($product['image']??null)
            ?: $this->firstMeta($meta,['og:image:secure_url','og:image','twitter:image','twitter:image:src']);

        [$price,$currency]=$this->productOffer($product);
        if($price===null){
            $raw=$this->firstMeta($meta,['product:price:amount','og:price:amount','price']);
            if($raw!==''&&preg_match('/-?\d+(?:[.,]\d+)?/',str_replace(',','',$raw),$m))$price=(float)$m[0];
        }
        if($currency==='')$currency=strtoupper(substr($this->firstMeta($meta,['product:price:currency','og:price:currency','pricecurrency']),0,3));

        $features=$this->additionalProperties($product);
        $affiliate=$requestedUrl;
        $source=$canonical!==''?$canonical:$resolvedUrl;

        if($title===''&&$image===''&&$description===''){
            throw new RuntimeException('The page loaded, but no usable product metadata was found.');
        }

        return [
            'provider'=>'page_metadata',
            'source_url'=>$source,
            'resolved_url'=>$resolvedUrl,
            'asin'=>'',
            'title'=>$title,
            'brand'=>$brand,
            'main_image_url'=>$image,
            'short_description'=>$description,
            'features'=>$features,
            'price'=>$price,
            'currency'=>$currency,
            'amazon_url'=>'',
            'affiliate_url'=>$affiliate,
            'marketplace'=>'',
            'warning'=>'',
        ];
    }

    private function request(string $url,bool $headersOnly): array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required to read product links.');
        [$host,$port,$pinnedIp]=$this->safeDestination($url);
        $body='';$location='';$tooLarge=false;$max=self::MAX_BYTES;
        $ch=curl_init($url);
        $options=[
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_RETURNTRANSFER=>false,
            CURLOPT_CONNECTTIMEOUT=>6,
            CURLOPT_TIMEOUT=>15,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/153.0 Safari/537.36 MediaPitchMetadata/1.0',
            CURLOPT_HTTPHEADER=>['Accept: text/html,application/xhtml+xml;q=0.9,*/*;q=0.8','Accept-Language: en-IN,en;q=0.9','Cache-Control: no-cache'],
            CURLOPT_ENCODING=>'',
            CURLOPT_NOBODY=>$headersOnly,
            CURLOPT_HEADERFUNCTION=>static function($curl,string $line)use(&$location):int{
                if(str_starts_with(strtolower($line),'location:'))$location=trim(substr($line,9));
                return strlen($line);
            },
        ];
        if(!$headersOnly){
            $options[CURLOPT_WRITEFUNCTION]=static function($curl,string $chunk)use(&$body,&$tooLarge,$max):int{
                if(strlen($body)+strlen($chunk)>$max){$tooLarge=true;return 0;}
                $body.=$chunk;return strlen($chunk);
            };
        }
        if($pinnedIp!==null)$options[CURLOPT_RESOLVE]=[$host.':'.$port.':'.$pinnedIp];
        curl_setopt_array($ch,$options);
        $ok=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $contentType=(string)(curl_getinfo($ch,CURLINFO_CONTENT_TYPE)?:'');
        $error=curl_error($ch);
        curl_close($ch);
        if($tooLarge)throw new RuntimeException('The product page is too large to inspect safely.');
        if($ok===false)throw new RuntimeException('Could not load the product page: '.$error);
        return ['status'=>$status,'content_type'=>$contentType,'location'=>$location,'body'=>$body];
    }

    private function safeDestination(string $url): array
    {
        $parts=parse_url($url);
        if(!is_array($parts))throw new RuntimeException('Invalid product URL.');
        $scheme=strtolower((string)($parts['scheme']??''));
        if(!in_array($scheme,['http','https'],true))throw new RuntimeException('Only HTTP and HTTPS product links are supported.');
        if(isset($parts['user'])||isset($parts['pass']))throw new RuntimeException('Product links with embedded credentials are not allowed.');
        $host=strtolower(rtrim((string)($parts['host']??''),'.'));
        if($host===''||$host==='localhost'||str_ends_with($host,'.localhost')||str_ends_with($host,'.local')||str_ends_with($host,'.internal')){
            throw new RuntimeException('That product URL host is not allowed.');
        }
        $port=(int)($parts['port']??($scheme==='https'?443:80));
        if(!in_array($port,[80,443],true))throw new RuntimeException('Only standard HTTP/HTTPS ports are allowed.');

        if(filter_var($host,FILTER_VALIDATE_IP)){
            if(!$this->isPublicIp($host))throw new RuntimeException('Private or reserved network addresses are not allowed.');
            return [$host,$port,null];
        }

        $records=function_exists('dns_get_record')?@dns_get_record($host,DNS_A|DNS_AAAA):[];
        $ips=[];
        if(is_array($records)){
            foreach($records as $record){
                $ip=(string)($record['ip']??$record['ipv6']??'');
                if($ip!=='')$ips[]=$ip;
            }
        }
        if(!$ips){
            $ipv4=@gethostbynamel($host);
            if(is_array($ipv4))$ips=$ipv4;
        }
        if(!$ips)throw new RuntimeException('Could not resolve the product URL host.');
        foreach($ips as $ip)if(!$this->isPublicIp($ip))throw new RuntimeException('The product URL resolves to a private or reserved network address.');
        $ipv4=null;
        foreach($ips as $ip)if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){$ipv4=$ip;break;}
        if($ipv4===null)throw new RuntimeException('This host could not be safely pinned to a public IPv4 address.');
        return [$host,$port,$ipv4];
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false;
    }

    private function normalizeUrl(string $url): string
    {
        $url=trim($url);
        if($url===''||filter_var($url,FILTER_VALIDATE_URL)===false)throw new RuntimeException('Enter a valid product URL.');
        $parts=parse_url($url);
        $scheme=strtolower((string)($parts['scheme']??''));
        if(!in_array($scheme,['http','https'],true))throw new RuntimeException('Only HTTP and HTTPS product links are supported.');
        return $url;
    }

    private function resolveUrl(string $base,string $location): string
    {
        $location=trim($location);
        if($location==='')throw new RuntimeException('The product page returned an empty redirect.');
        if(filter_var($location,FILTER_VALIDATE_URL)!==false)return $this->normalizeUrl($location);
        $baseParts=parse_url($base);
        if(!is_array($baseParts)||empty($baseParts['host']))throw new RuntimeException('Could not resolve the product page redirect.');
        $scheme=(string)($baseParts['scheme']??'https');
        if(str_starts_with($location,'//'))return $this->normalizeUrl($scheme.':'.$location);
        $authority=$scheme.'://'.$baseParts['host'].(isset($baseParts['port'])?':'.$baseParts['port']:'');
        if(str_starts_with($location,'/'))return $this->normalizeUrl($authority.$location);
        $path=(string)($baseParts['path']??'/');
        $dir=preg_replace('#/[^/]*$#','/',$path)?:'/';
        $combined=$dir.$location;
        $segments=[];
        foreach(explode('/',$combined) as $segment){
            if($segment===''||$segment==='.')continue;
            if($segment==='..'){array_pop($segments);continue;}
            $segments[]=$segment;
        }
        return $this->normalizeUrl($authority.'/'.implode('/',$segments));
    }

    private function isAmazonUrl(string $url): bool
    {
        $host=strtolower((string)(parse_url($url,PHP_URL_HOST)?:''));
        $host=preg_replace('/^www\./','',$host)??$host;
        if(in_array($host,['amzn.in','amzn.to','a.co'],true))return true;
        return preg_match('/(^|\.)amazon\.[a-z]{2,}(?:\.[a-z]{2,})?$/',$host)===1;
    }

    private function asinFromUrl(string $url): string
    {
        $path=(string)(parse_url($url,PHP_URL_PATH)?:'');
        foreach([
            '#/(?:dp|gp/product|gp/aw/d)/([A-Z0-9]{10})(?:[/?]|$)#i',
            '#/([A-Z0-9]{10})(?:[/?]|$)#i',
        ] as $pattern){
            if(preg_match($pattern,$path,$m))return strtoupper($m[1]);
        }
        parse_str((string)(parse_url($url,PHP_URL_QUERY)?:''),$query);
        $candidate=strtoupper(trim((string)($query['asin']??$query['ASIN']??'')));
        return preg_match('/^[A-Z0-9]{10}$/',$candidate)?$candidate:'';
    }

    private function metaTags(string $html): array
    {
        $out=[];
        if(!preg_match_all('/<meta\b[^>]*>/i',$html,$tags))return $out;
        foreach($tags[0] as $tag){
            $attrs=$this->attributes($tag);
            $key=strtolower(trim((string)($attrs['property']??$attrs['name']??$attrs['itemprop']??'')));
            if($key===''||!isset($attrs['content']))continue;
            $value=$this->cleanText((string)$attrs['content']);
            if($value!==''&&!isset($out[$key]))$out[$key]=$value;
        }
        return $out;
    }

    private function canonicalUrl(string $html,string $base): string
    {
        if(!preg_match_all('/<link\b[^>]*>/i',$html,$links))return '';
        foreach($links[0] as $tag){
            $attrs=$this->attributes($tag);
            $rel=strtolower(trim((string)($attrs['rel']??'')));
            if(!preg_match('/(^|\s)canonical(\s|$)/',$rel)||empty($attrs['href']))continue;
            try{return $this->resolveUrl($base,(string)$attrs['href']);}catch(Throwable){return '';}
        }
        return '';
    }

    private function attributes(string $tag): array
    {
        $attrs=[];
        if(preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/',$tag,$matches,PREG_SET_ORDER)){
            foreach($matches as $m){
                $key=strtolower($m[1]);
                $value=$m[2]!==''?$m[2]:($m[3]!==''?$m[3]:($m[4]??''));
                $attrs[$key]=html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8');
            }
        }
        return $attrs;
    }

    private function jsonLdProduct(string $html): array
    {
        if(!preg_match_all('/<script\b([^>]*)>(.*?)<\/script>/is',$html,$scripts,PREG_SET_ORDER))return [];
        foreach($scripts as $script){
            $attrs=$this->attributes('<script '.$script[1].'>');
            if(strtolower(trim((string)($attrs['type']??'')))!=='application/ld+json')continue;
            $raw=trim(html_entity_decode($script[2],ENT_QUOTES|ENT_HTML5,'UTF-8'));
            if($raw==='')continue;
            $decoded=json_decode($raw,true);
            if(!is_array($decoded))continue;
            $product=$this->findProductNode($decoded);
            if($product)return $product;
        }
        return [];
    }

    private function findProductNode(array $node): array
    {
        $type=$node['@type']??null;
        $types=is_array($type)?$type:[$type];
        foreach($types as $candidate)if(is_string($candidate)&&strcasecmp($candidate,'Product')===0)return $node;
        foreach($node as $value){
            if(!is_array($value))continue;
            if(array_is_list($value)){
                foreach($value as $item)if(is_array($item)){ $found=$this->findProductNode($item); if($found)return $found; }
            }else{
                $found=$this->findProductNode($value);if($found)return $found;
            }
        }
        return [];
    }

    private function productOffer(array $product): array
    {
        $offers=$product['offers']??null;
        if(!is_array($offers))return [null,''];
        if(array_is_list($offers))$offers=$offers[0]??[];
        if(!is_array($offers))return [null,''];
        $priceRaw=$offers['price']??$offers['lowPrice']??($offers['priceSpecification']['price']??null);
        $currency=$this->stringValue($offers['priceCurrency']??($offers['priceSpecification']['priceCurrency']??null));
        $price=null;
        if(is_numeric($priceRaw))$price=(float)$priceRaw;
        elseif(is_string($priceRaw)&&preg_match('/-?\d+(?:[.,]\d+)?/',str_replace(',','',$priceRaw),$m))$price=(float)$m[0];
        return [$price,strtoupper(substr($currency,0,3))];
    }

    private function additionalProperties(array $product): array
    {
        $props=$product['additionalProperty']??[];
        if(!is_array($props))return [];
        if(!array_is_list($props))$props=[$props];
        $out=[];
        foreach($props as $prop){
            if(!is_array($prop))continue;
            $name=$this->stringValue($prop['name']??'');
            $value=$this->stringValue($prop['value']??'');
            if($name!==''&&$value!=='')$out[]=$name.': '.$value;
            elseif($value!=='')$out[]=$value;
            if(count($out)>=20)break;
        }
        return $out;
    }

    private function imageValue(mixed $image): string
    {
        if(is_string($image))return trim($image);
        if(!is_array($image))return '';
        if(array_is_list($image)){
            foreach($image as $item){$value=$this->imageValue($item);if($value!=='')return $value;}
            return '';
        }
        return $this->stringValue($image['url']??$image['contentUrl']??'');
    }

    private function firstMeta(array $meta,array $keys): string
    {
        foreach($keys as $key){
            $value=trim((string)($meta[strtolower($key)]??''));
            if($value!=='')return $value;
        }
        return '';
    }

    private function stringValue(mixed $value): string
    {
        if(is_string($value)||is_numeric($value))return $this->cleanText((string)$value);
        return '';
    }

    private function cleanText(string $value): string
    {
        $value=html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');
        return trim(preg_replace('/\s+/u',' ',$value)??$value);
    }
}
