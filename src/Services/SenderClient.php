<?php

declare(strict_types=1);

namespace MediaPitch\Services;

use MediaPitch\Repositories\SettingsRepository;

final class SenderClient
{
    public function __construct(private readonly SettingsRepository $settings = new SettingsRepository())
    {
    }
    private const BASE_URL = 'https://api.sender.net/v2';

    public function configured(): bool
    {
        return !empty($this->settings->sender()['api_token_configured']);
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

    /** @return array<int,array<string,mixed>> */
    public function transactionalTemplates(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $response = $this->request('GET', '/transactional', null, ['limit' => $limit]);
        $rows = $response['data'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return array<string,mixed> */
    public function transactionalTemplate(string $id): array
    {
        $id = $this->cleanId($id);
        $response = $this->request('GET', '/transactional/' . rawurlencode($id));
        $row = $response['data'] ?? null;

        if (!is_array($row)) {
            throw new SenderApiException('Sender returned an unreadable template response.');
        }

        return $row;
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

        return $this->request(
            'POST',
            '/message/' . rawurlencode($templateId) . '/send',
            $payload
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function campaigns(int $limit = 100): array
    {
        $limit = max(1, min(100, $limit));
        $response = $this->request('GET', '/campaigns', null, ['limit' => $limit]);
        $rows = $response['data'] ?? [];

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    }

    /** @return array<string,mixed> */
    public function campaign(string $id): array
    {
        $id = $this->cleanId($id);
        $response = $this->request('GET', '/campaigns/' . rawurlencode($id));
        $row = $response['data'] ?? null;

        if (!is_array($row)) {
            throw new SenderApiException('Sender returned an unreadable campaign response.');
        }

        return $row;
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
            throw new SenderApiException('Sender created the group but did not return its ID.');
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

        return $this->request(
            'POST',
            '/subscribers/groups/' . rawurlencode($groupId),
            [
                'subscribers' => array_values($clean),
                'trigger_automation' => $triggerAutomation,
            ]
        );
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

        return $this->request('POST', '/subscribers', $payload);
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

        return $this->request('POST', '/campaigns', $payload);
    }

    /** @return array<string,mixed> */
    public function sendCampaign(string $campaignId): array
    {
        $campaignId = $this->cleanId($campaignId);
        return $this->request('POST', '/campaigns/' . rawurlencode($campaignId) . '/send', []);
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
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($body)) {
            throw new SenderApiException(
                $curlError !== '' ? 'Sender request failed: ' . $curlError : 'Sender request failed.'
            );
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

            $retryAfter = null;
            if (isset($responseHeaders['retry-after']) && ctype_digit((string)$responseHeaders['retry-after'])) {
                $retryAfter = max(1, (int)$responseHeaders['retry-after']);
            }

            throw new SenderApiException(substr($message, 0, 500), $status, $retryAfter);
        }

        return $decoded;
    }

    private function cleanId(string $id): string
    {
        $id = trim($id);
        if ($id === '' || strlen($id) > 100 || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            throw new \InvalidArgumentException('Sender template ID is invalid.');
        }

        return $id;
    }
}
