<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\View;
use MediaPitch\Repositories\AuditRepository;
use Throwable;

final class AuditAdminController
{
    public function __construct(private readonly AuditRepository $repo) {}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/audit'))return false;
        if(!Auth::check())$this->redirect('/admin/login');
        if(!Auth::isAdministrator()){http_response_code(403);exit('Administrator access required.');}

        if($path==='/admin/audit'&&$method==='GET'){
            $entries=[];
            $schemaError=null;
            try{
                $entries=$this->repo->recent();
            }catch(Throwable $e){
                $schemaError='Audit storage is not available yet. Run the database deployment/migrations, then reload this page.';
                if((bool)env('APP_DEBUG',false))error_log('Audit log read failed: '.$e->getMessage());
            }
            View::render('admin/audit',[
                'pageTitle'=>'Audit Log',
                'adminUser'=>Auth::user(),
                'entries'=>$entries,
                'schemaError'=>$schemaError,
            ],'admin/layout');
            return true;
        }

        http_response_code(404);
        return true;
    }

    private function redirect(string $path): never{header('Location: '.url($path));exit;}
}
