<?php

declare(strict_types=1);

namespace MediaPitch\Admin;

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\NewsletterRepository;

final class NewsletterAdminController
{
    public function __construct(private readonly NewsletterRepository $repo) {}

    public function handle(string $method,string $path): bool
    {
        if(!str_starts_with($path,'/admin/newsletter')) return false;
        if(!Auth::check()){header('Location: '.url('admin/login'));exit;}
        if(!Auth::isAdministrator()){http_response_code(403);exit('Forbidden');}

        if($path==='/admin/newsletter' && $method==='GET'){
            $query=trim((string)($_GET['q']??''));$status=(string)($_GET['status']??'all');$rows=$this->repo->all($query,$status);
            if(($_GET['export']??'')==='csv'){
                header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="mediapitch-newsletter-subscribers-'.date('Y-m-d').'.csv"');
                $out=fopen('php://output','w');fputcsv($out,['Email','Status','Source','Subscribed at','Unsubscribed at']);foreach($rows as $row)fputcsv($out,[$row['email'],$row['status'],$row['source'],$row['subscribed_at'],$row['unsubscribed_at']]);fclose($out);exit;
            }
            View::render('admin/newsletter',['pageTitle'=>'Newsletter Subscribers','adminUser'=>Auth::user(),'subscribers'=>$rows,'stats'=>$this->repo->stats(),'query'=>$query,'status'=>$status,'success'=>(string)($_GET['success']??'')],'admin/layout');return true;
        }

        if($path==='/admin/newsletter/action' && $method==='POST'){
            if(!Csrf::validate($_POST['_csrf']??null)){http_response_code(419);exit('Invalid or expired form token.');}
            $id=(int)($_POST['id']??0);$action=(string)($_POST['action']??'');
            if($id>0){if($action==='delete')$this->repo->delete($id);elseif($action==='activate')$this->repo->setStatus($id,'active');elseif($action==='unsubscribe')$this->repo->setStatus($id,'unsubscribed');}
            header('Location: '.url('admin/newsletter').'?success='.rawurlencode('Subscriber updated.'));exit;
        }
        return false;
    }
}