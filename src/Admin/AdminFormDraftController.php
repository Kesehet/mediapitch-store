<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Repositories\AdminFormDraftRepository;
use Throwable;

final class AdminFormDraftController
{
    public function __construct(private readonly AdminFormDraftRepository $drafts) {}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/form-drafts')) return false;
        if(!Auth::check()){
            http_response_code(401);
            $this->json(['ok'=>false,'error'=>'Authentication required.']);
            return true;
        }

        $userId=(int)(Auth::user()['id']??0);
        if($path==='/admin/form-drafts/load'&&$method==='GET'){
            $key=(string)($_GET['key']??'');
            try{
                $draft=$this->drafts->find($userId,$key);
                $this->json(['ok'=>true,'draft'=>$draft]);
            }catch(Throwable){
                http_response_code(500);
                $this->json(['ok'=>false,'error'=>'Draft could not be loaded.']);
            }
            return true;
        }

        if(in_array($path,['/admin/form-drafts/save','/admin/form-drafts/delete'],true)&&$method==='POST'){
            if(!Csrf::validate(isset($_POST['_csrf'])?(string)$_POST['_csrf']:null)){
                http_response_code(419);
                $this->json(['ok'=>false,'error'=>'Invalid or expired form token.']);
                return true;
            }
            $key=(string)($_POST['key']??'');
            try{
                if($path==='/admin/form-drafts/delete'){
                    $this->drafts->delete($userId,$key);
                    $this->json(['ok'=>true]);
                    return true;
                }
                $raw=(string)($_POST['payload']??'');
                $payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
                if(!is_array($payload)) throw new \RuntimeException('Invalid payload.');
                $this->drafts->save($userId,$key,$payload);
                $this->json(['ok'=>true]);
            }catch(Throwable){
                http_response_code(400);
                $this->json(['ok'=>false,'error'=>'Draft could not be saved.']);
            }
            return true;
        }

        http_response_code(404);
        $this->json(['ok'=>false,'error'=>'Not found.']);
        return true;
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
}
