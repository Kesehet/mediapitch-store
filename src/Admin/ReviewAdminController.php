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
use MediaPitch\Repositories\ReviewRepository;
use Throwable;

final class ReviewAdminController
{
    public function __construct(private readonly ReviewRepository $reviews, private readonly AdminRepository $admin) {}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/reviews')) return false;
        if(!Auth::check()) $this->redirect('/admin/login');
        if(!Auth::canEditContent()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/reviews'&&$method==='GET'){
            View::render('admin/reviews',[
                'pageTitle'=>'Reviews','adminUser'=>Auth::user(),'reviews'=>$this->reviews->adminList(),
                'success'=>$this->flash('success'),'error'=>$this->flash('error')
            ],'admin/layout'); return true;
        }

        if($method==='GET'&&($path==='/admin/reviews/new'||preg_match('#^/admin/reviews/(\d+)/edit$#',$path,$m))){
            $id=isset($m[1])?(int)$m[1]:null;
            View::render('admin/review-form',[
                'pageTitle'=>$id?'Edit Review':'New Review','adminUser'=>Auth::user(),
                'review'=>$this->reviews->adminReview($id),'products'=>$this->admin->productOptions(),
                'categories'=>$this->admin->categoryOptions(),'mediaItems'=>(new MediaRepository())->all(),
                'success'=>$this->flash('success'),'error'=>$this->flash('error')
            ],'admin/layout'); return true;
        }

        if($path==='/admin/reviews/save'&&$method==='POST'){
            if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){http_response_code(419);exit('Invalid or expired form token.');}
            $status=(string)($_POST['status']??'draft');
            if(in_array($status,['published','scheduled'],true)&&!Auth::canPublish()){http_response_code(403);exit('Forbidden');}
            $existingId=!empty($_POST['id'])?(int)$_POST['id']:null;
            $old=$existingId?$this->reviews->adminReview($existingId):null;
            try{
                $id=$this->reviews->save($_POST,(int)Auth::user()['id'],$existingId);
                $newSlug=trim((string)($_POST['slug']??''));
                if($old && !empty($old['slug']) && $newSlug!=='' && $old['slug']!==$newSlug){
                    (new RedirectRepository())->upsert('/review/'.$old['slug'],'/review/'.$newSlug);
                }
                Audit::record($existingId?'review.update':'review.create','review',$id,$existingId?'Updated review':'Created review',[
                    'title'=>(string)($_POST['title']??''),'slug'=>$newSlug,'status'=>$status,'product_id'=>(int)($_POST['product_id']??0),
                ]);
                $this->setFlash('success','Review saved.'); $this->redirect('/admin/reviews/'.$id.'/edit');
            }catch(Throwable $e){$this->setFlash('error',$e->getMessage());$this->redirect('/admin/reviews');}
        }
        return false;
    }

    private function redirect(string $path): never {header('Location: '.url($path));exit;}
    private function setFlash(string $key,string $value): void {$_SESSION['_flash'][$key]=$value;}
    private function flash(string $key): ?string {$v=$_SESSION['_flash'][$key]??null;unset($_SESSION['_flash'][$key]);return is_string($v)?$v:null;}
}
