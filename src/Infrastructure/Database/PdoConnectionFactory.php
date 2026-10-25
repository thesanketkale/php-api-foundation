<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Support\Config;
use InvalidArgumentException;
use PDO;

final class PdoConnectionFactory
{
    public static function create(Config $config): PDO
    {
        $driver = $config->getString('DB_CONNECTION', 'mysql');

        if ($driver === 'sqlite') {
            $database = $config->getString('DB_DATABASE', ':memory:');
            $dsn = sprintf('sqlite:%s', $database);

            $pdo = new PDO($dsn, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            $pdo->exec('PRAGMA foreign_keys = ON;');
            $pdo->exec('PRAGMA journal_mode = WAL;');

            return $pdo;
        }

        if ($driver === 'mysql') {
            $host = $config->getString('DB_HOST', '127.0.0.1');
            $port = $config->getInt('DB_PORT', 3306);
            $database = $config->getString('DB_DATABASE', 'php_api_foundation');
            $username = $config->getString('DB_USERNAME', 'root');
            $password = $config->getString('DB_PASSWORD', '');
            $charset = 'utf8mb4';

            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);

            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_0900_ai_ci',
            ]);
        }

        throw new InvalidArgumentException(sprintf('Unsupported database driver "%s". Supported: sqlite, mysql.', $driver));
    }
}
