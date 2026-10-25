<?php

declare(strict_types=1);

namespace App\Tests;

use App\Domain\User\User;
use App\Http\App;
use App\Infrastructure\Token\JwtService;
use App\Support\Config;
use DI\ContainerBuilder;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class TestCase extends BaseTestCase
{
    protected ?App $app = null;
    protected ?ContainerInterface $container = null;
    protected ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create persistent in-memory SQLite instance for test duration
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON;');

        // Create schema
        $this->pdo->exec('
            CREATE TABLE users (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            );

            CREATE TABLE refresh_tokens (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                token_family VARCHAR(36) NOT NULL,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                sequence INTEGER NOT NULL DEFAULT 1,
                is_revoked INTEGER NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            );

            CREATE TABLE notes (
                id VARCHAR(36) NOT NULL PRIMARY KEY,
                user_id VARCHAR(36) NOT NULL,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                version INTEGER NOT NULL DEFAULT 1,
                etag VARCHAR(64) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            );
        ');

        $testConfig = new Config([
            'APP_NAME' => 'PHP API Foundation Test',
            'APP_ENV' => 'testing',
            'APP_DEBUG' => true,
            'APP_URL' => 'http://localhost:8080',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'JWT_SECRET' => 'testing_jwt_secret_must_be_at_least_32_characters_long_for_security!',
            'JWT_ALGORITHM' => 'HS256',
            'JWT_ISSUER' => 'php-api-foundation-test',
            'JWT_ACCESS_TTL' => 900,
            'REFRESH_TOKEN_TTL' => 2592000,
            'CORS_ALLOWED_ORIGINS' => '*',
            'CORS_SUPPORTS_CREDENTIALS' => false,
            'CORS_MAX_AGE' => 86400,
            'RATE_LIMIT_DRIVER' => 'file',
            'RATE_LIMIT_STORAGE_PATH' => sys_get_temp_dir() . '/php_api_test_ratelimit',
            'RATE_LIMIT_GLOBAL_LIMIT' => 1000,
            'RATE_LIMIT_GLOBAL_WINDOW' => 60,
            'RATE_LIMIT_AUTH_LIMIT' => 100,
            'RATE_LIMIT_AUTH_WINDOW' => 60,
            'LOG_CHANNEL' => 'test',
            'LOG_PATH' => 'storage/logs/test.log',
        ]);

        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);
        $builder->addDefinitions(dirname(__DIR__) . '/config/dependencies.php');
        $builder->addDefinitions([
            Config::class => $testConfig,
            PDO::class => $this->pdo,
        ]);

        $this->container = $builder->build();
        $this->app = new App($this->container);
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array<string, string> $headers
     * @param array<string, mixed>|null $body
     * @return ServerRequestInterface
     */
    protected function createRequest(string $method, string $uri, array $headers = [], ?array $body = null): ServerRequestInterface
    {
        $psr17Factory = new Psr17Factory();
        $request = new ServerRequest($method, $uri, $headers);

        if ($body !== null) {
            $stream = $psr17Factory->createStream((string) json_encode($body));
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($stream)
                ->withParsedBody($body);
        }

        return $request;
    }

    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        /** @var App $app */
        $app = $this->app;
        return $app->handle($request);
    }

    protected function createUser(string $email = 'test@example.com', string $password = 'P@ssw0rd123!'): User
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $userId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $user = User::create($userId, $email, $password);
        /** @var \App\Domain\User\UserRepositoryInterface $repo */
        $repo = $this->container->get(\App\Domain\User\UserRepositoryInterface::class);
        $repo->save($user);

        return $user;
    }

    protected function generateAccessToken(User $user): string
    {
        /** @var JwtService $jwt */
        $jwt = $this->container->get(JwtService::class);
        return $jwt->createAccessToken($user->getId(), ['email' => $user->getEmail()]);
    }
}
