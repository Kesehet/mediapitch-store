<?php

declare(strict_types=1);

use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\NewsletterRepository;
use MediaPitch\Services\SubscriberMergeService;

require dirname(__DIR__).'/src/bootstrap.php';
if(!Auth::check()){header('Location: '.url('admin/login'));exit;}
if(!Auth::isAdministrator()){http_response_code(403);exit('Forbidden');}

$repo=new NewsletterRepository();
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');

if($method==='POST'){
    if(!Csrf::validate($_POST['_csrf']??null)){http_response_code(419);exit('Invalid or expired form token.');}
    $id=(int)($_POST['id']??0);
    $action=(string)($_POST['action']??'');
    if($id>0){
        if($action==='delete')$repo->delete($id);
        elseif($action==='activate')$repo->setStatus($id,'active');
        elseif($action==='unsubscribe')$repo->setStatus($id,'unsubscribed');
        elseif($action==='revalidate')$repo->revalidate($id);
    }
    header('Location: '.url('admin/newsletter').'?success='.rawurlencode($action==='revalidate'?'Email validation refreshed.':'Subscriber updated.'));
    exit;
}

$query=trim((string)($_GET['q']??''));
$presence=(string)($_GET['presence']??'all');
$status=(string)($_GET['status']??'all');
$validation=(string)($_GET['validation']??'all');

if(!in_array($presence,['all','local','sender','both'],true))$presence='all';
if(!in_array($status,['all','active','unsubscribed','bounced','suppressed','unknown'],true))$status='all';
if(!in_array($validation,['all','clean','risky','invalid','unknown','not_checked'],true))$validation='all';

$merged=(new SubscriberMergeService($repo))->merged($query,$presence,$status,$validation);
$rows=$merged['rows'];

if(($_GET['export']??'')==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="mediapitch-merged-subscribers-'.date('Y-m-d').'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,[
        'Email','Name','Presence','Effective status','Local status','Sender status',
        'Local source','Validation','Validation reason','Local subscribed at','Sender created at'
    ]);
    foreach($rows as $row){
        fputcsv($out,[
            $row['email'],
            $row['display_name']??'',
            $row['presence']??'',
            $row['effective_status']??'',
            $row['local_status']??'',
            $row['sender_status']??'',
            $row['local_source']??'',
            $row['validation_status']??'',
            $row['validation_reason']??'',
            $row['local_subscribed_at']??'',
            $row['sender_created_at']??'',
        ]);
    }
    fclose($out);
    exit;
}

View::render('admin/newsletter',[
    'pageTitle'=>'Merged Subscribers',
    'adminUser'=>Auth::user(),
    'subscribers'=>$rows,
    'stats'=>$merged['stats'],
    'query'=>$query,
    'presence'=>$presence,
    'status'=>$status,
    'validation'=>$validation,
    'senderError'=>$merged['sender_error'],
    'senderTruncated'=>$merged['sender_truncated'],
    'senderReportedTotal'=>$merged['sender_reported_total'],
    'success'=>(string)($_GET['success']??''),
],'admin/layout');
