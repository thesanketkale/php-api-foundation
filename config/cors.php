<?php

declare(strict_types=1);

use App\Support\Config;

return static function (Config $config): array {
    $allowedOriginsRaw = $config->getString('CORS_ALLOWED_ORIGINS', '*');
    $allowedOrigins = array_map('trim', explode(',', $allowedOriginsRaw));

    return [
        'allowed_origins' => $allowedOrigins,
        'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        'allowed_headers' => [
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'X-Request-Id',
            'If-Match',
            'Accept',
        ],
        'exposed_headers' => [
            'X-Request-Id',
            'X-RateLimit-Limit',
            'X-RateLimit-Remaining',
            'X-RateLimit-Reset',
            'ETag',
            'Location',
        ],
        'max_age' => $config->getInt('CORS_MAX_AGE', 86400),
        'supports_credentials' => $config->getBool('CORS_SUPPORTS_CREDENTIALS', false),
    ];
};
