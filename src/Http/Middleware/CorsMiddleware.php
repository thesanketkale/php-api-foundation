<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Config;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private array $allowedOrigins;
    /**
     * @var list<string>
     */
    private array $allowedMethods;
    /**
     * @var list<string>
     */
    private array $allowedHeaders;
    /**
     * @var list<string>
     */
    private array $exposedHeaders;
    private int $maxAge;
    private bool $supportsCredentials;

    public function __construct(Config $config)
    {
        $corsConfigCallable = require dirname(__DIR__, 3) . '/config/cors.php';
        /** @var array{allowed_origins: list<string>, allowed_methods: list<string>, allowed_headers: list<string>, exposed_headers: list<string>, max_age: int, supports_credentials: bool} $cors */
        $cors = $corsConfigCallable($config);

        $this->allowedOrigins = $cors['allowed_origins'];
        $this->allowedMethods = $cors['allowed_methods'];
        $this->allowedHeaders = $cors['allowed_headers'];
        $this->exposedHeaders = $cors['exposed_headers'];
        $this->maxAge = $cors['max_age'];
        $this->supportsCredentials = $cors['supports_credentials'];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Handle preflight OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Response(204);
            return $this->applyCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);

        return $this->applyCorsHeaders($response, $origin);
    }

    private function applyCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        if ($origin === '') {
            return $response;
        }

        $allowOrigin = '';
        if (in_array('*', $this->allowedOrigins, true)) {
            $allowOrigin = $this->supportsCredentials ? $origin : '*';
        } elseif (in_array($origin, $this->allowedOrigins, true)) {
            $allowOrigin = $origin;
        }

        if ($allowOrigin === '') {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->supportsCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
