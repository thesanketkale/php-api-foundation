<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class AccessLogMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $startTime = microtime(true);
        $response = $handler->handle($request);
        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        $requestId = (string) $request->getAttribute(RequestIdMiddleware::ATTRIBUTE, '');
        $status = $response->getStatusCode();

        $context = [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'status' => $status,
            'duration_ms' => $durationMs,
            'user_agent' => $request->getHeaderLine('User-Agent'),
        ];

        if ($status >= 500) {
            $this->logger->error('HTTP Request Failed', $context);
        } elseif ($status >= 400) {
            $this->logger->warning('HTTP Client Error', $context);
        } else {
            $this->logger->info('HTTP Request Handled', $context);
        }

        return $response;
    }
}
