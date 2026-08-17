<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\MerchandisingRepository;
use Throwable;

final class MerchandisingAdminController
{
    public function __construct(private readonly MerchandisingRepository $repo) {}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/merchandising'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::canManageProducts()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/merchandising'&&$method==='GET'){
            View::render('admin/merchandising',[
                'pageTitle'=>'Homepage Merchandising',
                'adminUser'=>Auth::user(),
                'settings'=>$this->repo->settings(),
                'products'=>$this->repo->productOptions(),
                'success'=>$this->flash('success'),
                'error'=>$this->flash('error'),
            ],'admin/layout');
            return true;
        }

        if($path==='/admin/merchandising/save'&&$method==='POST'){
            $this->requireCsrf();
            try{
                $this->repo->save($_POST);
                Audit::record('merchandising.update','homepage',null,'Updated homepage featured/deal picks',[
                    'featured_ids'=>array_values(array_map('intval',is_array($_POST['featured_ids']??null)?$_POST['featured_ids']:[])),
                    'deal_ids'=>array_values(array_map('intval',is_array($_POST['deal_ids']??null)?$_POST['deal_ids']:[])),
                    'deals_title'=>(string)($_POST['deals_title']??''),
                ]);
                $this->setFlash('success','Homepage merchandising saved.');
            }catch(Throwable $e){
                $this->setFlash('error','Merchandising could not be saved: '.$e->getMessage());
            }
            $this->redirect('/admin/merchandising');
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
    private function flash(string $key): ?string{$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
