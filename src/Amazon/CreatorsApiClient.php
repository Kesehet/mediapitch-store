<?php

declare(strict_types=1);

namespace MediaPitch\Amazon;

use RuntimeException;

final class CreatorsApiClient
{
    private const RETRYABLE_STATUS=[429,500,502,503,504];
    private static array $tokenCache=[];

    public function testCredentials(array $settings): array
    {
        if(empty($settings['enabled']))throw new RuntimeException('Enable this Amazon Creators API marketplace profile before testing it.');
        $token=$this->fetchToken($settings,true);
        $marketplace=trim((string)($settings['marketplace']??''));
        $partnerTag=trim((string)($settings['partner_tag']??''));
        if($marketplace===''||$partnerTag==='')throw new RuntimeException('Amazon marketplace and Partner Tag are required.');

        // Do a real catalog call, not just OAuth. This validates credential scope,
        // marketplace access, Partner Tag and the current Creators API request shape.
        $probe=$this->apiRequest('searchItems',[
            'keywords'=>'book','searchIndex'=>'All','itemCount'=>1,
            'marketplace'=>$marketplace,'partnerTag'=>$partnerTag,
            'resources'=>['images.primary.large','itemInfo.title','offersV2.listings.price'],
        ],$settings);
        $items=$probe['searchResult']['items']??[];
        return ['ok'=>true,'expires_in'=>$token['expires_in'],'token_type'=>$token['token_type'],'catalog_ok'=>is_array($items),'sample_items'=>is_array($items)?count($items):0];
    }

    public function searchItems(array $settings,string $keywords,int $itemCount=10): array
    {
        $keywords=trim($keywords);
        if($keywords==='')throw new RuntimeException('Enter keywords to search Amazon.');
        $itemCount=max(1,min(10,$itemCount));
        $marketplace=trim((string)($settings['marketplace']??''));
        $partnerTag=trim((string)($settings['partner_tag']??''));
        if($marketplace===''||$partnerTag==='')throw new RuntimeException('Amazon marketplace and Partner Tag are required.');
        if(empty($settings['enabled']))throw new RuntimeException('Amazon Creators API is disabled in settings.');

        $payload=[
            'keywords'=>$keywords,'searchIndex'=>'All','itemCount'=>$itemCount,
            'marketplace'=>$marketplace,'partnerTag'=>$partnerTag,
            'resources'=>['images.primary.large','itemInfo.title','itemInfo.features','itemInfo.byLineInfo','offersV2.listings.price','offersV2.listings.availability'],
        ];
        $json=$this->apiRequest('searchItems',$payload,$settings);
        $items=$json['searchResult']['items']??[];
        return is_array($items)?$items:[];
    }

    public function getItems(array $settings,array $asins): array
    {
        $asins=array_values(array_unique(array_filter(array_map(static fn($v)=>strtoupper(trim((string)$v)),$asins))));
        if(!$asins)return [];
        if(count($asins)>10)$asins=array_slice($asins,0,10);
        $marketplace=trim((string)($settings['marketplace']??''));
        $partnerTag=trim((string)($settings['partner_tag']??''));
        if($marketplace===''||$partnerTag==='')throw new RuntimeException('Amazon marketplace and Partner Tag are required.');
        if(empty($settings['enabled']))throw new RuntimeException('Amazon Creators API is disabled in settings.');
        $payload=[
            'itemIds'=>$asins,'itemIdType'=>'ASIN','marketplace'=>$marketplace,'partnerTag'=>$partnerTag,
            'resources'=>['images.primary.large','itemInfo.title','itemInfo.features','itemInfo.byLineInfo','offersV2.listings.price','offersV2.listings.availability'],
        ];
        $json=$this->apiRequest('getItems',$payload,$settings);
        $items=$json['itemsResult']['items']??[];
        return is_array($items)?$items:[];
    }

    private function apiRequest(string $operation,array $payload,array $settings): array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for Creators API requests.');
        $marketplace=trim((string)($settings['marketplace']??''));
        $endpoint='https://creatorsapi.amazon/catalog/v1/'.$operation;
        $attempts=max(1,min(5,(int)env('AMAZON_API_RETRY_ATTEMPTS',3)));
        $lastMessage='Request failed';

        for($attempt=1;$attempt<=$attempts;$attempt++){
            $token=$this->fetchToken($settings,$attempt>1 && str_contains($lastMessage,'HTTP 401'));
            $ch=curl_init($endpoint);
            curl_setopt_array($ch,[
                CURLOPT_POST=>true,
                CURLOPT_POSTFIELDS=>json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES),
                CURLOPT_HTTPHEADER=>[
                    'Authorization: Bearer '.$token['access_token'],
                    'Content-Type: application/json','Accept: application/json','x-marketplace: '.$marketplace,
                ],
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,
            ]);
            $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);

            if($response!==false){
                $json=json_decode((string)$response,true);
                if($status>=200&&$status<300&&is_array($json)) return $json;
                $lastMessage='Amazon API request failed (HTTP '.$status.'): '.$this->errorMessage($json);
                if(!in_array($status,self::RETRYABLE_STATUS,true) && $status!==401) throw new RuntimeException($lastMessage);
            }else{
                $lastMessage='Amazon API request failed: '.$error;
            }

            if($attempt<$attempts)$this->backoff($attempt);
        }

        throw new RuntimeException($lastMessage.' after '.$attempts.' attempt(s).');
    }

    private function fetchToken(array $settings,bool $force=false): array
    {
        $id=trim((string)($settings['credential_id']??''));
        $secret=trim((string)($settings['credential_secret']??''));
        $version=trim((string)($settings['credential_version']??''));
        if($id===''||$secret===''||$version==='')throw new RuntimeException('Credential ID, secret and version are required.');
        if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for Creators API requests.');

        $fingerprint=hash('sha256',$version.'|'.$id);
        $cached=self::$tokenCache[$fingerprint]??($_SESSION['_amazon_creators_token']??null);
        if(!$force&&is_array($cached)&&($cached['fingerprint']??'')===$fingerprint&&(int)($cached['expires_at']??0)>time()+90&&!empty($cached['access_token'])) return $cached;

        [$endpoint,$body,$contentType]=$this->tokenRequest($version,$id,$secret);
        $attempts=max(1,min(4,(int)env('AMAZON_AUTH_RETRY_ATTEMPTS',2)));
        $last='Amazon authentication failed.';
        for($attempt=1;$attempt<=$attempts;$attempt++){
            $ch=curl_init($endpoint);
            curl_setopt_array($ch,[
                CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,
                CURLOPT_HTTPHEADER=>['Content-Type: '.$contentType,'Accept: application/json'],
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_FOLLOWLOCATION=>false,
            ]);
            $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
            if($response!==false){
                $json=json_decode((string)$response,true);
                if($status>=200&&$status<300&&is_array($json)&&!empty($json['access_token'])){
                    $expires=max(60,(int)($json['expires_in']??3600));
                    $token=['access_token'=>(string)$json['access_token'],'expires_in'=>$expires,'token_type'=>(string)($json['token_type']??'bearer'),'expires_at'=>time()+$expires,'fingerprint'=>$fingerprint];
                    self::$tokenCache[$fingerprint]=$token;
                    $_SESSION['_amazon_creators_token']=$token;
                    return $token;
                }
                $last='Amazon authentication failed (HTTP '.$status.'): '.$this->errorMessage($json);
                if(!in_array($status,self::RETRYABLE_STATUS,true)) throw new RuntimeException($last);
            }else{$last='Amazon authentication request failed: '.$error;}
            if($attempt<$attempts)$this->backoff($attempt);
        }
        throw new RuntimeException($last.' after '.$attempts.' attempt(s).');
    }

    private function backoff(int $attempt): void
    {
        $base=max(100,min(5000,(int)env('AMAZON_RETRY_BASE_MS',350)));
        $delay=min(5000,$base*(2**max(0,$attempt-1))+random_int(0,150));
        usleep($delay*1000);
    }

    private function errorMessage(mixed $json): string
    {
        if(!is_array($json))return 'Unexpected response from Amazon.';
        if(isset($json['errors'][0]['message']))return (string)$json['errors'][0]['message'];
        return (string)($json['error_description']??$json['message']??$json['error']??'Request failed');
    }

    private function tokenRequest(string $version,string $id,string $secret): array
    {
        $endpoints=[
            '2.1'=>'https://creatorsapi.auth.us-east-1.amazoncognito.com/oauth2/token',
            '2.2'=>'https://creatorsapi.auth.eu-south-2.amazoncognito.com/oauth2/token',
            '2.3'=>'https://creatorsapi.auth.us-west-2.amazoncognito.com/oauth2/token',
            '3.1'=>'https://api.amazon.com/auth/o2/token',
            '3.2'=>'https://api.amazon.co.uk/auth/o2/token',
            '3.3'=>'https://api.amazon.co.jp/auth/o2/token',
        ];
        if(!isset($endpoints[$version]))throw new RuntimeException('Unsupported Creators API credential version.');
        if(str_starts_with($version,'2.')) return [$endpoints[$version],http_build_query(['grant_type'=>'client_credentials','client_id'=>$id,'client_secret'=>$secret,'scope'=>'creatorsapi/default']),'application/x-www-form-urlencoded'];
        return [$endpoints[$version],json_encode(['grant_type'=>'client_credentials','client_id'=>$id,'client_secret'=>$secret,'scope'=>'creatorsapi::default'],JSON_THROW_ON_ERROR),'application/json'];
    }
}
