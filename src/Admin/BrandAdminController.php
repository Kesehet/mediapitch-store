<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\BrandRepository;
use MediaPitch\Repositories\MediaRepository;
use Throwable;

final class BrandAdminController
{
    public function __construct(private readonly BrandRepository $repo){}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/brands'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::canManageProducts()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/brands'&&$method==='GET'){
            $editId=isset($_GET['edit'])?(int)$_GET['edit']:null;
            View::render('admin/brands',[
                'pageTitle'=>$editId?'Edit Brand':'Brands','adminUser'=>Auth::user(),
                'brands'=>$this->repo->all(),'brand'=>$this->repo->find($editId),'mediaItems'=>(new MediaRepository())->all(),
                'success'=>$this->flash('success'),'error'=>$this->flash('error'),
            ],'admin/layout');return true;
        }

        if($path==='/admin/brands/save'&&$method==='POST'){
            $this->requireCsrf();$existingId=!empty($_POST['id'])?(int)$_POST['id']:null;
            try{$id=$this->repo->save($_POST,$existingId);Audit::record($existingId?'brand.update':'brand.create','brand',$id,$existingId?'Updated brand':'Created brand',['name'=>(string)($_POST['name']??''),'slug'=>(string)($_POST['slug']??'')]);$this->setFlash('success','Brand saved.');}
            catch(Throwable $e){$this->setFlash('error','Brand could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/brands');
        }

        if($method==='POST'&&preg_match('#^/admin/brands/(\d+)/(archive|restore)$#',$path,$m)){
            $this->requireCsrf();$id=(int)$m[1];$restore=$m[2]==='restore';
            try{$this->repo->setActive($id,$restore);Audit::record($restore?'brand.restore':'brand.archive','brand',$id,$restore?'Restored brand':'Archived brand');$this->setFlash('success',$restore?'Brand restored.':'Brand archived. Existing product relationships were preserved.');}
            catch(Throwable $e){$this->setFlash('error',$e->getMessage());}
            $this->redirect('/admin/brands');
        }
        return false;
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
