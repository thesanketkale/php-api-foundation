<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiter;

use App\Support\Config;

final class RateLimiterFactory
{
    public static function create(Config $config): RateLimiterInterface
    {
        $driver = $config->getString('RATE_LIMIT_DRIVER', 'auto');
        $storagePath = $config->getString('RATE_LIMIT_STORAGE_PATH', dirname(__DIR__, 3) . '/storage/cache/ratelimit');

        if ($driver === 'apcu' || ($driver === 'auto' && extension_loaded('apcu') && (bool) ini_get('apc.enabled'))) {
            return new ApcuRateLimiter();
        }

        return new FileRateLimiter($storagePath);
    }
}
