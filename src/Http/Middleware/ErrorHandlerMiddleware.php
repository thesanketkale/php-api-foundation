<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Config;
use App\Support\ProblemDetailsException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;
    private bool $debug;

    public function __construct(LoggerInterface $logger, Config $config)
    {
        $this->logger = $logger;
        $this->debug = $config->getBool('APP_DEBUG', false);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ProblemDetailsException $e) {
            $payload = $e->toArray();
            if ($e->getInstance() === null) {
                $payload['instance'] = $request->getUri()->getPath();
            }

            $response = new Response(
                $e->getStatus(),
                ['Content-Type' => 'application/problem+json; charset=utf-8'],
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );

            // If extensions contain custom response headers (e.g. Retry-After)
            $extensions = $e->getExtensions();
            if (isset($extensions['headers']) && is_array($extensions['headers'])) {
                foreach ($extensions['headers'] as $hName => $hVal) {
                    $response = $response->withHeader((string) $hName, (string) $hVal);
                }
            }

            return $response;
        } catch (Throwable $e) {
            $requestId = (string) $request->getAttribute(RequestIdMiddleware::ATTRIBUTE, 'unknown');

            $this->logger->error('Unhandled server exception', [
                'request_id' => $requestId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $payload = [
                'type' => 'https://api.example.com/errors/internal-server-error',
                'title' => 'Internal Server Error',
                'status' => 500,
                'detail' => $this->debug ? $e->getMessage() : 'An unexpected internal server error occurred.',
                'instance' => $request->getUri()->getPath(),
            ];

            if ($this->debug) {
                $payload['debug'] = [
                    'class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            return new Response(
                500,
                ['Content-Type' => 'application/problem+json; charset=utf-8'],
                (string) json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );
        }
    }
}
