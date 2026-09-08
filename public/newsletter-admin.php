<?php

declare(strict_types=1);

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\NewsletterRepository;

require dirname(__DIR__).'/src/bootstrap.php';
if(!Auth::check()){header('Location: '.url('admin/login'));exit;}
if(!Auth::isAdministrator()){http_response_code(403);exit('Forbidden');}
$repo=new NewsletterRepository();
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
if($method==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null)){http_response_code(419);exit('Invalid or expired form token.');}
    $id=(int)($_POST['id']??0);$action=(string)($_POST['action']??'');
    if($id>0){
        if($action==='delete')$repo->delete($id);
        elseif($action==='activate')$repo->setStatus($id,'active');
        elseif($action==='unsubscribe')$repo->setStatus($id,'unsubscribed');
    }
    header('Location: '.url('admin/newsletter').'?success='.rawurlencode('Subscriber updated.'));exit;
}
$query=trim((string)($_GET['q']??''));
$status=(string)($_GET['status']??'all');
$validation=(string)($_GET['validation']??'all');
$rows=$repo->all($query,$status,$validation);
if(($_GET['export']??'')==='csv'){
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="mediapitch-newsletter-subscribers-'.date('Y-m-d').'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['Email','Status','Source','Validation','Validation reason','Validation checked at','Subscribed at','Unsubscribed at']);
    foreach($rows as $row)fputcsv($out,[$row['email'],$row['status'],$row['source'],$row['validation_status']??'', $row['validation_reason']??'', $row['validation_checked_at']??'', $row['subscribed_at'],$row['unsubscribed_at']]);
    fclose($out);exit;
}
View::render('admin/newsletter',['pageTitle'=>'Newsletter Subscribers','adminUser'=>Auth::user(),'subscribers'=>$rows,'stats'=>$repo->stats(),'query'=>$query,'status'=>$status,'validation'=>$validation,'success'=>(string)($_GET['success']??'')],'admin/layout');