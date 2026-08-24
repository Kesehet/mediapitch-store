<?php

declare(strict_types=1);

require dirname(__DIR__).'/src/bootstrap.php';

use MediaPitch\Ai\AutonomousContentWorker;

$result=(new AutonomousContentWorker())->runOnce();
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit(($result['status']??'')==='failed'?1:0);
