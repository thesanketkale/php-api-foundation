<?php

declare(strict_types=1);

use App\Support\Config;

return static function (Config $config): array {
    return [
        'jwt' => [
            'secret' => $config->getString('JWT_SECRET'),
            'algorithm' => $config->getString('JWT_ALGORITHM', 'HS256'),
            'issuer' => $config->getString('JWT_ISSUER', 'php-api-foundation'),
            'access_ttl' => $config->getInt('JWT_ACCESS_TTL', 900), // 15 minutes in seconds
            'public_key' => $config->getString('JWT_PUBLIC_KEY', ''),
            'private_key' => $config->getString('JWT_PRIVATE_KEY', ''),
        ],
        'refresh_token' => [
            'ttl' => $config->getInt('REFRESH_TOKEN_TTL', 2592000), // 30 days in seconds
        ],
        'argon2id' => [
            'memory_cost' => PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
            'time_cost' => PASSWORD_ARGON2_DEFAULT_TIME_COST,
            'threads' => PASSWORD_ARGON2_DEFAULT_THREADS,
        ],
    ];
};
