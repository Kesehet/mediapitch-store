<?php

declare(strict_types=1);

namespace MediaPitch\Ai;

use RuntimeException;

final class OllamaClient
{
    public function __construct(private readonly string $baseUrl,private readonly string $model,private readonly string $apiKey){}

    public function model(): string{return $this->model;}

    public function test(): array
    {
        $body=$this->request('GET','/api/tags');
        $models=[];
        foreach(($body['models']??[]) as $row)if(is_array($row)&&!empty($row['name']))$models[]=(string)$row['name'];
        return ['ok'=>true,'models'=>$models];
    }

    public function json(string $system,string $user,array $schema): array
    {
        $payload=[
            'model'=>$this->model,
            'stream'=>false,
            'format'=>$schema,
            'messages'=>[
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>$user],
            ],
            'options'=>['temperature'=>0.25],
        ];
        $response=$this->request('POST','/api/chat',$payload);
        $content=(string)($response['message']['content']??'');
        if($content==='')throw new RuntimeException('Ollama returned an empty response.');
        $decoded=json_decode($content,true);
        if(!is_array($decoded))throw new RuntimeException('Ollama returned invalid structured JSON.');
        return $decoded;
    }

    private function request(string $method,string $path,?array $payload=null): array
    {
        $base=rtrim(trim($this->baseUrl),'/');
        if(!preg_match('#^https?://#i',$base))throw new RuntimeException('Ollama URL must start with http:// or https://.');
        if(trim($this->apiKey)==='')throw new RuntimeException('Ollama API key is required.');
        $url=$base.$path;
        $body=$payload===null?null:json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $headers=['Accept: application/json','Authorization: Bearer '.$this->apiKey];if($body!==null)$headers[]='Content-Type: application/json';
        if(function_exists('curl_init')){
            $ch=curl_init($url);if($ch===false)throw new RuntimeException('Could not initialize HTTP client.');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>240,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CUSTOMREQUEST=>$method]);
            if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
            $raw=curl_exec($ch);$error=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
            if($raw===false)throw new RuntimeException('Ollama request failed: '.$error);
        }else{
            $context=stream_context_create(['http'=>['method'=>$method,'header'=>implode("\r\n",$headers),'content'=>$body??'','timeout'=>240,'ignore_errors'=>true]]);
            $raw=@file_get_contents($url,false,$context);if($raw===false)throw new RuntimeException('Ollama request failed. Enable cURL or allow_url_fopen.');
            $status=200;if(isset($http_response_header[0])&&preg_match('/\s(\d{3})\s/',$http_response_header[0],$m))$status=(int)$m[1];
        }
        $decoded=json_decode((string)$raw,true);
        if($status<200||$status>=300){$message=is_array($decoded)?(string)($decoded['error']??$raw):(string)$raw;throw new RuntimeException('Ollama HTTP '.$status.': '.substr($message,0,1000));}
        if(!is_array($decoded))throw new RuntimeException('Ollama returned invalid JSON.');
        return $decoded;
    }
}
