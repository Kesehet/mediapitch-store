<?php

declare(strict_types=1);

use MediaPitch\Core\Audit;
use MediaPitch\Core\Auth;
use MediaPitch\Core\Csrf;
use MediaPitch\Core\View;
use MediaPitch\Repositories\NewsletterRepository;
use MediaPitch\Repositories\SenderCampaignRepository;
use MediaPitch\Repositories\SenderQueueRepository;
use MediaPitch\Repositories\SettingsRepository;
use MediaPitch\Services\SenderCampaignService;
use MediaPitch\Services\SenderClient;
use MediaPitch\Services\SenderQueueService;
use MediaPitch\Services\SubscriberMergeService;

require dirname(__DIR__) . '/src/bootstrap.php';

if (!Auth::check()) {
    header('Location: ' . url('admin/login'));
    exit;
}
if (!Auth::isAdministrator()) {
    http_response_code(403);
    exit('Forbidden');
}

$settingsRepo = new SettingsRepository();
$sender = new SenderClient($settingsRepo);
$queueRepo = new SenderQueueRepository();
$campaignRepo = new SenderCampaignRepository();
$newsletter = new NewsletterRepository();
$subscriberMerge = new SubscriberMergeService($newsletter, $sender);
$queue = new SenderQueueService($queueRepo, $sender);
$campaignQueue = new SenderCampaignService($campaignRepo, $sender);
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

$redirect = static function (string $message, string $tab = 'dashboard', bool $error = false): never {
    $query = http_build_query([
        'tab' => $tab,
        $error ? 'error' : 'success' => $message,
    ]);
    header('Location: ' . url('admin/sender') . '?' . $query);
    exit;
};

if ($method === 'POST') {
    if (!Csrf::validate(isset($_POST['_csrf']) ? (string)$_POST['_csrf'] : null)) {
        http_response_code(419);
        exit('Invalid or expired form token.');
    }

    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'save_settings') {
            $settingsRepo->saveSender($_POST);
            $saved = $settingsRepo->sender();

            Audit::record('settings.sender.update', 'settings', null, 'Updated Sender email settings', [
                'api_token_configured' => !empty($saved['api_token_configured']),
                'daily_limit' => (int)$saved['daily_limit'],
                'batch_size' => (int)$saved['batch_size'],
                'api_token' => '[redacted]',
            ]);

            $redirect('Sender settings saved securely.', 'settings');
        }

        if ($action === 'test_connection') {
            $connection = $sender->testConnection();
            $redirect('Sender connection is working. Templates available: ' . (int)$connection['templates'] . '.', 'settings');
        }

        if ($action === 'queue_campaign') {
            $campaignId = trim((string)($_POST['campaign_id'] ?? ''));
            $run = $campaignQueue->queueCampaign(
                $campaignId,
                (int)(Auth::user()['id'] ?? 0),
                !empty($_POST['consent_confirmed']),
                !empty($_POST['auto_continue'])
            );

            Audit::record('sender.campaign.queue', 'sender_campaign', (int)$run['id'], 'Queued Sender marketing campaign', [
                'source_campaign_id' => $campaignId,
                'audience_candidates' => (int)($run['audience_candidates'] ?? 0),
                'clean_queued' => (int)($run['clean_queued'] ?? $run['total_recipients'] ?? 0),
                'rejected_before_queue' => (int)($run['rejected_before_queue'] ?? 0),
                'unknown_before_queue' => (int)($run['unknown_before_queue'] ?? 0),
                'risky_before_queue' => (int)($run['risky_before_queue'] ?? 0),
                'invalid_before_queue' => (int)($run['invalid_before_queue'] ?? 0),
                'auto_continue' => !empty($_POST['auto_continue']),
            ]);

            $redirect(
                'Merged audience: ' . (int)($run['audience_candidates'] ?? 0) . ' active. ' .
                'Cleaner passed and queued ' . (int)($run['clean_queued'] ?? $run['total_recipients'] ?? 0) . '. ' .
                (int)($run['rejected_before_queue'] ?? 0) . ' rejected before queueing ' .
                '(' . (int)($run['risky_before_queue'] ?? 0) . ' risky, ' .
                (int)($run['invalid_before_queue'] ?? 0) . ' invalid, ' .
                (int)($run['unknown_before_queue'] ?? 0) . ' unknown).',
                'campaigns'
            );
        }

        if ($action === 'process_campaign') {
            $runId = (int)($_POST['run_id'] ?? 0);
            $requested = max(1, min(100, (int)($_POST['limit'] ?? 50)));
            $result = $campaignQueue->processRun($runId, $requested);

            Audit::record('sender.campaign.process', 'sender_campaign', $runId, 'Processed Sender campaign batch', $result);

            $activity = (int)$result['dispatched'] + (int)$result['blocked'] + (int)$result['retried'] + (int)$result['failed'];
            if ($activity === 0) {
                $detail = 'No campaign recipient was ready to process.';
                if ((int)($result['recovered_stale'] ?? 0) > 0) {
                    $detail .= ' Recovered ' . (int)$result['recovered_stale'] . ' stale processing row(s).';
                }
                if ((int)($result['queued_waiting'] ?? 0) > 0) {
                    $detail .= ' ' . (int)$result['queued_waiting'] . ' recipient(s) are waiting for cleaner retry';
                    if (!empty($result['next_retry_at'])) {
                        $detail .= ' at ' . (string)$result['next_retry_at'] . ' UTC';
                    }
                    $detail .= '.';
                }
                if ((int)($result['processing'] ?? 0) > 0) {
                    $detail .= ' ' . (int)$result['processing'] . ' recipient(s) are still marked processing.';
                }
                if ((int)($result['queued_ready'] ?? 0) > 0) {
                    $detail .= ' ' . (int)$result['queued_ready'] . ' recipient(s) are ready; retry processing.';
                }
                $redirect($detail, 'campaigns', true);
            }

            $redirect(
                'Campaign batch processed: ' . $result['dispatched'] . ' dispatched, ' .
                $result['blocked'] . ' blocked, ' .
                $result['retried'] . ' retrying, ' .
                $result['failed'] . ' failed. ' .
                $result['remaining_today'] . ' send(s) remain today.',
                'campaigns'
            );
        }

        if (in_array($action, ['pause_campaign','resume_campaign','cancel_campaign'], true)) {
            $runId = (int)($_POST['run_id'] ?? 0);
            if ($runId < 1) throw new InvalidArgumentException('Campaign queue not found.');

            if ($action === 'pause_campaign') $campaignQueue->pause($runId);
            elseif ($action === 'resume_campaign') $campaignQueue->resume($runId);
            else $campaignQueue->cancel($runId);

            Audit::record('sender.campaign.' . str_replace('_campaign', '', $action), 'sender_campaign', $runId, 'Updated Sender campaign queue');
            $redirect('Campaign queue updated.', 'campaigns');
        }

        if ($action === 'queue_manual') {
            $templateId = trim((string)($_POST['template_id'] ?? ''));
            $template = $sender->transactionalTemplate($templateId);
            $result = $queue->queueFromText(
                $templateId,
                (string)($template['title'] ?? $template['subject'] ?? $templateId),
                (string)($_POST['recipients'] ?? ''),
                (string)($_POST['variables_json'] ?? ''),
                (int)(Auth::user()['id'] ?? 0),
                !empty($_POST['consent_confirmed'])
            );

            Audit::record('sender.queue.manual', 'sender_template', null, 'Queued Sender template recipients', [
                'template_id' => $templateId,
                'added' => $result['added'],
                'duplicates' => $result['duplicates'],
                'invalid_syntax' => $result['invalid'],
                'rejected_before_queue' => $result['rejected'],
                'risky' => $result['risky'],
                'cleaner_invalid' => $result['cleaner_invalid'],
                'unknown' => $result['unknown'],
            ]);

            $redirect(
                'Cleaner passed and queued ' . $result['added'] . ' recipient(s). ' .
                $result['duplicates'] . ' duplicate(s) skipped; ' .
                $result['invalid'] . ' invalid line(s); ' .
                $result['rejected'] . ' rejected by cleaner before queueing ' .
                '(' . $result['risky'] . ' risky, ' .
                $result['cleaner_invalid'] . ' invalid, ' .
                $result['unknown'] . ' unknown).',
                'queue'
            );
        }

        if ($action === 'queue_subscribers') {
            $templateId = trim((string)($_POST['template_id'] ?? ''));
            $template = $sender->transactionalTemplate($templateId);
            $eligible = $newsletter->all('', 'active', 'all');
            $result = $queue->queueSubscribers(
                $templateId,
                (string)($template['title'] ?? $template['subject'] ?? $templateId),
                $eligible,
                (int)(Auth::user()['id'] ?? 0),
                !empty($_POST['consent_confirmed'])
            );

            Audit::record('sender.queue.subscribers', 'sender_template', null, 'Queued newsletter subscribers after live cleaning', [
                'template_id' => $templateId,
                'added' => $result['added'],
                'duplicates' => $result['duplicates'],
                'rejected_before_queue' => $result['rejected'],
                'risky' => $result['risky'],
                'cleaner_invalid' => $result['cleaner_invalid'],
                'unknown' => $result['unknown'],
            ]);

            $redirect(
                'Cleaner passed and queued ' . $result['added'] . ' active subscriber(s). ' .
                $result['duplicates'] . ' already queued/sent; ' .
                $result['rejected'] . ' rejected before queueing ' .
                '(' . $result['risky'] . ' risky, ' .
                $result['cleaner_invalid'] . ' invalid, ' .
                $result['unknown'] . ' unknown).',
                'queue'
            );
        }

        if ($action === 'process') {
            $requested = max(1, min(100, (int)($_POST['limit'] ?? 50)));
            $result = $queue->process($requested);

            Audit::record('sender.queue.process', 'sender_queue', null, 'Processed Sender queue', $result);

            $redirect(
                'Sender queue processed: ' . $result['sent'] . ' sent, ' .
                $result['blocked'] . ' blocked, ' .
                $result['retried'] . ' retrying, ' .
                $result['failed'] . ' failed. ' .
                $result['remaining_today'] . ' send(s) remain today.',
                'queue'
            );
        }

        if ($action === 'retry') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $queueRepo->retry($id);
                Audit::record('sender.queue.retry', 'sender_queue', $id, 'Requeued Sender email');
            }
            $redirect('Email requeued. It will be validated again before sending.', 'queue');
        }

        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $queueRepo->deleteUnsent($id);
                Audit::record('sender.queue.delete', 'sender_queue', $id, 'Deleted unsent Sender queue item');
            }
            $redirect('Unsent queue item deleted.', 'queue');
        }

        throw new InvalidArgumentException('Unknown Sender action.');
    } catch (Throwable $e) {
        if ((bool)env('APP_DEBUG', false)) {
            error_log('Sender admin action failed: ' . $e->getMessage());
        }
        $redirect($e->getMessage(), (string)($_GET['tab'] ?? $_POST['tab'] ?? 'dashboard'), true);
    }
}

$tab = (string)($_GET['tab'] ?? 'dashboard');
if (!in_array($tab, ['dashboard','campaigns','templates','queue','history','settings'], true)) {
    $tab = 'dashboard';
}

$templates = [];
$providerError = null;
$connection = null;
if ($sender->configured()) {
    try {
        $templates = $sender->transactionalTemplates(100);
        $connection = ['ok' => true, 'templates' => count($templates)];
    } catch (Throwable $e) {
        $providerError = $e->getMessage();
    }
}

$campaigns = [];
$selectedCampaign = null;
$mergedAudienceStats = [];
$mergedAudienceError = null;
$mergedAudienceWarning = null;
$mergedAudienceTruncated = false;
$mergedAudienceLastSyncedAt = null;
$mergedAudienceRefreshAfter = null;
if ($tab === 'campaigns' && $sender->configured()) {
    try {
        $campaigns = array_values(array_filter(
            $sender->campaigns(100),
            static fn(array $campaign): bool => !str_starts_with((string)($campaign['title'] ?? ''), '[MediaPitch batch]')
        ));
        $campaignId = trim((string)($_GET['campaign'] ?? ''));
        if ($campaignId !== '') {
            $selectedCampaign = $sender->campaign($campaignId);
        }

        $audience = $subscriberMerge->merged();
        $mergedAudienceStats = $audience['stats'];
        $mergedAudienceError = $audience['sender_error'];
        $mergedAudienceWarning = $audience['sender_warning'];
        $mergedAudienceTruncated = (bool)$audience['sender_truncated'];
        $mergedAudienceLastSyncedAt = $audience['sender_last_synced_at'];
        $mergedAudienceRefreshAfter = $audience['sender_refresh_after'];
    } catch (Throwable $e) {
        $providerError = $e->getMessage();
    }
}

$selectedTemplate = null;
$templateId = trim((string)($_GET['template'] ?? ''));
if ($templateId !== '' && $sender->configured()) {
    try {
        $selectedTemplate = $sender->transactionalTemplate($templateId);
    } catch (Throwable $e) {
        $providerError = $e->getMessage();
    }
}

$queueStatus = (string)($_GET['status'] ?? 'all');
if (!in_array($queueStatus, ['all','queued','processing','sent','blocked','failed'], true)) {
    $queueStatus = 'all';
}

View::render('admin/sender', [
    'pageTitle' => 'Sender Email Center',
    'adminUser' => Auth::user(),
    'tab' => $tab,
    'templates' => $templates,
    'selectedTemplate' => $selectedTemplate,
    'campaigns' => $campaigns,
    'selectedCampaign' => $selectedCampaign,
    'campaignRuns' => $campaignRepo->runs(100),
    'campaignStats' => $campaignQueue->stats(),
    'mergedAudienceStats' => $mergedAudienceStats,
    'mergedAudienceError' => $mergedAudienceError,
    'mergedAudienceWarning' => $mergedAudienceWarning,
    'mergedAudienceTruncated' => $mergedAudienceTruncated,
    'mergedAudienceLastSyncedAt' => $mergedAudienceLastSyncedAt,
    'mergedAudienceRefreshAfter' => $mergedAudienceRefreshAfter,
    'providerConfigured' => $sender->configured(),
    'senderSettings' => $settingsRepo->sender(),
    'providerError' => $providerError,
    'connection' => $connection,
    'stats' => $queue->stats(),
    'queueRows' => $queueRepo->recent($queueStatus, 200),
    'queueStatus' => $queueStatus,
    'newsletterStats' => $newsletter->stats(),
    'success' => (string)($_GET['success'] ?? ''),
    'error' => (string)($_GET['error'] ?? ''),
], 'admin/layout');
