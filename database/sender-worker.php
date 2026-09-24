<?php

declare(strict_types=1);

use MediaPitch\Services\SenderQueueService;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $requested = isset($argv[1]) ? max(1, (int)$argv[1]) : (int)\env('SENDER_QUEUE_BATCH_SIZE', 50);
    $result = (new SenderQueueService())->process($requested);
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Sender worker failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
