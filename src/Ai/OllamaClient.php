<?php

declare(strict_types=1);

namespace MediaPitch\Ai;

use RuntimeException;

final class OllamaHttpException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly string $endpoint
    ) {
        parent::__construct($message, $statusCode);
    }
}

final class OllamaClient
{
    private string $lastGenerationEndpoint='';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly string $apiKey
    ) {
    }

    public function model(): string{return $this->model;}

    /**
     * Readiness is based on a real authenticated generation request.
     * Model listing is optional because the MediaPitch PHP proxy does not
     * necessarily expose Ollama's GET /api/tags endpoint.
     *
     * @return array{ok:bool,models:array<int,string>,models_supported:bool,generation_endpoint:string}
     */
    public function test(): array
    {
        $generation=$this->testGeneration();
        $models=[];
        $modelsSupported=true;

        try{
            $body=$this->request('GET','/api/tags');
            foreach(($body['models']??[]) as $row){
                if(is_array($row)&&!empty($row['name']))$models[]=(string)$row['name'];
            }
        }catch(OllamaHttpException $e){
            if(!in_array($e->statusCode,[404,405],true))throw $e;
            $modelsSupported=false;
        }

        return [
            'ok'=>true,
            'models'=>$models,
            'models_supported'=>$modelsSupported,
            'generation_endpoint'=>(string)($generation['endpoint']??$this->lastGenerationEndpoint),
        ];
    }

    /** @return array{ok:bool,model:string,endpoint:string} */
    public function testGeneration(): array
    {
        $schema=['type'=>'object','properties'=>['status'=>['type'=>'string']],'required'=>['status']];
        $result=$this->json(
            'You are a connectivity test. Return the requested JSON only.',
            'Return {"status":"ok"}.',
            $schema
        );

        if(strtolower(trim((string)($result['status']??'')))!=='ok'){
            throw new RuntimeException('Ollama model responded, but structured generation validation failed.');
        }

        return ['ok'=>true,'model'=>$this->model,'endpoint'=>$this->lastGenerationEndpoint];
    }

    public function json(string $system,string $user,array $schema): array
    {
        $chatPayload=[
            'model'=>$this->model,
            'stream'=>false,
            'format'=>$schema,
            'messages'=>[
                ['role'=>'system','content'=>$system],
                ['role'=>'user','content'=>$user],
            ],
            'options'=>['temperature'=>0.25],
        ];

        try{
            $response=$this->request('POST','/api/chat',$chatPayload);
            $this->lastGenerationEndpoint='/api/chat';
            $content=(string)($response['message']['content']??'');
        }catch(OllamaHttpException $e){
            if(!in_array($e->statusCode,[404,405],true))throw $e;

            // The MediaPitch PHP proxy and some lightweight Ollama-compatible
            // gateways expose /api/generate even when /api/chat is unavailable.
            $generatePayload=[
                'model'=>$this->model,
                'stream'=>false,
                'format'=>$schema,
                'prompt'=>"[SYSTEM]\n".$system."\n\n[USER]\n".$user."\n\nReturn valid JSON only.",
                'options'=>['temperature'=>0.25],
            ];
            $response=$this->request('POST','/api/generate',$generatePayload);
            $this->lastGenerationEndpoint='/api/generate';
            $content=(string)($response['response']??'');
        }

        if(trim($content)==='')throw new RuntimeException('Ollama returned an empty response.');

        return $this->decodeStructuredJson($content);
    }

    /** @return array<string,mixed> */
    private function decodeStructuredJson(string $content): array
    {
        $content=trim($content);
        $decoded=json_decode($content,true);
        if(is_array($decoded))return $decoded;

        // Some proxy-backed models still wrap JSON in Markdown despite the
        // requested format. Tolerate that without weakening JSON validation.
        if(str_starts_with($content,'```')){
            $content=preg_replace('/^\`\`\`(?:json)?\s*/i','',$content)??$content;
            $content=preg_replace('/\s*\`\`\`$/','',$content)??$content;
            $decoded=json_decode(trim($content),true);
            if(is_array($decoded))return $decoded;
        }

        $first=strpos($content,'{');
        $last=strrpos($content,'}');
        if($first!==false&&$last!==false&&$last>$first){
            $candidate=substr($content,$first,$last-$first+1);
            $decoded=json_decode($candidate,true);
            if(is_array($decoded))return $decoded;
        }

        throw new RuntimeException('Ollama returned text, but it was not valid structured JSON.');
    }

    /** @return array<string,mixed> */
    private function request(string $method,string $path,?array $payload=null): array
    {
        $base=rtrim(trim($this->baseUrl),'/');
        if(!preg_match('#^https?://#i',$base)){
            throw new RuntimeException('Ollama URL must start with http:// or https://.');
        }
        if(trim($this->apiKey)==='')throw new RuntimeException('Ollama API key is required.');

        $url=$base.$path;
        $body=$payload===null
            ? null
            : json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);

        $headers=[
            'Accept: application/json',
            'Authorization: Bearer '.$this->apiKey,
        ];
        if($body!==null)$headers[]='Content-Type: application/json';

        $contentType='';

        if(function_exists('curl_init')){
            $ch=curl_init($url);
            if($ch===false)throw new RuntimeException('Could not initialize HTTP client.');

            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CONNECTTIMEOUT=>10,
                CURLOPT_TIMEOUT=>240,
                CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_CUSTOMREQUEST=>$method,
                CURLOPT_FOLLOWLOCATION=>true,
                CURLOPT_MAXREDIRS=>3,
            ]);
            if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);

            $raw=curl_exec($ch);
            $error=curl_error($ch);
            $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            $contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if($raw===false)throw new RuntimeException('Ollama request failed: '.$error);
        }else{
            $context=stream_context_create(['http'=>[
                'method'=>$method,
                'header'=>implode("\r\n",$headers),
                'content'=>$body??'',
                'timeout'=>240,
                'ignore_errors'=>true,
                'follow_location'=>1,
                'max_redirects'=>3,
            ]]);
            $raw=@file_get_contents($url,false,$context);
            if($raw===false)throw new RuntimeException('Ollama request failed. Enable cURL or allow_url_fopen.');

            $status=200;
            foreach(($http_response_header??[]) as $header){
                if(preg_match('/^HTTP\/\S+\s+(\d{3})\b/i',$header,$m))$status=(int)$m[1];
                if(stripos($header,'Content-Type:')===0)$contentType=trim(substr($header,13));
            }
        }

        $raw=(string)$raw;
        $decoded=json_decode($raw,true);

        if($status<200||$status>=300){
            $message=$this->errorMessage($path,$status,$contentType,$decoded,$raw);
            throw new OllamaHttpException($message,$status,$path);
        }

        if(!is_array($decoded)){
            if(str_contains(strtolower($contentType),'text/html')||preg_match('/^\s*<!doctype\s+html|^\s*<html/i',$raw)){
                throw new RuntimeException(
                    'Ollama endpoint '.$path.' returned HTML instead of JSON. '.
                    'Check that the configured URL points to the Ollama proxy API base.'
                );
            }
            throw new RuntimeException('Ollama endpoint '.$path.' returned invalid JSON.');
        }

        return $decoded;
    }

    private function errorMessage(
        string $path,
        int $status,
        string $contentType,
        mixed $decoded,
        string $raw
    ): string {
        if(is_array($decoded)){
            $apiMessage=trim((string)($decoded['error']??$decoded['message']??''));
            if($apiMessage!==''){
                return 'Ollama '.$path.' returned HTTP '.$status.': '.substr($apiMessage,0,500);
            }
        }

        $isHtml=str_contains(strtolower($contentType),'text/html')
            ||preg_match('/^\s*<!doctype\s+html|^\s*<html/i',$raw)===1;

        if($isHtml){
            return 'Ollama '.$path.' returned HTTP '.$status.' with an HTML page instead of API JSON.';
        }

        $excerpt=trim(preg_replace('/\s+/',' ',strip_tags($raw))??'');
        if($excerpt!=='')return 'Ollama '.$path.' returned HTTP '.$status.': '.substr($excerpt,0,300);

        return 'Ollama '.$path.' returned HTTP '.$status.'.';
    }
}
