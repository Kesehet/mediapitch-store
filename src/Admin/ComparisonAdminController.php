<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AdminRepository;
use MediaPitch\Repositories\ComparisonRepository;
use MediaPitch\Repositories\RedirectRepository;
use Throwable;

final class ComparisonAdminController
{
    public function __construct(
        private readonly ComparisonRepository $comparisons,
        private readonly AdminRepository $admin
    ) {}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/comparisons'))return false;
        if(!Auth::check())$this->redirect('/admin/login');

        if($path==='/admin/comparisons'&&$method==='GET'){
            View::render('admin/comparisons',array_merge([
                'comparisons'=>$this->comparisons->adminList(),
            ],$this->common('Comparisons')),'admin/layout');
            return true;
        }

        if($method==='GET'&&($path==='/admin/comparisons/new'||preg_match('#^/admin/comparisons/(\d+)/edit$#',$path,$m))){
            $id=isset($m[1])?(int)$m[1]:null;
            View::render('admin/comparison-form',array_merge([
                'comparison'=>$this->comparisons->adminComparison($id),
                'categories'=>$this->admin->categoryOptions(),
                'productOptions'=>$this->admin->productOptions(),
            ],$this->common($id?'Edit Comparison':'New Comparison')),'admin/layout');
            return true;
        }

        if($path==='/admin/comparisons/save'&&$method==='POST'){
            $this->requireCsrf();
            $status=(string)($_POST['status']??'draft');
            if(in_array($status,['published','scheduled'],true)&&!Auth::canPublish()){
                http_response_code(403);echo 'You do not have permission to publish or schedule.';return true;
            }
            $existingId=!empty($_POST['id'])?(int)$_POST['id']:null;
            $old=$existingId?$this->comparisons->adminComparison($existingId):null;
            try{
                $id=$this->comparisons->save($_POST,(int)Auth::user()['id'],$existingId);
                $newSlug=trim((string)($_POST['slug']??''));
                if($old && !empty($old['slug']) && $newSlug!=='' && $old['slug']!==$newSlug){
                    (new RedirectRepository())->upsert('/compare/'.$old['slug'],'/compare/'.$newSlug);
                }
                $this->setFlash('success','Comparison saved.');
                $this->redirect('/admin/comparisons/'.$id.'/edit');
            }catch(Throwable $e){
                $this->setFlash('error','Comparison could not be saved: '.$e->getMessage());
                $this->redirect('/admin/comparisons');
            }
        }

        http_response_code(404);
        View::render('404',['pageTitle'=>'Admin page not found','metaDescription'=>'']);
        return true;
    }

    private function common(string $title): array
    {
        return ['pageTitle'=>$title,'adminUser'=>Auth::user(),'success'=>$this->flash('success'),'error'=>$this->flash('error')];
    }

    private function requireCsrf(): void
    {
        if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){
            http_response_code(419);exit('Invalid or expired form token.');
        }
    }

    private function redirect(string $path): never
    {
        header('Location: '.url($path));exit;
    }

    private function setFlash(string $key,string $value): void{$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string
    {
        $value=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($value)?$value:null;
    }
}
