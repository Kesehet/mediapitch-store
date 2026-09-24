<?php

declare(strict_types=1);

namespace MediaPitch\Ai;

use RuntimeException;

final class WebResearcher
{
    /** @return array<int,array{url:string,title:string,excerpt:string}> */
    public function search(string $query,int $limit=4): array
    {
        $query=trim($query);
        if($query==='')return [];

        $providers=[
            [
                'name'=>'duckduckgo-html',
                'url'=>'https://html.duckduckgo.com/html/',
                'method'=>'POST',
                'body'=>http_build_query(['q'=>$query],'','&',PHP_QUERY_RFC3986),
            ],
            [
                'name'=>'duckduckgo-lite',
                'url'=>'https://lite.duckduckgo.com/lite/',
                'method'=>'POST',
                'body'=>http_build_query(['q'=>$query],'','&',PHP_QUERY_RFC3986),
            ],
        ];

        $errors=[];
        foreach($providers as $provider){
            try{
                $html=$this->fetch(
                    (string)$provider['url'],
                    200000,
                    false,
                    (string)$provider['method'],
                    (string)$provider['body'],
                    [
                        'Content-Type: application/x-www-form-urlencoded',
                        'Referer: https://html.duckduckgo.com/',
                        'Origin: https://html.duckduckgo.com',
                        'Sec-Fetch-Site: same-origin',
                        'Sec-Fetch-Mode: navigate',
                        'Sec-Fetch-Dest: document',
                    ]
                );
                $results=$this->parseSearchResults($html,$limit);
                if($results!==[])return $results;
                $errors[]=(string)$provider['name'].': no parseable results';
            }catch(RuntimeException $e){
                $errors[]=(string)$provider['name'].': '.$e->getMessage();
            }
        }

        if($errors!==[]){
            throw new RuntimeException('Web research search failed across all providers: '.implode(' | ',$errors));
        }

        return [];
    }

    public function read(string $url,int $maxChars=10000): string
    {
        if(!$this->isSafePublicUrl($url))throw new RuntimeException('Research URL is not an allowed public HTTP(S) URL.');
        $html=$this->fetch($url,400000,true);
        $html=preg_replace('#<(script|style|noscript|svg|iframe|form|nav|footer|header)[^>]*>.*?</\1>#is',' ',$html)??$html;
        $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $text=preg_replace('/\s+/u',' ',$text)??$text;
        return trim(substr($text,0,$maxChars));
    }

    /** @return array<int,array{url:string,title:string,excerpt:string}> */
    private function parseSearchResults(string $html,int $limit): array
    {
        $results=[];
        $seen=[];

        // DuckDuckGo HTML variant. Attribute order can vary.
        if(preg_match_all('#<a\b([^>]*\bclass=["\'][^"\']*result__a[^"\']*["\'][^>]*)>(.*?)</a>#is',$html,$matches,PREG_SET_ORDER)){
            foreach($matches as $match){
                $attrs=(string)$match[1];
                if(!preg_match('/\bhref=["\']([^"\']+)["\']/i',$attrs,$hrefMatch))continue;
                $this->appendResult($results,$seen,(string)$hrefMatch[1],(string)$match[2],$limit);
                if(count($results)>=$limit)return $results;
            }
        }

        // Same HTML variant when href appears before class.
        if(preg_match_all('#<a\b([^>]*\bhref=["\'][^"\']+["\'][^>]*\bclass=["\'][^"\']*result__a[^"\']*["\'][^>]*)>(.*?)</a>#is',$html,$matches,PREG_SET_ORDER)){
            foreach($matches as $match){
                $attrs=(string)$match[1];
                if(!preg_match('/\bhref=["\']([^"\']+)["\']/i',$attrs,$hrefMatch))continue;
                $this->appendResult($results,$seen,(string)$hrefMatch[1],(string)$match[2],$limit);
                if(count($results)>=$limit)return $results;
            }
        }

        // Lite output does not consistently expose result__a. Prefer DDG redirect
        // links and direct external links from the result table.
        if(preg_match_all('#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',$html,$matches,PREG_SET_ORDER)){
            foreach($matches as $match){
                $href=html_entity_decode((string)$match[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
                if(!preg_match('#(?:duckduckgo\.com/l/\?|^https?://)#i',$href))continue;

                $text=trim(html_entity_decode(strip_tags((string)$match[2]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
                if($text===''||preg_match('/^(next|previous|more results|feedback)$/i',$text))continue;

                $this->appendResult($results,$seen,$href,$text,$limit);
                if(count($results)>=$limit)return $results;
            }
        }

        return $results;
    }

    /**
     * @param array<int,array{url:string,title:string,excerpt:string}> $results
     * @param array<string,bool> $seen
     */
    private function appendResult(array &$results,array &$seen,string $href,string $titleHtml,int $limit): void
    {
        $href=html_entity_decode($href,ENT_QUOTES|ENT_HTML5,'UTF-8');
        $target=$this->duckDuckGoTarget($href);
        if(!$target||!$this->isSafePublicUrl($target)||isset($seen[$target]))return;

        $title=trim(html_entity_decode(strip_tags($titleHtml),ENT_QUOTES|ENT_HTML5,'UTF-8'));
        $title=preg_replace('/\s+/u',' ',$title)??$title;
        if($title==='')return;

        $seen[$target]=true;
        $results[]=['url'=>$target,'title'=>$title,'excerpt'=>''];
        if(count($results)>$limit)$results=array_slice($results,0,$limit);
    }

    private function duckDuckGoTarget(string $href): ?string
    {
        if(str_starts_with($href,'//'))$href='https:'.$href;
        if(str_starts_with($href,'/l/?'))$href='https://duckduckgo.com'.$href;

        if(str_contains($href,'duckduckgo.com/l/?')){
            parse_str((string)parse_url($href,PHP_URL_QUERY),$params);
            return isset($params['uddg'])?urldecode((string)$params['uddg']):null;
        }

        return preg_match('#^https?://#i',$href)?$href:null;
    }

    private function fetch(
        string $url,
        int $maxBytes,
        bool $follow,
        string $method='GET',
        ?string $body=null,
        array $extraHeaders=[]
    ): string {
        $headers=array_merge([
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
        ],$extraHeaders);

        if(function_exists('curl_init')){
            $ch=curl_init($url);if($ch===false)throw new RuntimeException('Could not initialize research HTTP client.');
            curl_setopt_array($ch,[
                CURLOPT_RETURNTRANSFER=>true,
                CURLOPT_CONNECTTIMEOUT=>10,
                CURLOPT_TIMEOUT=>25,
                CURLOPT_FOLLOWLOCATION=>$follow,
                CURLOPT_MAXREDIRS=>$follow?3:0,
                CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_ENCODING=>'',
                CURLOPT_CUSTOMREQUEST=>$method,
            ]);
            if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);

            $raw=curl_exec($ch);
            $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
            $type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
            $error=curl_error($ch);
            curl_close($ch);

            if($raw===false)throw new RuntimeException('Research request failed: '.$error);
            if($status<200||$status>=400)throw new RuntimeException('Research request returned HTTP '.$status.'.');
            if($type!==''&&!str_contains(strtolower($type),'text/html')&&!str_contains(strtolower($type),'application/xhtml+xml')){
                throw new RuntimeException('Research source is not HTML.');
            }
        }else{
            $context=stream_context_create(['http'=>[
                'method'=>$method,
                'header'=>implode("\r\n",$headers),
                'content'=>$body??'',
                'timeout'=>25,
                'ignore_errors'=>false,
                'follow_location'=>$follow?1:0,
                'max_redirects'=>$follow?3:0,
            ]]);
            $raw=@file_get_contents($url,false,$context);
            if($raw===false)throw new RuntimeException('Research request failed. Enable cURL or allow_url_fopen.');
        }

        return substr((string)$raw,0,$maxBytes);
    }

    private function isSafePublicUrl(string $url): bool
    {
        $parts=parse_url($url);
        if(!is_array($parts)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true))return false;

        $host=strtolower((string)($parts['host']??''));
        if($host===''||$host==='localhost'||str_ends_with($host,'.local'))return false;

        $ip=filter_var($host,FILTER_VALIDATE_IP)?$host:gethostbyname($host);
        if(filter_var($ip,FILTER_VALIDATE_IP)&&!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;

        return true;
    }
}
