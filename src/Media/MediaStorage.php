<?php

declare(strict_types=1);

namespace MediaPitch\Media;

interface MediaStorage
{
    /** @return array{relative_path:string,absolute_path:string,file_name:string} */
    public function storeUploaded(string $tmpPath,string $extension): array;

    public function delete(string $relativePath): void;
}
