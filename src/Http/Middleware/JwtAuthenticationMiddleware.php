<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Token\JwtService;
use App\Support\ProblemDetailsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JwtAuthenticationMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;

    /**
     * @var list<string>
     */
    private array $publicRoutes = [
        '/v1/health',
        '/v1/health/ready',
        '/v1/auth/register',
        '/v1/auth/login',
        '/v1/auth/refresh',
        '/v1/auth/logout',
        '/health',
        '/health/ready',
    ];

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        foreach ($this->publicRoutes as $publicRoute) {
            if ($path === $publicRoute) {
                return $handler->handle($request);
            }
        }

        $authHeader = $request->getHeaderLine('Authorization');
        if ($authHeader === '' || !str_starts_with($authHeader, 'Bearer ')) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/unauthorized',
                title: 'Unauthorized',
                status: 401,
                detail: 'Missing or malformed Authorization header. Bearer token required.'
            );
        }

        $token = trim(substr($authHeader, 7));
        $claims = $this->jwtService->verifyAccessToken($token);

        /** @var string $userId */
        $userId = $claims['sub'] ?? '';
        if ($userId === '') {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/unauthorized',
                title: 'Unauthorized',
                status: 401,
                detail: 'Token is missing valid subject claim.'
            );
        }

        $request = $request
            ->withAttribute('user_id', $userId)
            ->withAttribute('token_claims', $claims);

        return $handler->handle($request);
    }
}
