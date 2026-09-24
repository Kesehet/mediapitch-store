<?php

declare(strict_types=1);

namespace MediaPitch\Services;

final class SenderApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $statusCode = 0,
        public readonly ?int $retryAfter = null
    ) {
        parent::__construct($message, $statusCode);
    }

    public function retryable(): bool
    {
        return $this->statusCode === 0 || $this->statusCode === 429 || $this->statusCode >= 500;
    }
}
