<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\RedirectRepository;
use Throwable;

final class RedirectAdminController
{
    public function __construct(private readonly RedirectRepository $repo){}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/redirects'))return false;
        if(!Auth::check()){$this->redirect('/admin/login');}
        if(!Auth::isAdministrator()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/redirects'&&$method==='GET'){
            $editId=isset($_GET['edit'])?(int)$_GET['edit']:null;
            View::render('admin/redirects',[
                'pageTitle'=>'Redirects','adminUser'=>Auth::user(),'redirects'=>$this->repo->all(),'editRedirect'=>$this->repo->find($editId),
                'success'=>$this->flash('success'),'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }
        if($path==='/admin/redirects/save'&&$method==='POST'){
            $this->requireCsrf();
            try{$this->repo->save($_POST,!empty($_POST['id'])?(int)$_POST['id']:null);$this->setFlash('success','Redirect saved.');}
            catch(Throwable $e){$this->setFlash('error','Redirect could not be saved: '.$e->getMessage());}
            $this->redirect('/admin/redirects');
        }
        return false;
    }

    private function requireCsrf(): void{if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}}
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
