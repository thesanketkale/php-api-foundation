<?php

declare(strict_types=1);

namespace App\Http\Action\Health;

use App\Support\ProblemDetailsException;
use DateTimeImmutable;
use Nyholm\Psr7\Response;
use PDO;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class ReadinessAction implements RequestHandlerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $stmt = $this->pdo->query('SELECT 1');
            if ($stmt === false) {
                throw new \RuntimeException('Failed to execute test query against database.');
            }

            $payload = [
                'status' => 'pass',
                'database' => 'connected',
                'timestamp' => (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM),
            ];

            return new Response(
                200,
                ['Content-Type' => 'application/json; charset=utf-8'],
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $e) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/service-unavailable',
                title: 'Service Unavailable',
                status: 503,
                detail: 'Database connectivity probe failed: ' . $e->getMessage(),
                previous: $e
            );
        }
    }
}
