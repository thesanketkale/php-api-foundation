<?php

declare(strict_types=1);

namespace App\Http\Action\Health;

use DateTimeImmutable;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LivenessAction implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $payload = [
            'status' => 'pass',
            'timestamp' => (new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM),
        ];

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES)
        );
    }
}
