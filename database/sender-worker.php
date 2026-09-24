<?php

declare(strict_types=1);

use MediaPitch\Services\SenderCampaignService;
use MediaPitch\Services\SenderQueueService;

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $queue = new SenderQueueService();
    $campaigns = new SenderCampaignService();
    $requested = isset($argv[1]) ? max(1, min(100, (int)$argv[1])) : $queue->batchSize();

    $transactional = $queue->process($requested);
    $campaign = [
        'examined' => 0,
        'dispatched' => 0,
        'blocked' => 0,
        'retried' => 0,
        'failed' => 0,
        'batches' => 0,
        'remaining_today' => (int)$transactional['remaining_today'],
    ];

    if ((int)$transactional['remaining_today'] > 0) {
        $campaign = $campaigns->processReadyRuns(
            min($requested, (int)$transactional['remaining_today'])
        );
    }

    fwrite(STDOUT, json_encode([
        'transactional' => $transactional,
        'campaigns' => $campaign,
        'remaining_today' => (int)$campaign['remaining_today'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Sender worker failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
