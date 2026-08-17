<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\MediaRepository;
use Throwable;

final class MediaAdminController
{
    public function __construct(private readonly MediaRepository $repo) {}

    public function handle(string $method, string $path): bool
    {
        if (!str_starts_with($path, '/admin/media')) return false;
        if (!Auth::check()) $this->redirect('/admin/login');

        if ($path === '/admin/media' && $method === 'GET') {
            View::render('admin/media', [
                'pageTitle'=>'Media',
                'adminUser'=>Auth::user(),
                'items'=>$this->repo->all(),
                'success'=>$this->flash('success'),
                'error'=>$this->flash('error'),
            ], 'admin/layout');
            return true;
        }

        if ($path === '/admin/media/upload' && $method === 'POST') {
            if (!Auth::canManageProducts()) { http_response_code(403); exit('Forbidden'); }
            if (!Csrf::validate(isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : null)) {
                http_response_code(419); exit('Invalid or expired form token.');
            }

            try {
                $this->storeUpload($_FILES['image'] ?? [], trim((string)($_POST['alt_text'] ?? '')));
                $this->setFlash('success','Image uploaded.');
            } catch (Throwable $e) {
                $this->setFlash('error',$e->getMessage());
            }
            $this->redirect('/admin/media');
        }

        return false;
    }

    private function storeUpload(array $file, string $altText): void
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Choose an image to upload.');
        }
        $size=(int)($file['size'] ?? 0);
        if ($size < 1 || $size > 5 * 1024 * 1024) {
            throw new \RuntimeException('Images must be 5 MB or smaller.');
        }

        $tmp=(string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('Invalid upload.');
        }

        $finfo=new \finfo(FILEINFO_MIME_TYPE);
        $mime=(string)$finfo->file($tmp);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if (!isset($extensions[$mime])) {
            throw new \RuntimeException('Only JPEG, PNG, WebP and GIF images are allowed.');
        }

        $imageInfo=@getimagesize($tmp);
        if ($imageInfo === false) {
            throw new \RuntimeException('The uploaded file is not a valid image.');
        }

        $year=gmdate('Y'); $month=gmdate('m');
        $relativeDir='/uploads/' . $year . '/' . $month;
        $publicDir=dirname(__DIR__,2) . '/public' . $relativeDir;
        if (!is_dir($publicDir) && !mkdir($publicDir,0755,true) && !is_dir($publicDir)) {
            throw new \RuntimeException('Upload directory could not be created.');
        }

        $name=bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $destination=$publicDir . '/' . $name;
        if (!move_uploaded_file($tmp,$destination)) {
            throw new \RuntimeException('Image could not be stored.');
        }

        $thumbnailPath=null;
        $optimized=false;
        try {
            [$thumbnailPath,$optimized]=$this->createThumbnail($destination,$relativeDir,$mime,(int)$imageInfo[0],(int)$imageInfo[1]);
        } catch (Throwable $thumbnailError) {
            error_log('MediaPitch thumbnail generation failed: ' . $thumbnailError->getMessage());
        }

        $this->repo->create([
            'uploaded_by'=>(int)(Auth::user()['id'] ?? 0) ?: null,
            'original_name'=>substr((string)($file['name'] ?? 'image'),0,255),
            'file_name'=>$name,
            'file_path'=>$relativeDir . '/' . $name,
            'thumbnail_path'=>$thumbnailPath,
            'optimized'=>$optimized,
            'mime_type'=>$mime,
            'file_size'=>(int)(filesize($destination) ?: $size),
            'width'=>(int)$imageInfo[0],
            'height'=>(int)$imageInfo[1],
            'alt_text'=>$altText !== '' ? $altText : null,
        ]);
    }

    private function createThumbnail(string $sourcePath,string $relativeDir,string $mime,int $width,int $height): array
    {
        if (!extension_loaded('gd') || $width < 1 || $height < 1) return [null,false];

        $loader=match($mime){
            'image/jpeg'=>'imagecreatefromjpeg',
            'image/png'=>'imagecreatefrompng',
            'image/webp'=>'imagecreatefromwebp',
            'image/gif'=>'imagecreatefromgif',
            default=>null,
        };
        if ($loader===null || !function_exists($loader)) return [null,false];

        $source=@$loader($sourcePath);
        if ($source===false) return [null,false];

        $maxWidth=480;
        $scale=min(1,$maxWidth/$width);
        $thumbWidth=max(1,(int)round($width*$scale));
        $thumbHeight=max(1,(int)round($height*$scale));
        $thumb=imagecreatetruecolor($thumbWidth,$thumbHeight);
        if ($thumb===false) { imagedestroy($source); return [null,false]; }

        if (in_array($mime,['image/png','image/webp','image/gif'],true)) {
            imagealphablending($thumb,false);
            imagesavealpha($thumb,true);
            $transparent=imagecolorallocatealpha($thumb,0,0,0,127);
            imagefilledrectangle($thumb,0,0,$thumbWidth,$thumbHeight,$transparent);
        }
        imagecopyresampled($thumb,$source,0,0,0,0,$thumbWidth,$thumbHeight,$width,$height);

        $thumbName='thumb-' . pathinfo($sourcePath,PATHINFO_FILENAME) . '.webp';
        $thumbAbsolute=dirname($sourcePath) . '/' . $thumbName;
        $saved=function_exists('imagewebp') ? imagewebp($thumb,$thumbAbsolute,82) : false;
        imagedestroy($thumb);
        imagedestroy($source);

        if (!$saved) return [null,false];
        return [$relativeDir . '/' . $thumbName,true];
    }

    private function redirect(string $path): never { header('Location: ' . url($path)); exit; }
    private function setFlash(string $key,string $value): void { $_SESSION['_flash'][$key]=$value; }
    private function flash(string $key): ?string { $v=$_SESSION['_flash'][$key]??null; unset($_SESSION['_flash'][$key]); return is_string($v)?$v:null; }
}
