<?php

declare(strict_types=1);

namespace MediaPitch\GoogleAds;

use RuntimeException;

final class GoogleAdsClient
{
    private const API_VERSION='v25';
    private const SCOPE='https://www.googleapis.com/auth/adwords';
    private const EXPECTED=[
        'affiliate'=>['name'=>'MediaPitch - Affiliate click','category'=>'OUTBOUND_CLICK','primary'=>true,'counting'=>'ONE_PER_CLICK'],
        'product_view'=>['name'=>'MediaPitch - Product view','category'=>'PAGE_VIEW','primary'=>false,'counting'=>'MANY_PER_CLICK'],
        'search'=>['name'=>'MediaPitch - Site search','category'=>'ENGAGEMENT','primary'=>false,'counting'=>'MANY_PER_CLICK'],
    ];

    public function configured(): bool
    {
        return trim((string)env('GOOGLE_ADS_CLIENT_ID',''))!==''&&trim((string)env('GOOGLE_ADS_CLIENT_SECRET',''))!==''&&trim((string)env('GOOGLE_ADS_DEVELOPER_TOKEN',''))!=='';
    }

    public function authorizationUrl(string $state): string
    {
        $clientId=trim((string)env('GOOGLE_ADS_CLIENT_ID',''));
        if($clientId==='')throw new RuntimeException('GOOGLE_ADS_CLIENT_ID is not configured.');
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id'=>$clientId,
            'redirect_uri'=>$this->redirectUri(),
            'response_type'=>'code',
            'scope'=>self::SCOPE,
            'access_type'=>'offline',
            'prompt'=>'consent',
            'include_granted_scopes'=>'true',
            'state'=>$state,
        ]);
    }

    public function exchangeCode(string $code): string
    {
        $json=$this->tokenRequest([
            'code'=>$code,
            'client_id'=>(string)env('GOOGLE_ADS_CLIENT_ID',''),
            'client_secret'=>(string)env('GOOGLE_ADS_CLIENT_SECRET',''),
            'redirect_uri'=>$this->redirectUri(),
            'grant_type'=>'authorization_code',
        ]);
        $refresh=trim((string)($json['refresh_token']??''));
        if($refresh==='')throw new RuntimeException('Google did not return a refresh token. Reconnect and approve offline access.');
        return $refresh;
    }

    public function listAccessibleCustomers(string $refreshToken): array
    {
        $access=$this->accessToken($refreshToken);
        $json=$this->request('GET','/customers:listAccessibleCustomers',$access);
        $resources=$json['resourceNames']??[];$accounts=[];
        if(!is_array($resources))return [];
        foreach($resources as $resource){
            $id=preg_replace('/\D/','',(string)$resource)??'';
            if($id==='')continue;
            $name='Google Ads '.$id;$manager=false;
            try{
                $detail=$this->search($id,$access,"SELECT customer.id, customer.descriptive_name, customer.manager, customer.currency_code, customer.time_zone FROM customer LIMIT 1",$id);
                $row=$detail[0]['customer']??[];
                if(is_array($row)){$name=trim((string)($row['descriptiveName']??''))?:$name;$manager=!empty($row['manager']);}
            }catch(\Throwable){/* The account is still selectable even if metadata lookup is restricted. */}
            $accounts[]=['id'=>$id,'name'=>$name,'manager'=>$manager];
        }
        return $accounts;
    }

    public function reconcile(string $refreshToken,string $customerId,string $loginCustomerId=''): array
    {
        $access=$this->accessToken($refreshToken);
        $customerId=$this->digits($customerId);
        $loginCustomerId=$this->digits($loginCustomerId);
        $rows=$this->conversionActions($customerId,$access,$loginCustomerId);
        $byName=[];foreach($rows as $row){$action=$row['conversionAction']??null;if(is_array($action)&&isset($action['name']))$byName[(string)$action['name']]=$action;}
        $report=[];
        foreach(self::EXPECTED as $key=>$definition){
            $action=$byName[$definition['name']]??null;
            if(is_array($action)){
                if(($action['type']??'')!=='WEBPAGE'){$report[$key]=['status'=>'conflict','name'=>$definition['name'],'message'=>'An action with this name exists but is not a website conversion. Nothing was changed.'];continue;}
                $report[$key]=['status'=>'present','name'=>$definition['name'],'action'=>$action];continue;
            }
            $this->createConversionAction($customerId,$access,$definition,$loginCustomerId);
            $report[$key]=['status'=>'created','name'=>$definition['name']];
        }
        $rows=$this->conversionActions($customerId,$access,$loginCustomerId);$byName=[];
        foreach($rows as $row){$action=$row['conversionAction']??null;if(is_array($action)&&isset($action['name']))$byName[(string)$action['name']]=$action;}
        $tracking=['google_tag_id'=>'','affiliate_label'=>'','product_view_label'=>'','search_label'=>''];
        foreach(self::EXPECTED as $key=>$definition){
            $action=$byName[$definition['name']]??null;if(!is_array($action))continue;
            $parsed=$this->trackingFromAction($action);
            if($tracking['google_tag_id']===''&&$parsed['google_tag_id']!=='')$tracking['google_tag_id']=$parsed['google_tag_id'];
            if($key==='affiliate')$tracking['affiliate_label']=$parsed['label'];
            if($key==='product_view')$tracking['product_view_label']=$parsed['label'];
            if($key==='search')$tracking['search_label']=$parsed['label'];
            $report[$key]['action']=$action;
        }
        return ['report'=>$report,'tracking'=>$tracking];
    }

    private function conversionActions(string $customerId,string $accessToken,string $loginCustomerId): array
    {
        return $this->search($customerId,$accessToken,"SELECT conversion_action.id, conversion_action.name, conversion_action.status, conversion_action.type, conversion_action.category, conversion_action.primary_for_goal, conversion_action.tag_snippets FROM conversion_action WHERE conversion_action.status != 'REMOVED'",$loginCustomerId);
    }

    private function createConversionAction(string $customerId,string $accessToken,array $definition,string $loginCustomerId): void
    {
        $payload=['operations'=>[['create'=>[
            'name'=>$definition['name'],'category'=>$definition['category'],'type'=>'WEBPAGE','status'=>'ENABLED',
            'primaryForGoal'=>(bool)$definition['primary'],'countingType'=>$definition['counting'],
            'valueSettings'=>['defaultValue'=>0,'defaultCurrencyCode'=>'INR','alwaysUseDefaultValue'=>true],
        ]]]];
        $this->request('POST','/customers/'.$customerId.'/conversionActions:mutate',$accessToken,$payload,$loginCustomerId);
    }

    private function search(string $customerId,string $accessToken,string $query,string $loginCustomerId=''): array
    {
        $json=$this->request('POST','/customers/'.$customerId.'/googleAds:search',$accessToken,['query'=>$query],$loginCustomerId);
        return is_array($json['results']??null)?$json['results']:[];
    }

    private function trackingFromAction(array $action): array
    {
        $text='';foreach(($action['tagSnippets']??[]) as $snippet)if(is_array($snippet))$text.=' '.(string)($snippet['eventSnippet']??'').' '.(string)($snippet['globalSiteTag']??'');
        if(preg_match('/(AW-\d+)\/([A-Za-z0-9_-]+)/',$text,$m))return ['google_tag_id'=>$m[1],'label'=>$m[2]];
        if(preg_match('/AW-\d+/',$text,$m))return ['google_tag_id'=>$m[0],'label'=>''];
        return ['google_tag_id'=>'','label'=>''];
    }

    private function accessToken(string $refreshToken): string
    {
        $cached=$_SESSION['_google_ads_access_token']??null;
        if(is_array($cached)&&(int)($cached['expires_at']??0)>time()+90&&!empty($cached['token']))return (string)$cached['token'];
        $json=$this->tokenRequest([
            'client_id'=>(string)env('GOOGLE_ADS_CLIENT_ID',''),'client_secret'=>(string)env('GOOGLE_ADS_CLIENT_SECRET',''),
            'refresh_token'=>$refreshToken,'grant_type'=>'refresh_token',
        ]);
        $token=trim((string)($json['access_token']??''));if($token==='')throw new RuntimeException('Google access-token refresh failed.');
        $_SESSION['_google_ads_access_token']=['token'=>$token,'expires_at'=>time()+max(60,(int)($json['expires_in']??3600))];return $token;
    }

    private function tokenRequest(array $fields): array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for Google Ads integration.');
        $ch=curl_init('https://oauth2.googleapis.com/token');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded','Accept: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false]);
        $response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if($response===false)throw new RuntimeException('Google OAuth request failed: '.$error);$json=json_decode((string)$response,true);
        if($status<200||$status>=300||!is_array($json))throw new RuntimeException('Google OAuth failed (HTTP '.$status.'): '.$this->errorMessage($json));return $json;
    }

    private function request(string $method,string $path,string $accessToken,?array $payload=null,string $loginCustomerId=''): array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for Google Ads integration.');
        $developerToken=trim((string)env('GOOGLE_ADS_DEVELOPER_TOKEN',''));if($developerToken==='')throw new RuntimeException('GOOGLE_ADS_DEVELOPER_TOKEN is not configured.');
        $headers=['Authorization: Bearer '.$accessToken,'developer-token: '.$developerToken,'Accept: application/json'];
        if($loginCustomerId!=='')$headers[]='login-customer-id: '.$loginCustomerId;
        $ch=curl_init('https://googleads.googleapis.com/'.self::API_VERSION.$path);$options=[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>30,CURLOPT_FOLLOWLOCATION=>false];
        if($payload!==null){$headers[]='Content-Type: application/json';$options[CURLOPT_HTTPHEADER]=$headers;$options[CURLOPT_POSTFIELDS]=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
        curl_setopt_array($ch,$options);$response=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if($response===false)throw new RuntimeException('Google Ads API request failed: '.$error);$json=json_decode((string)$response,true);
        if($status<200||$status>=300||!is_array($json))throw new RuntimeException('Google Ads API failed (HTTP '.$status.'): '.$this->errorMessage($json));return $json;
    }

    private function errorMessage(mixed $json): string
    {
        if(!is_array($json))return 'Unexpected response.';
        return (string)($json['error']['message']??$json['error_description']??$json['error']??'Request failed');
    }

    private function redirectUri(): string{return url('admin/settings/google-ads/callback');}
    private function digits(string $value): string{return preg_replace('/\D/','',$value)??'';}
}
