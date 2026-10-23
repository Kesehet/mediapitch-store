<?php
declare(strict_types=1);
namespace MediaPitch\Services;

/** Single destination policy shared by CMS, public buttons and redirects. */
final class ProductLinks
{
    public static function destination(array $product): ?string
    {
        foreach (['affiliate_url', 'amazon_url'] as $field) {
            $url=trim((string)($product[$field]??''));
            if($url==='')continue;
            $scheme=strtolower((string)(parse_url($url,PHP_URL_SCHEME)?:''));
            if(in_array($scheme,['https','http'],true) && filter_var($url,FILTER_VALIDATE_URL)!==false)return $url;
        }
        return null;
    }
}
