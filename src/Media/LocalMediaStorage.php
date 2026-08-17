<?php

declare(strict_types=1);

namespace MediaPitch\Media;

use RuntimeException;

final class LocalMediaStorage implements MediaStorage
{
    /** @return array{relative_path:string,absolute_path:string,file_name:string} */
    public function storeUploaded(string $tmpPath,string $extension): array
    {
        $year=gmdate('Y');
        $month=gmdate('m');
        $relativeDir='/uploads/'.$year.'/'.$month;
        $publicDir=dirname(__DIR__,2).'/public'.$relativeDir;
        if(!is_dir($publicDir) && !mkdir($publicDir,0755,true) && !is_dir($publicDir)){
            throw new RuntimeException('Upload directory could not be created.');
        }

        $fileName=bin2hex(random_bytes(16)).'.'.$extension;
        $absolutePath=$publicDir.'/'.$fileName;
        if(!move_uploaded_file($tmpPath,$absolutePath)){
            throw new RuntimeException('Image could not be stored.');
        }

        return [
            'relative_path'=>$relativeDir.'/'.$fileName,
            'absolute_path'=>$absolutePath,
            'file_name'=>$fileName,
        ];
    }

    public function delete(string $relativePath): void
    {
        $relativePath='/'.ltrim($relativePath,'/');
        if(!str_starts_with($relativePath,'/uploads/')) return;
        $absolute=dirname(__DIR__,2).'/public'.$relativePath;
        if(is_file($absolute)) @unlink($absolute);
    }
}
