<?php

declare(strict_types=1);

namespace MediaPitch\Services;

final class EmailValidationClient
{
    /** @return array<string,mixed> */
    public function validate(string $email): array
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'email' => $email,
                'status' => 'invalid',
                'reason' => 'Invalid email syntax',
                'suggestion' => null,
            ];
        }

        $url = trim((string)\env('EMAIL_VALIDATOR_API_URL', 'https://mediapitch.in/mail-list-cleaner/api.php'));
        if ($url === '') {
            return $this->unknown($email, 'Remote email validation is disabled');
        }

        $payload = json_encode(['email' => $email], JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return $this->unknown($email, 'Could not prepare validation request');
        }

        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $apiKey = trim((string)\env('EMAIL_VALIDATOR_API_KEY', ''));
        if ($apiKey !== '') {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        $curl = curl_init($url);
        if ($curl === false) {
            return $this->unknown($email, 'Email validation service is temporarily unavailable');
        }

        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 4,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'MediaPitch-Store-Newsletter/1.0',
        ]);

        $body = curl_exec($curl);
        $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if (!is_string($body) || $body === '' || $statusCode < 200 || $statusCode >= 300) {
            return $this->unknown(
                $email,
                $curlError !== '' ? 'Email validation request failed' : 'Email validation service returned an unavailable response'
            );
        }

        $decoded = json_decode($body, true);
        $row = is_array($decoded) && isset($decoded['results'][0]) && is_array($decoded['results'][0])
            ? $decoded['results'][0]
            : null;

        if ($row === null || !in_array((string)($row['status'] ?? ''), ['clean', 'risky', 'unknown', 'invalid'], true)) {
            return $this->unknown($email, 'Email validation service returned an unreadable response');
        }

        return $row;
    }

    /**
     * Validate many addresses concurrently so campaign pre-cleaning does not
     * turn into one network round-trip per subscriber.
     *
     * @param array<int,string> $emails
     * @return array<string,array<string,mixed>>
     */
    public function validateMany(array $emails, int $concurrency = 10): array
    {
        $normalized = [];
        foreach ($emails as $email) {
            $email = strtolower(trim((string)$email));
            if ($email === '') continue;
            $normalized[$email] = $email;
        }

        $results = [];
        $pending = [];
        foreach ($normalized as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[$email] = [
                    'email' => $email,
                    'status' => 'invalid',
                    'reason' => 'Invalid email syntax',
                    'suggestion' => null,
                ];
            } else {
                $pending[] = $email;
            }
        }

        if ($pending === []) return $results;

        $url = trim((string)\env('EMAIL_VALIDATOR_API_URL', 'https://mediapitch.in/mail-list-cleaner/api.php'));
        if ($url === '') {
            foreach ($pending as $email) {
                $results[$email] = $this->unknown($email, 'Remote email validation is disabled');
            }
            return $results;
        }

        $concurrency = max(1, min(20, $concurrency));
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        $apiKey = trim((string)\env('EMAIL_VALIDATOR_API_KEY', ''));
        if ($apiKey !== '') $headers[] = 'X-API-Key: ' . $apiKey;

        foreach (array_chunk($pending, $concurrency) as $chunk) {
            $multi = curl_multi_init();
            $handles = [];

            foreach ($chunk as $email) {
                $payload = json_encode(['email' => $email], JSON_UNESCAPED_SLASHES);
                if ($payload === false) {
                    $results[$email] = $this->unknown($email, 'Could not prepare validation request');
                    continue;
                }

                $curl = curl_init($url);
                if ($curl === false) {
                    $results[$email] = $this->unknown($email, 'Email validation service is temporarily unavailable');
                    continue;
                }

                curl_setopt_array($curl, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => $headers,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_TIMEOUT => 4,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_USERAGENT => 'MediaPitch-Store-Newsletter/1.0',
                ]);
                curl_multi_add_handle($multi, $curl);
                $handles[$email] = $curl;
            }

            do {
                $status = curl_multi_exec($multi, $running);
                if ($running > 0) {
                    $selected = curl_multi_select($multi, 1.0);
                    if ($selected === -1) usleep(10000);
                }
            } while ($running > 0 && $status === CURLM_OK);

            foreach ($handles as $email => $curl) {
                $body = curl_multi_getcontent($curl);
                $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
                $curlError = curl_error($curl);

                if (!is_string($body) || $body === '' || $statusCode < 200 || $statusCode >= 300) {
                    $results[$email] = $this->unknown(
                        $email,
                        $curlError !== ''
                            ? 'Email validation request failed'
                            : 'Email validation service returned an unavailable response'
                    );
                } else {
                    $decoded = json_decode($body, true);
                    $row = is_array($decoded) && isset($decoded['results'][0]) && is_array($decoded['results'][0])
                        ? $decoded['results'][0]
                        : null;

                    if ($row === null || !in_array((string)($row['status'] ?? ''), ['clean','risky','unknown','invalid'], true)) {
                        $results[$email] = $this->unknown($email, 'Email validation service returned an unreadable response');
                    } else {
                        $results[$email] = $row;
                    }
                }

                curl_multi_remove_handle($multi, $curl);
                curl_close($curl);
            }

            curl_multi_close($multi);
        }

        return $results;
    }

    /** @return array<string,mixed> */
    private function unknown(string $email, string $reason): array
    {
        return [
            'email' => $email,
            'status' => 'unknown',
            'reason' => $reason,
            'suggestion' => null,
        ];
    }
}
