<?php

declare(strict_types=1);

namespace MediaPitch\Media;

final class ImageOptimizer
{
    /** @return array{optimized:bool,width:int,height:int,file_size:int} */
    public function optimize(string $path,string $mime,int $width,int $height): array
    {
        $fileSize=(int)(filesize($path)?:0);
        if(!extension_loaded('gd') || $width<1 || $height<1 || $mime==='image/gif'){
            return ['optimized'=>false,'width'=>$width,'height'=>$height,'file_size'=>$fileSize];
        }

        $loader=match($mime){
            'image/jpeg'=>'imagecreatefromjpeg',
            'image/png'=>'imagecreatefrompng',
            'image/webp'=>'imagecreatefromwebp',
            default=>null,
        };
        if($loader===null || !function_exists($loader)){
            return ['optimized'=>false,'width'=>$width,'height'=>$height,'file_size'=>$fileSize];
        }

        $source=@$loader($path);
        if($source===false){
            return ['optimized'=>false,'width'=>$width,'height'=>$height,'file_size'=>$fileSize];
        }

        $maxDimension=max(800,(int)env('MEDIA_MAX_DIMENSION',2000));
        $scale=min(1,$maxDimension/max($width,$height));
        $targetWidth=max(1,(int)round($width*$scale));
        $targetHeight=max(1,(int)round($height*$scale));
        $target=imagecreatetruecolor($targetWidth,$targetHeight);
        if($target===false){imagedestroy($source);return ['optimized'=>false,'width'=>$width,'height'=>$height,'file_size'=>$fileSize];}

        if(in_array($mime,['image/png','image/webp'],true)){
            imagealphablending($target,false);
            imagesavealpha($target,true);
            $transparent=imagecolorallocatealpha($target,0,0,0,127);
            imagefilledrectangle($target,0,0,$targetWidth,$targetHeight,$transparent);
        }
        imagecopyresampled($target,$source,0,0,0,0,$targetWidth,$targetHeight,$width,$height);

        $temp=$path.'.opt';
        $saved=match($mime){
            'image/jpeg'=>function_exists('imagejpeg')?imagejpeg($target,$temp,max(55,min(95,(int)env('MEDIA_JPEG_QUALITY',84)))):false,
            'image/png'=>function_exists('imagepng')?imagepng($target,$temp,max(0,min(9,(int)env('MEDIA_PNG_COMPRESSION',6)))):false,
            'image/webp'=>function_exists('imagewebp')?imagewebp($target,$temp,max(55,min(95,(int)env('MEDIA_WEBP_QUALITY',84)))):false,
            default=>false,
        };
        imagedestroy($target);
        imagedestroy($source);

        if(!$saved || !is_file($temp)){
            @unlink($temp);
            return ['optimized'=>false,'width'=>$width,'height'=>$height,'file_size'=>$fileSize];
        }

        $optimizedSize=(int)(filesize($temp)?:0);
        if($optimizedSize<1 || ($scale===1.0 && $optimizedSize>=$fileSize)){
            @unlink($temp);
            return ['optimized'=>false,'width'=>$width,'height'=>$height,'file_size'=>$fileSize];
        }

        if(!@rename($temp,$path)){
            @unlink($temp);
            return ['optimized'=>false,'width'=>$width,'height'=>$height,'file_size'=>$fileSize];
        }

        return ['optimized'=>true,'width'=>$targetWidth,'height'=>$targetHeight,'file_size'=>$optimizedSize];
    }
}
