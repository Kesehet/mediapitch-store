<?php

declare(strict_types=1);

use MediaPitch\Services\SenderQueueService;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $service = new SenderQueueService();
    $requested = isset($argv[1]) ? max(1, (int)$argv[1]) : $service->batchSize();
    $result = $service->process($requested);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Sender worker failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
