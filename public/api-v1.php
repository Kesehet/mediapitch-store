<?php

declare(strict_types=1);

use MediaPitch\Api\AdminApiController;
use MediaPitch\Repositories\AdminRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

$method=strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path=parse_url($_SERVER['REQUEST_URI'] ?? '/api/v1/status',PHP_URL_PATH) ?: '/api/v1/status';

$api=new AdminApiController(new AdminRepository());
if(!$api->handle($method,$path)){
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode(['ok'=>false,'error'=>'not_found'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
}
