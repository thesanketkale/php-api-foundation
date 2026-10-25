<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\RateLimiter\RateLimiterInterface;
use App\Support\Config;
use App\Support\ProblemDetailsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiterInterface $rateLimiter;
    private int $globalLimit;
    private int $globalWindow;
    private int $authLimit;
    private int $authWindow;

    public function __construct(RateLimiterInterface $rateLimiter, Config $config)
    {
        $this->rateLimiter = $rateLimiter;
        $this->globalLimit = $config->getInt('RATE_LIMIT_GLOBAL_LIMIT', 300);
        $this->globalWindow = $config->getInt('RATE_LIMIT_GLOBAL_WINDOW', 60);
        $this->authLimit = $config->getInt('RATE_LIMIT_AUTH_LIMIT', 10);
        $this->authWindow = $config->getInt('RATE_LIMIT_AUTH_WINDOW', 60);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $this->getClientIp($request);
        $path = $request->getUri()->getPath();

        $isAuthRoute = str_contains($path, '/auth/login') || str_contains($path, '/auth/register');
        $limit = $isAuthRoute ? $this->authLimit : $this->globalLimit;
        $window = $isAuthRoute ? $this->authWindow : $this->globalWindow;
        $prefix = $isAuthRoute ? 'auth' : 'global';

        $key = sprintf('%s:%s', $prefix, $ip);
        $result = $this->rateLimiter->check($key, $limit, $window);

        if (!$result['allowed']) {
            $retryAfter = max(1, $result['reset'] - time());
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/too-many-requests',
                title: 'Too Many Requests',
                status: 429,
                detail: sprintf('Rate limit exceeded. Try again in %d seconds.', $retryAfter),
                extensions: [
                    'headers' => [
                        'Retry-After' => (string) $retryAfter,
                        'X-RateLimit-Limit' => (string) $result['limit'],
                        'X-RateLimit-Remaining' => '0',
                        'X-RateLimit-Reset' => (string) $result['reset'],
                    ],
                ]
            );
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $result['limit'])
            ->withHeader('X-RateLimit-Remaining', (string) $result['remaining'])
            ->withHeader('X-RateLimit-Reset', (string) $result['reset']);
    }

    private function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $raw = (string) $serverParams[$header];
                $parts = explode(',', $raw);
                $ip = trim($parts[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }
}
