<?php

declare(strict_types=1);

use App\Support\Config;

return static function (Config $config): array {
    return [
        'name' => $config->getString('APP_NAME', 'PHP API Foundation'),
        'env' => $config->getString('APP_ENV', 'production'),
        'debug' => $config->getBool('APP_DEBUG', false),
        'url' => $config->getString('APP_URL', 'http://localhost:8080'),
        'timezone' => $config->getString('APP_TIMEZONE', 'UTC'),
    ];
};
