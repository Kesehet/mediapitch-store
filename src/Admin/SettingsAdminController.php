<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Amazon\CreatorsApiClient;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\SettingsRepository;
use Throwable;

final class SettingsAdminController
{
    public function __construct(private readonly SettingsRepository $repo)
    {
    }

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/settings'))return false;
        if(!Auth::check()){$this->redirect('/admin/login');}
        if(!Auth::isAdministrator()){http_response_code(403);echo 'Administrator access required.';return true;}

        if($path==='/admin/settings/site'&&$method==='GET'){
            View::render('admin/settings-site',[
                'pageTitle'=>'Website Settings',
                'adminUser'=>Auth::user(),
                'settings'=>$this->repo->site(),
                'success'=>$this->flash('success'),
                'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }
        if($path==='/admin/settings/site/save'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $this->repo->saveSite($_POST);
                $this->setFlash('success','Website settings saved.');
            }catch(Throwable $e){
                $this->setFlash('error','Website settings could not be saved: '.$e->getMessage());
            }
            $this->redirect('/admin/settings/site');
        }

        if($path==='/admin/settings/amazon'&&$method==='GET'){
            View::render('admin/settings-amazon',[
                'pageTitle'=>'Amazon Creators API',
                'adminUser'=>Auth::user(),
                'settings'=>$this->repo->amazon(),
                'success'=>$this->flash('success'),
                'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }

        if($path==='/admin/settings/amazon/save'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $this->repo->saveAmazon($_POST);
                $this->setFlash('success','Amazon settings saved securely.');
            }catch(Throwable $e){
                $this->setFlash('error','Settings could not be saved: '.$e->getMessage());
            }
            $this->redirect('/admin/settings/amazon');
        }

        if($path==='/admin/settings/amazon/test'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $settings=$this->repo->amazon();
                $result=(new CreatorsApiClient())->testCredentials($settings);
                $stamp=gmdate('Y-m-d H:i:s');
                $this->repo->setAmazonStatus($stamp,null);
                $this->setFlash('success','Amazon authentication succeeded. Token lifetime reported: '.(int)$result['expires_in'].' seconds.');
            }catch(Throwable $e){
                $this->repo->setAmazonStatus(null,substr($e->getMessage(),0,1000));
                $this->setFlash('error','Amazon connection test failed: '.$e->getMessage());
            }
            $this->redirect('/admin/settings/amazon');
        }

        return false;
    }

    private function requireCsrf(): void
    {
        if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){
            http_response_code(419);exit('Invalid or expired form token.');
        }
    }
    private function redirect(string $path): never{header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string{$value=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($value)?$value:null;}
}
