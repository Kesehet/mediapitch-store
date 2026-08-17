<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Services\PasswordReset;
use Throwable;

final class PasswordResetAdminController
{
    public function __construct(private readonly PasswordReset $service) {}

    public function handle(string $method,string $path): bool
    {
        if(!in_array($path,['/admin/forgot-password','/admin/reset-password'],true)) return false;

        if($path==='/admin/forgot-password' && $method==='GET'){
            View::render('admin/forgot-password',[
                'pageTitle'=>'Forgot Password',
                'success'=>$this->flash('success'),
                'error'=>$this->flash('error'),
            ],'admin/auth-layout');
            return true;
        }

        if($path==='/admin/forgot-password' && $method==='POST'){
            $this->requireCsrf();
            try{
                $this->service->request((string)($_POST['email']??''));
                $this->setFlash('success','If an active account matches that email, a password-reset link has been sent.');
            }catch(Throwable $e){
                $this->setFlash('error',$e->getMessage());
            }
            $this->redirect('/admin/forgot-password');
        }

        if($path==='/admin/reset-password' && $method==='GET'){
            View::render('admin/reset-password',[
                'pageTitle'=>'Reset Password',
                'token'=>(string)($_GET['token']??''),
                'error'=>$this->flash('error'),
            ],'admin/auth-layout');
            return true;
        }

        if($path==='/admin/reset-password' && $method==='POST'){
            $this->requireCsrf();
            try{
                $this->service->reset(
                    (string)($_POST['token']??''),
                    (string)($_POST['password']??''),
                    (string)($_POST['password_confirmation']??'')
                );
                $this->setFlash('success','Password reset successfully. You can sign in now.');
                $this->redirect('/admin/login');
            }catch(Throwable $e){
                $this->setFlash('error',$e->getMessage());
                $token=urlencode((string)($_POST['token']??''));
                $this->redirect('/admin/reset-password?token='.$token);
            }
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
