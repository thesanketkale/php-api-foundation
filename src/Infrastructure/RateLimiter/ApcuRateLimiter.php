<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiter;

final class ApcuRateLimiter implements RateLimiterInterface
{
    /**
     * @param string $key
     * @param int $limit
     * @param int $windowSeconds
     * @return array{allowed: bool, limit: int, remaining: int, reset: int}
     */
    public function check(string $key, int $limit, int $windowSeconds): array
    {
        $now = time();
        $cacheKey = 'rl:' . $key;
        $resetKey = 'rl:rst:' . $key;

        $reset = apcu_fetch($resetKey);
        if ($reset === false || !is_int($reset) || $reset <= $now) {
            $reset = $now + $windowSeconds;
            apcu_store($resetKey, $reset, $windowSeconds);
            apcu_store($cacheKey, 1, $windowSeconds);
            $count = 1;
        } else {
            $count = (int) apcu_inc($cacheKey);
        }

        $allowed = $count <= $limit;
        $remaining = max(0, $limit - $count);

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset,
        ];
    }
}
