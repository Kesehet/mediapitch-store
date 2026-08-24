<?php

declare(strict_types=1);

use MediaPitch\Admin\AiSettingsAdminController;
use MediaPitch\Ai\AiJobRepository;
use MediaPitch\Repositories\AiSettingsRepository;

require dirname(__DIR__).'/src/bootstrap.php';

$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
$path=parse_url($_SERVER['REQUEST_URI']??'/admin/settings/ai',PHP_URL_PATH)?:'/admin/settings/ai';

try{
    $controller=new AiSettingsAdminController(new AiSettingsRepository(),new AiJobRepository());
    if($controller->handle($method,$path))exit;
    http_response_code(404);
    echo 'Not found';
}catch(Throwable $e){
    http_response_code(500);
    if((bool)env('APP_DEBUG',false)){
        echo '<pre>'.e($e->getMessage())."\n\n".e($e->getTraceAsString()).'</pre>';
    }else{
        echo 'AI settings could not be loaded. Check that database migrations have been applied, then reload the page.';
    }
}
