<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Domain\Auth\TokenFamilyService;
use App\Support\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RefreshTokenAction implements RequestHandlerInterface
{
    private TokenFamilyService $tokenFamilyService;

    public function __construct(TokenFamilyService $tokenFamilyService)
    {
        $this->tokenFamilyService = $tokenFamilyService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);

        $refreshToken = trim((string) ($body['refresh_token'] ?? ''));

        if ($refreshToken === '') {
            throw new ValidationException([
                'refresh_token' => ['The refresh_token parameter is required.'],
            ], $request->getUri()->getPath());
        }

        $tokens = $this->tokenFamilyService->rotateTokens($refreshToken);

        $payload = [
            'data' => $tokens,
            'meta' => [
                'timestamp' => time(),
            ],
        ];

        return new Response(
            200,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES)
        );
    }
}
