<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ProblemDetailsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');

        if (stripos($contentType, 'application/json') !== false) {
            $rawBody = (string) $request->getBody();

            if (trim($rawBody) !== '') {
                $parsed = json_decode($rawBody, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new ProblemDetailsException(
                        type: 'https://api.example.com/errors/bad-request',
                        title: 'Malformed JSON',
                        status: 400,
                        detail: 'Syntax error in JSON request body: ' . json_last_error_msg()
                    );
                }

                if (is_array($parsed)) {
                    $request = $request->withParsedBody($parsed);
                }
            }
        }

        return $handler->handle($request);
    }
}
