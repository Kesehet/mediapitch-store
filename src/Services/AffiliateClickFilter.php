<?php

declare(strict_types=1);

namespace MediaPitch\Services;

final class AffiliateClickFilter
{
    private const BOT_MARKERS=[
        'bot','crawler','spider','slurp','bingpreview','facebookexternalhit','twitterbot','linkedinbot',
        'headless','lighthouse','pagespeed','pingdom','uptimerobot','statuscake','monitoring','curl/','wget/'
    ];

    public function shouldTrack(?string $userAgent): bool
    {
        $ip=trim((string)($_SERVER['REMOTE_ADDR']??''));
        $excludedIps=array_values(array_filter(array_map('trim',explode(',',(string)env('AFFILIATE_ANALYTICS_EXCLUDE_IPS','')))));
        if($ip!=='' && in_array($ip,$excludedIps,true))return false;

        $ua=strtolower(trim((string)$userAgent));
        if($ua==='')return false;
        foreach(self::BOT_MARKERS as $marker){if(str_contains($ua,$marker))return false;}

        $extra=array_values(array_filter(array_map(static fn($v)=>strtolower(trim($v)),explode(',',(string)env('AFFILIATE_ANALYTICS_EXCLUDE_UA','')))));
        foreach($extra as $marker){if($marker!==''&&str_contains($ua,$marker))return false;}
        return true;
    }
}
