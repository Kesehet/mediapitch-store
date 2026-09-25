<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Repositories\SenderApiStateRepository;
use MediaPitch\Repositories\SenderResourceCacheRepository;
use MediaPitch\Repositories\SettingsRepository;

final class SenderClient
{
    private const BASE_URL = 'https://api.sender.net/v2';
    private const CATALOG_FRESH_SECONDS = 900;
    private const CATALOG_MAX_STALE_SECONDS = 604800;

    public function __construct(
        private readonly SettingsRepository $settings = new SettingsRepository(),
        private readonly SenderApiStateRepository $apiState = new SenderApiStateRepository(),
        private readonly SenderResourceCacheRepository $resourceCache = new SenderResourceCacheRepository()
    ) {
    }

    public function configured(): bool
    {
        return !empty($this->settings->sender()['api_token_configured']);
    }

    /** @return array<string,mixed> */
    public function apiStatus(): array
    {
        return $this->apiState->status();
    }

    public function clearApiCooldown(): void
    {
        $this->apiState->clearCooldown();
    }

    public function assertApiAvailable(): void
    {
        if (!$this->configured()) {
            throw new SenderApiException('Sender API token is not configured.');
        }
        $this->apiState->assertRequestAllowed();
    }

    /** @return array<string,mixed> */
    public function testConnection(): array
    {
        $response = $this->request('GET', '/transactional', null, ['limit' => 1]);

        return [
            'ok' => true,
            'templates' => (int)($response['meta']['total'] ?? count((array)($response['data'] ?? []))),
        ];
    }

    /** @return array<string,mixed> */
    public function subscribersPage(int $limit = 100, int $page = 1): array
    {
        return $this->request('GET', '/subscribers', null, [
            'limit' => max(1, min(100, $limit)),
            'page' => max(1, $page),
            'order' => 'created',
            'direction' => 'desc',
        ]);
    }

    /**
     * @return array{rows:array<int,array<string,mixed>>,reported_total:int,truncated:bool,pages:int}
     */
    public function allSubscribers(int $maxRows = 20000): array
    {
        $maxRows = max(100, min(50000, $maxRows));
        $rows = [];
        $page = 1;
        $reportedTotal = 0;
        $lastPage = 1;
        $truncated = false;

        do {
            $response = $this->subscribersPage(100, $page);
            $data = $response['data'] ?? [];
            if (!is_array($data)) {
                throw new SenderApiException('Sender returned an unreadable subscriber-list response.');
            }

            foreach ($data as $row) {
                if (!is_array($row)) continue;
                $rows[] = $row;
                if (count($rows) >= $maxRows) {
                    $truncated = true;
                    break 2;
                }
            }

            $meta = is_array($response['meta'] ?? null) ? $response['meta'] : [];
            $reportedTotal = max($reportedTotal, (int)($meta['total'] ?? count($rows)));
            $lastPage = max($page, (int)($meta['last_page'] ?? $page));
            $hasMore = array_key_exists('has_more_resources', $response)
                ? (bool)$response['has_more_resources']
                : $page < $lastPage;

            $page++;
        } while ($hasMore && $page <= $lastPage && count($rows) < $maxRows);

        if ($reportedTotal > count($rows)) {
            $truncated = true;
        }

        return [
            'rows' => $rows,
            'reported_total' => $reportedTotal ?: count($rows),
            'truncated' => $truncated,
            'pages' => max(1, $page - 1),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function transactionalTemplates(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $payload = $this->cachedRead(
            'transactional:list:' . $limit,
            self::CATALOG_FRESH_SECONDS,
            self::CATALOG_MAX_STALE_SECONDS,
            function () use ($limit): array {
                $response = $this->request('GET', '/transactional', null, ['limit' => $limit]);
                $rows = $response['data'] ?? [];
                return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
            }
        );

        return array_values(array_filter($payload, 'is_array'));
    }

    /** @return array<string,mixed> */
    public function transactionalTemplate(string $id): array
    {
        $id = $this->cleanId($id);
        $payload = $this->cachedRead(
            'transactional:item:' . $id,
            self::CATALOG_FRESH_SECONDS,
            self::CATALOG_MAX_STALE_SECONDS,
            function () use ($id): array {
                $response = $this->request('GET', '/transactional/' . rawurlencode($id));
                $row = $response['data'] ?? null;
                if (!is_array($row)) {
                    throw new SenderApiException('Sender returned an unreadable template response.');
                }
                return $row;
            },
            true
        );

        return $payload;
    }

    /**
     * @param array<string,mixed> $variables
     * @return array<string,mixed>
     */
    public function sendTemplate(string $templateId, string $email, string $name = '', array $variables = []): array
    {
        $templateId = $this->cleanId($templateId);
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Recipient email is invalid.');
        }

        $payload = [
            'to' => array_filter([
                'email' => $email,
                'name' => trim($name),
            ], static fn(mixed $value): bool => $value !== ''),
        ];

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        $response = $this->request(
            'POST',
            '/message/' . rawurlencode($templateId) . '/send',
            $payload
        );
        if (array_key_exists('success', $response) && $response['success'] === false) {
            throw new SenderApiException(
                trim((string)($response['message'] ?? 'Sender rejected the message.')),
                422
            );
        }
        return $response;
    }

    /** @return array<int,array<string,mixed>> */
    public function campaigns(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $payload = $this->cachedRead(
            'campaigns:list:' . $limit,
            self::CATALOG_FRESH_SECONDS,
            self::CATALOG_MAX_STALE_SECONDS,
            function () use ($limit): array {
                $response = $this->request('GET', '/campaigns', null, ['limit' => $limit]);
                $rows = $response['data'] ?? [];
                return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
            }
        );

        return array_values(array_filter($payload, 'is_array'));
    }

    /** @return array<string,mixed> */
    public function campaign(string $id): array
    {
        $id = $this->cleanId($id);
        $payload = $this->cachedRead(
            'campaigns:item:' . $id,
            self::CATALOG_FRESH_SECONDS,
            self::CATALOG_MAX_STALE_SECONDS,
            function () use ($id): array {
                $response = $this->request('GET', '/campaigns/' . rawurlencode($id));
                $row = $response['data'] ?? null;
                if (!is_array($row)) {
                    throw new SenderApiException('Sender returned an unreadable campaign response.');
                }
                return $row;
            },
            true
        );

        return $payload;
    }

    public function createGroup(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            throw new \InvalidArgumentException('Sender group title is required.');
        }

        $response = $this->request('POST', '/groups', [
            'title' => substr($title, 0, 255),
        ]);
        $id = trim((string)($response['data']['id'] ?? $response['id'] ?? ''));

        if ($id === '') {
            throw new SenderApiException('Sender did not return a group ID.', 422);
        }

        return $id;
    }

    /** @param array<int,string> $emails @return array<string,mixed> */
    public function addSubscribersToGroup(string $groupId, array $emails, bool $triggerAutomation = false): array
    {
        $groupId = $this->cleanId($groupId);
        $clean = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string)$email));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) $clean[$email] = $email;
        }
        if ($clean === []) {
            throw new \InvalidArgumentException('At least one valid subscriber email is required.');
        }

        $response = $this->request(
            'POST',
            '/subscribers/groups/' . rawurlencode($groupId),
            [
                'subscribers' => array_values($clean),
                'trigger_automation' => $triggerAutomation,
            ]
        );
        if (array_key_exists('success', $response) && $response['success'] === false) {
            throw new SenderApiException(trim((string)($response['message'] ?? 'Sender rejected the group update.')), 422);
        }
        return $response;
    }

    /** @param array<int,string> $groups @return array<string,mixed> */
    public function createSubscriber(string $email, string $name = '', array $groups = [], bool $triggerAutomation = false): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Subscriber email is invalid.');
        }

        $parts = preg_split('/\s+/', trim($name), 2) ?: [];
        $payload = [
            'email' => $email,
            'trigger_automation' => $triggerAutomation,
        ];
        if (($parts[0] ?? '') !== '') $payload['firstname'] = substr((string)$parts[0], 0, 100);
        if (($parts[1] ?? '') !== '') $payload['lastname'] = substr((string)$parts[1], 0, 100);

        $groupIds = [];
        foreach ($groups as $group) {
            $group = $this->cleanId((string)$group);
            $groupIds[$group] = $group;
        }
        if ($groupIds !== []) $payload['groups'] = array_values($groupIds);

        $response = $this->request('POST', '/subscribers', $payload);
        if (array_key_exists('success', $response) && $response['success'] === false) {
            throw new SenderApiException(trim((string)($response['message'] ?? 'Sender rejected the subscriber.')), 422);
        }
        return $response;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    public function createCampaign(array $payload): array
    {
        foreach (['subject','from','reply_to','content_type','content'] as $required) {
            if (trim((string)($payload[$required] ?? '')) === '') {
                throw new \InvalidArgumentException('Sender campaign field ' . $required . ' is required.');
            }
        }
        if (!in_array((string)$payload['content_type'], ['html','text'], true)) {
            throw new \InvalidArgumentException('Sender campaign content type must be html or text.');
        }

        $response = $this->request('POST', '/campaigns', $payload);
        if (array_key_exists('success', $response) && $response['success'] === false) {
            throw new SenderApiException(trim((string)($response['message'] ?? 'Sender rejected the campaign.')), 422);
        }
        return $response;
    }

    /** @return array<string,mixed> */
    public function sendCampaign(string $campaignId): array
    {
        $campaignId = $this->cleanId($campaignId);
        $response = $this->request('POST', '/campaigns/' . rawurlencode($campaignId) . '/send', []);
        if (array_key_exists('success', $response) && $response['success'] === false) {
            throw new SenderApiException(trim((string)($response['message'] ?? 'Sender rejected the campaign send.')), 422);
        }
        return $response;
    }

    /** @return array<string,mixed> */
    public function sentMessages(string $templateId, int $limit = 25, int $page = 1, ?string $email = null): array
    {
        $templateId = $this->cleanId($templateId);
        $query = [
            'limit' => max(1, min(100, $limit)),
            'page' => max(1, $page),
            'direction' => 'desc',
        ];

        if ($email !== null && trim($email) !== '') {
            $query['email_address'] = strtolower(trim($email));
        }

        return $this->request(
            'GET',
            '/transactional/' . rawurlencode($templateId) . '/sent',
            null,
            $query
        );
    }

    /**
     * @param array<string,mixed>|null $payload
     * @param array<string,int|string> $query
     * @return array<string,mixed>
     */
    private function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        $token = trim((string)($this->settings->sender()['api_token'] ?? ''));
        if ($token === '') {
            throw new SenderApiException('Sender API token is not configured.');
        }

        $this->apiState->assertRequestAllowed();

        $url = self::BASE_URL . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $encoded = null;
        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded)) {
                throw new SenderApiException('Could not encode Sender request.');
            }
        }

        $curl = curl_init($url);
        if ($curl === false) {
            throw new SenderApiException('Could not initialize Sender request.');
        }

        $responseHeaders = [];
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'MediaPitch-Store-Sender/1.0',
            CURLOPT_HEADERFUNCTION => static function ($curlHandle, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return $length;
            },
        ]);

        if ($encoded !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $encoded);
        }

        $body = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($body)) {
            $message = $curlError !== '' ? 'Sender request failed: ' . $curlError : 'Sender request failed.';
            $this->apiState->recordResponse(0, $responseHeaders, $message);
            throw new SenderApiException($message);
        }

        $decoded = $body !== '' ? json_decode($body, true) : [];
        if (!is_array($decoded)) {
            $decoded = [];
        }

        if ($status < 200 || $status >= 300) {
            $message = trim((string)($decoded['message'] ?? $decoded['error'] ?? ''));
            if ($message === '') {
                $message = 'Sender API returned HTTP ' . $status . '.';
            }

            $this->apiState->recordResponse($status, $responseHeaders, $message);
            throw new SenderApiException(
                substr($message, 0, 500),
                $status,
                $this->retryAfterSeconds($responseHeaders)
            );
        }

        $this->apiState->recordResponse($status, $responseHeaders);
        return $decoded;
    }

    /**
     * @return array<string,mixed>|array<int,mixed>
     */
    private function cachedRead(
        string $key,
        int $freshSeconds,
        int $maxStaleSeconds,
        callable $loader,
        bool $annotate = false
    ): array {
        $cached = $this->resourceCache->get($key);
        if ($cached !== null && $cached['age_seconds'] <= $freshSeconds) {
            return $this->annotateCachePayload($cached['payload'], $cached, false, $annotate);
        }

        try {
            $payload = $loader();
            if (!is_array($payload)) {
                throw new SenderApiException('Sender returned an unreadable cached resource.');
            }
            $this->resourceCache->put($key, $payload);
            if ($annotate && !array_is_list($payload)) {
                $payload['_sender_cache_source'] = 'live';
                $payload['_sender_cache_stale'] = false;
                $payload['_sender_cache_age_seconds'] = 0;
            }
            return $payload;
        } catch (SenderApiException $e) {
            if ($cached !== null && $cached['age_seconds'] <= $maxStaleSeconds && $e->retryable()) {
                return $this->annotateCachePayload($cached['payload'], $cached, true, $annotate);
            }
            throw $e;
        }
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $payload
     * @param array{payload:array<string,mixed>|array<int,mixed>,synced_at:string,age_seconds:int} $meta
     * @return array<string,mixed>|array<int,mixed>
     */
    private function annotateCachePayload(array $payload, array $meta, bool $stale, bool $annotate): array
    {
        if (!$annotate || array_is_list($payload)) return $payload;

        $payload['_sender_cache_source'] = 'cache';
        $payload['_sender_cache_stale'] = $stale;
        $payload['_sender_cache_age_seconds'] = (int)$meta['age_seconds'];
        $payload['_sender_cache_synced_at'] = (string)$meta['synced_at'];
        return $payload;
    }

    /** @param array<string,string> $headers */
    private function retryAfterSeconds(array $headers): ?int
    {
        $retry = trim((string)($headers['retry-after'] ?? ''));
        if ($retry !== '') {
            if (ctype_digit($retry)) return max(1, (int)$retry);
            $ts = strtotime($retry);
            if ($ts !== false && $ts > time()) return max(1, $ts - time());
        }

        $reset = trim((string)($headers['x-ratelimit-reset'] ?? ''));
        if ($reset !== '') {
            if (ctype_digit($reset)) {
                $number = (int)$reset;
                $ts = $number > 1000000000 ? $number : time() + max(1, $number);
            } else {
                $ts = strtotime($reset);
            }
            if ($ts !== false && $ts > time()) return max(1, $ts - time());
        }

        return null;
    }

    private function cleanId(string $id): string
    {
        $id = trim($id);
        if ($id === '' || strlen($id) > 100 || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            throw new \InvalidArgumentException('Sender resource ID is invalid.');
        }

        return $id;
    }
}
