<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$root = __DIR__;
if (file_exists($root . '/.env')) {
    $dotenv = Dotenv::createImmutable($root);
    $dotenv->safeLoad();
}

$driver = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? 'mysql');

$connectionConfig = [];

if ($driver === 'sqlite') {
    $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? ($root . '/storage/database.sqlite'));
    if ($database !== ':memory:' && !file_exists($database)) {
        $dir = dirname($database);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        touch($database);
    }

    $connectionConfig = [
        'adapter' => 'sqlite',
        'name' => $database,
        'suffix' => '',
    ];
} else {
    $connectionConfig = [
        'adapter' => 'mysql',
        'host' => getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1'),
        'name' => getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? 'php_api_foundation'),
        'user' => getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root'),
        'pass' => getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ''),
        'port' => (int) (getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? 3306)),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_0900_ai_ci',
    ];
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => $connectionConfig,
        'testing' => [
            'adapter' => 'sqlite',
            'name' => ':memory:',
            'suffix' => '',
        ],
        'test_file' => [
            'adapter' => 'sqlite',
            'name' => $root . '/storage/test.sqlite',
            'suffix' => '',
        ],
        'production' => $connectionConfig,
    ],
    'version_order' => 'creation',
];
