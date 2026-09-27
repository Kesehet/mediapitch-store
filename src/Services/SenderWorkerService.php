<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Repositories\SenderWorkerStateRepository;

final class SenderWorkerService
{
    private SenderWorkerStateRepository $workerState;
    private SenderQueueService $queue;
    private SenderCampaignService $campaigns;

    public function __construct(
        ?SenderWorkerStateRepository $workerState = null,
        ?SenderQueueService $queue = null,
        ?SenderCampaignService $campaigns = null
    ) {
        $this->workerState = $workerState ?? new SenderWorkerStateRepository();
        $this->queue = $queue ?? new SenderQueueService();
        $this->campaigns = $campaigns ?? new SenderCampaignService();
    }

    /** @return array<string,mixed> */
    public function run(?int $requested = null): array
    {
        $this->workerState->started();

        try {
            $requested = $requested !== null
                ? max(1, min(100, $requested))
                : $this->queue->batchSize();

            $transactional = $this->queue->process($requested);
            $campaign = [
                'examined' => 0,
                'dispatched' => 0,
                'blocked' => 0,
                'retried' => 0,
                'failed' => 0,
                'batches' => 0,
                'remaining_today' => (int)$transactional['remaining_today'],
            ];

            $transactionalUncertain = (int)($transactional['uncertain_processing'] ?? 0);
            $senderCooling = !empty($transactional['sender_cooldown_until']);

            if (
                (int)$transactional['remaining_today'] > 0 &&
                $transactionalUncertain === 0 &&
                !$senderCooling
            ) {
                $campaign = $this->campaigns->processReadyRuns(
                    min($requested, (int)$transactional['remaining_today'])
                );
            }

            $remainingToday = (int)$campaign['remaining_today'];
            $this->workerState->succeeded($transactional, $campaign, $remainingToday);

            return [
                'ok' => true,
                'transactional' => $transactional,
                'campaigns' => $campaign,
                'remaining_today' => $remainingToday,
                'ran_at_utc' => gmdate('c'),
            ];
        } catch (\Throwable $e) {
            try {
                $this->workerState->failed($e->getMessage());
            } catch (\Throwable) {
            }
            throw $e;
        }
    }
}
