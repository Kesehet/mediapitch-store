<?php

declare(strict_types=1);

namespace MediaPitch\Amazon;

interface ProductProviderInterface
{
    public function isAvailable(): bool;

    public function search(string $query, int $limit = 10): array;

    public function fetchByAsin(string $asin): ?array;
}
