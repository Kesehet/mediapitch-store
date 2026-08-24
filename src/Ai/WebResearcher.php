<?php

declare(strict_types=1);

namespace MediaPitch\Ai;

use RuntimeException;

final class WebResearcher
{
    /** @return array<int,array{url:string,title:string,excerpt:string}> */
    public function search(string $query,int $limit=4): array
    {
        $url='https://html.duckduckgo.com/html/?q='.rawurlencode($query);
        $html=$this->fetch($url,150000,false);
        $results=[];
        if(preg_match_all('#<a[^>]+class="[^"]*result__a[^"]*"[^>]+href="([^"]+)"[^>]*>(.*?)</a>#is',$html,$matches,PREG_SET_ORDER)){
            foreach($matches as $match){
                $href=html_entity_decode((string)$match[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
                $target=$this->duckDuckGoTarget($href);if(!$target||!$this->isSafePublicUrl($target))continue;
                $title=trim(html_entity_decode(strip_tags((string)$match[2]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
                if($title==='')continue;
                $results[]=['url'=>$target,'title'=>$title,'excerpt'=>''];
                if(count($results)>=$limit)break;
            }
        }
        return $results;
    }

    public function read(string $url,int $maxChars=10000): string
    {
        if(!$this->isSafePublicUrl($url))throw new RuntimeException('Research URL is not an allowed public HTTP(S) URL.');
        $html=$this->fetch($url,400000,false);
        $html=preg_replace('#<(script|style|noscript|svg|iframe|form|nav|footer|header)[^>]*>.*?</\1>#is',' ',$html)??$html;
        $text=html_entity_decode(strip_tags($html),ENT_QUOTES|ENT_HTML5,'UTF-8');
        $text=preg_replace('/\s+/u',' ',$text)??$text;
        return trim(substr($text,0,$maxChars));
    }

    private function duckDuckGoTarget(string $href): ?string
    {
        if(str_starts_with($href,'//'))$href='https:'.$href;
        if(str_contains($href,'duckduckgo.com/l/?')){
            parse_str((string)parse_url($href,PHP_URL_QUERY),$params);
            return isset($params['uddg'])?urldecode((string)$params['uddg']):null;
        }
        return preg_match('#^https?://#i',$href)?$href:null;
    }

    private function fetch(string $url,int $maxBytes,bool $follow): string
    {
        $headers=['User-Agent: Mozilla/5.0 (compatible; MediaPitchResearchBot/1.0; +https://store.mediapitch.in/)','Accept: text/html,application/xhtml+xml'];
        if(function_exists('curl_init')){
            $ch=curl_init($url);if($ch===false)throw new RuntimeException('Could not initialize research HTTP client.');
            curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_FOLLOWLOCATION=>$follow,CURLOPT_MAXREDIRS=>$follow?3:0,CURLOPT_HTTPHEADER=>$headers,CURLOPT_ENCODING=>'']);
            $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);$error=curl_error($ch);curl_close($ch);
            if($raw===false)throw new RuntimeException('Research request failed: '.$error);
            if($status<200||$status>=400)throw new RuntimeException('Research request returned HTTP '.$status.'.');
            if($type!==''&&!str_contains(strtolower($type),'text/html'))throw new RuntimeException('Research source is not HTML.');
        }else{
            $context=stream_context_create(['http'=>['method'=>'GET','header'=>implode("\r\n",$headers),'timeout'=>25,'ignore_errors'=>false,'follow_location'=>$follow?1:0,'max_redirects'=>$follow?3:0]]);
            $raw=@file_get_contents($url,false,$context);if($raw===false)throw new RuntimeException('Research request failed. Enable cURL or allow_url_fopen.');
        }
        return substr((string)$raw,0,$maxBytes);
    }

    private function isSafePublicUrl(string $url): bool
    {
        $parts=parse_url($url);if(!is_array($parts)||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true))return false;
        $host=strtolower((string)($parts['host']??''));if($host===''||$host==='localhost'||str_ends_with($host,'.local'))return false;
        $ip=filter_var($host,FILTER_VALIDATE_IP)?$host:gethostbyname($host);
        if(filter_var($ip,FILTER_VALIDATE_IP) && !filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))return false;
        return true;
    }
}
