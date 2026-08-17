<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\Database;
use MediaPitch\Core\View;
use MediaPitch\Media\ImageOptimizer;
use MediaPitch\Media\LocalMediaStorage;
use MediaPitch\Media\MediaStorage;
use MediaPitch\Repositories\MediaRepository;
use PDO;
use Throwable;

final class MediaAdminController
{
    private MediaStorage $storage;

    public function __construct(private readonly MediaRepository $repo, ?MediaStorage $storage=null)
    {
        $this->storage=$storage ?? new LocalMediaStorage();
    }

    public function handle(string $method, string $path): bool
    {
        if (!str_starts_with($path, '/admin/media')) return false;
        if (!Auth::check()) $this->redirect('/admin/login');
        if (!Auth::canUploadMedia()) { http_response_code(403); exit('Forbidden'); }

        if ($path === '/admin/media' && $method === 'GET') {
            $query=trim((string)($_GET['q']??''));
            $categories=Auth::canManageProducts()
                ? Database::connection()->query('SELECT id,name,image_url FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC)
                : [];
            View::render('admin/media', [
                'pageTitle'=>'Media',
                'adminUser'=>Auth::user(),
                'items'=>$this->repo->all(100,$query),
                'query'=>$query,
                'categories'=>$categories,
                'success'=>$this->flash('success'),
                'error'=>$this->flash('error'),
            ], 'admin/layout');
            return true;
        }

        if ($path === '/admin/media/upload' && $method === 'POST') {
            $this->requireCsrf();
            try {
                $stored=$this->storeUpload($_FILES['image'] ?? [], trim((string)($_POST['alt_text'] ?? '')));
                Audit::record('media.upload','media',$stored['id']??null,'Uploaded media item',[
                    'original_name'=>$stored['original_name']??'','file_path'=>$stored['file_path']??'','mime_type'=>$stored['mime_type']??'','file_size'=>$stored['file_size']??0,'optimized'=>!empty($stored['optimized']),
                ]);
                $this->setFlash('success','Image uploaded'.(!empty($stored['optimized'])?' and optimized.':'.'));
            } catch (Throwable $e) {
                $this->setFlash('error',$e->getMessage());
            }
            $this->redirect('/admin/media');
        }

        if ($path === '/admin/media/delete' && $method === 'POST') {
            if (!Auth::canManageProducts()) { http_response_code(403); exit('Forbidden'); }
            $this->requireCsrf();
            try {
                $id=(int)($_POST['id']??0);
                $item=$this->repo->deleteIfUnused($id);
                $this->storage->delete((string)$item['file_path']);
                if(!empty($item['thumbnail_path'])) $this->storage->delete((string)$item['thumbnail_path']);
                Audit::record('media.delete','media',$id,'Deleted unused media item',['file_path'=>$item['file_path']??'']);
                $this->setFlash('success','Media item deleted.');
            } catch (Throwable $e) {
                $this->setFlash('error',$e->getMessage());
            }
            $this->redirect('/admin/media');
        }

        if ($path === '/admin/media/assign-category' && $method === 'POST') {
            if (!Auth::canManageProducts()) { http_response_code(403); exit('Forbidden'); }
            $this->requireCsrf();
            $categoryId=(int)($_POST['category_id'] ?? 0);
            $imageUrl=trim((string)($_POST['image_url'] ?? ''));
            if($categoryId<1 || $imageUrl==='') {
                $this->setFlash('error','Choose a category and image.');
                $this->redirect('/admin/media');
            }
            $stmt=Database::connection()->prepare('UPDATE categories SET image_url=:image_url WHERE id=:id');
            $stmt->execute(['image_url'=>$imageUrl,'id'=>$categoryId]);
            Audit::record('category.image.assign','category',$categoryId,'Assigned category image',['image_url'=>$imageUrl]);
            $this->setFlash('success','Category image updated.');
            $this->redirect('/admin/media');
        }

        return false;
    }

    private function storeUpload(array $file, string $altText): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new \RuntimeException('Choose an image to upload.');
        $size=(int)($file['size'] ?? 0);
        $maxUpload=max(1,(int)env('MEDIA_MAX_UPLOAD_MB',5))*1024*1024;
        if ($size < 1 || $size > $maxUpload) throw new \RuntimeException('Images must be '.max(1,(int)env('MEDIA_MAX_UPLOAD_MB',5)).' MB or smaller.');

        $tmp=(string)($file['tmp_name'] ?? '');
        if (!is_uploaded_file($tmp)) throw new \RuntimeException('Invalid upload.');

        $finfo=new \finfo(FILEINFO_MIME_TYPE);
        $mime=(string)$finfo->file($tmp);
        $extensions=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
        if (!isset($extensions[$mime])) throw new \RuntimeException('Only JPEG, PNG, WebP and GIF images are allowed.');

        $imageInfo=@getimagesize($tmp);
        if ($imageInfo === false) throw new \RuntimeException('The uploaded file is not a valid image.');

        $stored=$this->storage->storeUploaded($tmp,$extensions[$mime]);
        $destination=$stored['absolute_path'];
        $relativePath=$stored['relative_path'];
        $relativeDir=str_replace('\\','/',dirname($relativePath));

        $optimizedResult=(new ImageOptimizer())->optimize($destination,$mime,(int)$imageInfo[0],(int)$imageInfo[1]);
        $width=$optimizedResult['width'];
        $height=$optimizedResult['height'];
        $optimized=$optimizedResult['optimized'];

        $thumbnailPath=null;
        try {
            [$thumbnailPath,$thumbOptimized]=$this->createThumbnail($destination,$relativeDir,$mime,$width,$height);
            $optimized=$optimized || $thumbOptimized;
        } catch (Throwable $thumbnailError) {
            error_log('MediaPitch thumbnail generation failed: ' . $thumbnailError->getMessage());
        }

        $payload=[
            'uploaded_by'=>(int)(Auth::user()['id'] ?? 0) ?: null,
            'original_name'=>substr((string)($file['name'] ?? 'image'),0,255),
            'file_name'=>$stored['file_name'],
            'file_path'=>$relativePath,
            'thumbnail_path'=>$thumbnailPath,
            'optimized'=>$optimized,
            'mime_type'=>$mime,
            'file_size'=>$optimizedResult['file_size'] ?: (int)(filesize($destination) ?: $size),
            'width'=>$width,
            'height'=>$height,
            'alt_text'=>$altText !== '' ? $altText : null,
        ];
        $payload['id']=$this->repo->create($payload);
        return $payload;
    }

    private function createThumbnail(string $sourcePath,string $relativeDir,string $mime,int $width,int $height): array
    {
        if (!extension_loaded('gd') || $width < 1 || $height < 1) return [null,false];
        $loader=match($mime){
            'image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp','image/gif'=>'imagecreatefromgif',default=>null,
        };
        if ($loader===null || !function_exists($loader)) return [null,false];
        $source=@$loader($sourcePath);
        if ($source===false) return [null,false];

        $maxWidth=max(240,(int)env('MEDIA_THUMB_WIDTH',480));
        $scale=min(1,$maxWidth/$width);
        $thumbWidth=max(1,(int)round($width*$scale));
        $thumbHeight=max(1,(int)round($height*$scale));
        $thumb=imagecreatetruecolor($thumbWidth,$thumbHeight);
        if ($thumb===false) { imagedestroy($source); return [null,false]; }
        if (in_array($mime,['image/png','image/webp','image/gif'],true)) {
            imagealphablending($thumb,false);imagesavealpha($thumb,true);$transparent=imagecolorallocatealpha($thumb,0,0,0,127);imagefilledrectangle($thumb,0,0,$thumbWidth,$thumbHeight,$transparent);
        }
        imagecopyresampled($thumb,$source,0,0,0,0,$thumbWidth,$thumbHeight,$width,$height);
        $thumbName='thumb-' . pathinfo($sourcePath,PATHINFO_FILENAME) . '.webp';
        $thumbAbsolute=dirname($sourcePath) . '/' . $thumbName;
        $saved=function_exists('imagewebp') ? imagewebp($thumb,$thumbAbsolute,max(55,min(95,(int)env('MEDIA_WEBP_QUALITY',84)))) : false;
        imagedestroy($thumb);imagedestroy($source);
        if (!$saved) return [null,false];
        return [rtrim($relativeDir,'/').'/'.$thumbName,true];
    }

    private function requireCsrf(): void
    {
        if (!Csrf::validate(isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : null)) { http_response_code(419); exit('Invalid or expired form token.'); }
    }

    private function redirect(string $path): never { header('Location: ' . url($path)); exit; }
    private function setFlash(string $key,string $value): void { $_SESSION['_flash'][$key]=$value; }
    private function flash(string $key): ?string { $v=$_SESSION['_flash'][$key]??null; unset($_SESSION['_flash'][$key]); return is_string($v)?$v:null; }
}
