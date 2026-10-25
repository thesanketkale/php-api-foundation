<?php

declare(strict_types=1);

use App\Support\Config;

return static function (Config $config): array {
    $driver = $config->getString('DB_CONNECTION', 'mysql');

    return [
        'default' => $driver,
        'connections' => [
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $config->getString('DB_DATABASE', ':memory:'),
                'foreign_keys' => true,
            ],
            'mysql' => [
                'driver' => 'mysql',
                'host' => $config->getString('DB_HOST', '127.0.0.1'),
                'port' => $config->getInt('DB_PORT', 3306),
                'database' => $config->getString('DB_DATABASE', 'php_api_foundation'),
                'username' => $config->getString('DB_USERNAME', 'root'),
                'password' => $config->getString('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_0900_ai_ci',
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ],
        ],
    ];
};
