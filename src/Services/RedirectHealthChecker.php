<?php

declare(strict_types=1);

namespace MediaPitch\Services;

final class RedirectHealthChecker
{
    public function check(array $redirect): array
    {
        $results=$this->checkMany([$redirect]);
        $id=(int)($redirect['id']??0);
        return $results[$id] ?? [
            'id'=>$id,
            'target'=>(string)($redirect['to_url']??''),
            'http_status'=>null,
            'state'=>'warning',
            'error'=>'Redirect could not be checked.',
        ];
    }

    public function checkMany(array $redirects): array
    {
        if(!function_exists('curl_multi_init') || !function_exists('curl_init')){
            $out=[];
            foreach($redirects as $redirect){
                $id=(int)($redirect['id']??0);
                $out[$id]=[
                    'id'=>$id,
                    'target'=>(string)($redirect['to_url']??''),
                    'http_status'=>null,
                    'state'=>'warning',
                    'error'=>'PHP cURL extension is unavailable on this server.',
                ];
            }
            return $out;
        }

        $results=[];
        foreach(array_chunk($redirects,25) as $batch){
            $multi=curl_multi_init();
            $handles=[];

            foreach($batch as $redirect){
                $id=(int)($redirect['id']??0);
                $target=$this->targetUrl((string)($redirect['to_url']??''));
                if($id<1 || $target===null){
                    $results[$id]=[
                        'id'=>$id,
                        'target'=>(string)($redirect['to_url']??''),
                        'http_status'=>null,
                        'state'=>'warning',
                        'error'=>'Destination must be an HTTP(S) URL or site-relative path.',
                    ];
                    continue;
                }

                $ch=curl_init($target);
                curl_setopt_array($ch,[
                    CURLOPT_RETURNTRANSFER=>true,
                    CURLOPT_FOLLOWLOCATION=>true,
                    CURLOPT_MAXREDIRS=>5,
                    CURLOPT_CONNECTTIMEOUT=>4,
                    CURLOPT_TIMEOUT=>10,
                    CURLOPT_NOBODY=>true,
                    CURLOPT_USERAGENT=>'MediaPitch-Redirect-Health/1.0',
                    CURLOPT_SSL_VERIFYPEER=>true,
                    CURLOPT_SSL_VERIFYHOST=>2,
                ]);
                curl_multi_add_handle($multi,$ch);
                $handles[$id]=['handle'=>$ch,'target'=>$target];
            }

            do{
                $status=curl_multi_exec($multi,$running);
                if($running) curl_multi_select($multi,1.0);
            }while($running && $status===CURLM_OK);

            foreach($handles as $id=>$item){
                $ch=$item['handle'];
                $error=curl_error($ch);
                $httpStatus=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
                $finalUrl=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);

                $state='warning';
                if(in_array($httpStatus,[404,410],true)) $state='broken';
                elseif($httpStatus>=200 && $httpStatus<400) $state='healthy';

                $results[$id]=[
                    'id'=>$id,
                    'target'=>$item['target'],
                    'final_url'=>$finalUrl!==''?$finalUrl:$item['target'],
                    'http_status'=>$httpStatus>0?$httpStatus:null,
                    'state'=>$state,
                    'error'=>$error!==''?$error:null,
                ];

                curl_multi_remove_handle($multi,$ch);
                curl_close($ch);
            }
            curl_multi_close($multi);
        }

        return $results;
    }

    private function targetUrl(string $toUrl): ?string
    {
        $toUrl=trim($toUrl);
        if($toUrl==='') return null;
        if(str_starts_with($toUrl,'/')) return url(ltrim($toUrl,'/'));

        $parts=parse_url($toUrl);
        $scheme=strtolower((string)($parts['scheme']??''));
        if(!in_array($scheme,['http','https'],true) || empty($parts['host'])) return null;
        return $toUrl;
    }
}
