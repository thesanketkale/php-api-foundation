<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Domain\Auth\TokenFamilyService;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LogoutAction implements RequestHandlerInterface
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

        if ($refreshToken !== '') {
            $this->tokenFamilyService->revokeToken($refreshToken);
        }

        return new Response(204);
    }
}
