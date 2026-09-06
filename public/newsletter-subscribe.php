<?php

declare(strict_types=1);

use MediaPitch\Repositories\NewsletterRepository;

require dirname(__DIR__) . '/src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {http_response_code(405);echo json_encode(['ok'=>false,'message'=>'Method not allowed.']);exit;}
if (trim((string)($_POST['website'] ?? '')) !== '') {echo json_encode(['ok'=>true,'message'=>'Thanks for joining.']);exit;}
try {
    $result=(new NewsletterRepository())->subscribe((string)($_POST['email']??''),(string)($_POST['source']??'popup'));
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);
} catch (Throwable $e) {
    if((bool)env('APP_DEBUG',false))error_log('Newsletter signup failed: '.$e->getMessage());
    http_response_code(503);echo json_encode(['ok'=>false,'message'=>'We could not save your email right now. Please try again shortly.']);
}