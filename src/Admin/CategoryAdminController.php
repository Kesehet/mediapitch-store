<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Repositories\MediaRepository;
use MediaPitch\Repositories\RedirectRepository;
use Throwable;

final class CategoryAdminController
{
    public function __construct(private readonly AdminRepository $repo){}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/categories'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::canManageProducts()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/categories'&&$method==='GET'){
            $editId=isset($_GET['edit'])?(int)$_GET['edit']:null;
            View::render('admin/categories',[
                'pageTitle'=>$editId?'Edit Category':'Categories','adminUser'=>Auth::user(),
                'categories'=>$this->repo->categories(),'category'=>$this->repo->category($editId),
                'categoryOptions'=>$this->repo->categoryOptions(),'mediaItems'=>(new MediaRepository())->all(),
                'success'=>$this->flash('success'),'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }

        if($path==='/admin/categories/save'&&$method==='POST'){
            $this->requireCsrf();
            $id=!empty($_POST['id'])?(int)$_POST['id']:null;
            $old=$id?$this->repo->category($id):null;
            try{
                $saved=$this->repo->saveCategory($_POST,$id);
                $new=$this->repo->category($saved);
                if($old&&$new&&!empty($old['slug'])&&$old['slug']!==$new['slug']){
                    (new RedirectRepository())->upsert('/category/'.$old['slug'],'/category/'.$new['slug']);
                }
                Audit::record($id?'category.update':'category.create','category',$saved,$id?'Updated category':'Created category',[
                    'name'=>$new['name']??($_POST['name']??''),'slug'=>$new['slug']??($_POST['slug']??''),'active'=>(bool)($new['active']??true),
                ]);
                $this->setFlash('success','Category saved.');
            }catch(Throwable $e){$this->setFlash('error','Category could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/categories');
        }

        if($method==='POST'&&preg_match('#^/admin/categories/(\d+)/(archive|restore)$#',$path,$m)){
            $this->requireCsrf();
            try{
                $id=(int)$m[1];$action=$m[2];
                $this->repo->setCategoryActive($id,$action==='restore');
                Audit::record('category.'.$action,'category',$id,ucfirst($action).'d category');
                $this->setFlash('success',$action==='restore'?'Category restored.':'Category archived.');
            }catch(Throwable $e){$this->setFlash('error',$e->getMessage());}
            $this->redirect('/admin/categories');
        }
        return false;
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
