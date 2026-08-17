<?php

declare(strict_types=1);

namespace MediaPitch\Core;

use RuntimeException;

final class SecretBox
{
    public static function encrypt(string $plain): string
    {
        if ($plain === '') return '';
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL is required to store API secrets securely.');
        }
        $key=self::key();
        $iv=random_bytes(12);
        $tag='';
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag,'mediapitch-settings');
        if($cipher===false)throw new RuntimeException('Could not encrypt secret.');
        return base64_encode(json_encode([
            'v'=>1,
            'iv'=>base64_encode($iv),
            'tag'=>base64_encode($tag),
            'data'=>base64_encode($cipher),
        ],JSON_THROW_ON_ERROR));
    }

    public static function decrypt(?string $encoded): string
    {
        $encoded=(string)$encoded;
        if($encoded==='')return '';
        if(!function_exists('openssl_decrypt'))return '';
        try{
            $payload=json_decode((string)base64_decode($encoded,true),true,512,JSON_THROW_ON_ERROR);
            $plain=openssl_decrypt(
                (string)base64_decode((string)($payload['data']??''),true),
                'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,
                (string)base64_decode((string)($payload['iv']??''),true),
                (string)base64_decode((string)($payload['tag']??''),true),
                'mediapitch-settings'
            );
            return $plain===false?'':$plain;
        }catch(\Throwable){
            return '';
        }
    }

    private static function key(): string
    {
        $appKey=trim((string)env('APP_KEY',''));
        if(strlen($appKey)<24||str_contains($appKey,'change-this')){
            throw new RuntimeException('Set a strong APP_KEY before saving encrypted credentials.');
        }
        return hash('sha256',$appKey,true);
    }
}
