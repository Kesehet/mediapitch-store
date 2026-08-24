<?php

declare(strict_types=1);

use MediaPitch\Admin\GoogleAdsAdminController;
use MediaPitch\GoogleAds\GoogleAdsClient;
use MediaPitch\Repositories\GoogleAdsRepository;

require dirname(__DIR__).'/src/bootstrap.php';

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$path=parse_url($_SERVER['REQUEST_URI']??'/admin/settings/google-ads',PHP_URL_PATH)?:'/admin/settings/google-ads';

try{
    $controller=new GoogleAdsAdminController(new GoogleAdsRepository(),new GoogleAdsClient());
    if($controller->handle($method,$path))exit;
    http_response_code(404);echo 'Not found.';
}catch(Throwable $e){
    http_response_code(500);
    if((bool)env('APP_DEBUG',false))echo '<pre>'.e($e->getMessage())."\n\n".e($e->getTraceAsString()).'</pre>';
    else echo 'Google Ads settings are temporarily unavailable.';
}
