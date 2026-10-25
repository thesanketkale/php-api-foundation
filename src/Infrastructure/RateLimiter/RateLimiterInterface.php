<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiter;

interface RateLimiterInterface
{
    /**
     * @param string $key
     * @param int $limit
     * @param int $windowSeconds
     * @return array{allowed: bool, limit: int, remaining: int, reset: int}
     */
    public function check(string $key, int $limit, int $windowSeconds): array;
}
