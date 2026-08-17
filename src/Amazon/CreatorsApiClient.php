<?php

declare(strict_types=1);

namespace MediaPitch\Amazon;

use RuntimeException;

final class CreatorsApiClient
{
    public function testCredentials(array $settings): array
    {
        $id=trim((string)($settings['credential_id']??''));
        $secret=trim((string)($settings['credential_secret']??''));
        $version=trim((string)($settings['credential_version']??''));
        if($id===''||$secret===''||$version==='')throw new RuntimeException('Credential ID, secret and version are required.');
        if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL extension is required for Creators API requests.');

        [$endpoint,$body,$contentType]=$this->tokenRequest($version,$id,$secret);
        $ch=curl_init($endpoint);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$body,
            CURLOPT_HTTPHEADER=>['Content-Type: '.$contentType,'Accept: application/json'],
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_TIMEOUT=>15,
            CURLOPT_FOLLOWLOCATION=>false,
        ]);
        $response=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $error=curl_error($ch);
        curl_close($ch);
        if($response===false)throw new RuntimeException('Amazon authentication request failed: '.$error);
        $json=json_decode((string)$response,true);
        if($status<200||$status>=300||!is_array($json)||empty($json['access_token'])){
            $message=is_array($json)?(string)($json['error_description']??$json['message']??$json['error']??'Authentication failed'):'Authentication failed';
            throw new RuntimeException('Amazon authentication failed (HTTP '.$status.'): '.$message);
        }
        return ['ok'=>true,'expires_in'=>(int)($json['expires_in']??0),'token_type'=>(string)($json['token_type']??'bearer')];
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
        if(str_starts_with($version,'2.')){
            return [$endpoints[$version],http_build_query(['grant_type'=>'client_credentials','client_id'=>$id,'client_secret'=>$secret,'scope'=>'creatorsapi/default']),'application/x-www-form-urlencoded'];
        }
        return [$endpoints[$version],json_encode(['grant_type'=>'client_credentials','client_id'=>$id,'client_secret'=>$secret,'scope'=>'creatorsapi::default'],JSON_THROW_ON_ERROR),'application/json'];
    }
}
