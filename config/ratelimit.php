<?php

declare(strict_types=1);

use App\Support\Config;

return static function (Config $config): array {
    return [
        'driver' => $config->getString('RATE_LIMIT_DRIVER', 'auto'), // auto, apcu, file
        'storage_path' => $config->getString('RATE_LIMIT_STORAGE_PATH', dirname(__DIR__) . '/storage/cache/ratelimit'),
        'global' => [
            'limit' => $config->getInt('RATE_LIMIT_GLOBAL_LIMIT', 300),
            'window' => $config->getInt('RATE_LIMIT_GLOBAL_WINDOW', 60), // seconds
        ],
        'auth' => [
            'limit' => $config->getInt('RATE_LIMIT_AUTH_LIMIT', 10),
            'window' => $config->getInt('RATE_LIMIT_AUTH_WINDOW', 60), // seconds
        ],
    ];
};
