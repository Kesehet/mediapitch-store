<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Services\SpecificationAdminActions;
use Throwable;

final class SpecificationAdminController
{
    public function __construct(private readonly AdminRepository $repo){}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/specifications'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::canManageProducts()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/specifications'&&$method==='GET'){
            $editId=isset($_GET['edit'])?(int)$_GET['edit']:null;
            View::render('admin/specifications',[
                'pageTitle'=>$editId?'Edit Specification':'Specifications',
                'adminUser'=>Auth::user(),
                'definitions'=>$this->repo->specificationDefinitions(),
                'definition'=>$this->repo->specificationDefinition($editId),
                'categories'=>$this->repo->categoryOptions(),
                'success'=>$this->flash('success'),'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }

        if($path==='/admin/specifications/save'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $this->repo->saveSpecificationDefinition($_POST,!empty($_POST['id'])?(int)$_POST['id']:null);
                $this->setFlash('success','Specification saved.');
            }catch(Throwable $e){$this->setFlash('error','Specification could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/specifications');
        }

        if($method==='POST'&&preg_match('#^/admin/specifications/(\d+)/(archive|restore)$#',$path,$m)){
            $this->requireCsrf();
            try{
                $actions=new SpecificationAdminActions();
                if($m[2]==='archive'){$actions->archive((int)$m[1]);$this->setFlash('success','Specification archived. Existing values were preserved.');}
                else{$actions->restore((int)$m[1]);$this->setFlash('success','Specification restored. Re-enable filter/comparison flags if needed.');}
            }catch(Throwable $e){$this->setFlash('error',$e->getMessage());}
            $this->redirect('/admin/specifications');
        }
        return false;
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
