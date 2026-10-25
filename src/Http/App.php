<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Middleware\AccessLogMiddleware;
use App\Http\Middleware\CorsMiddleware;
use App\Http\Middleware\ErrorHandlerMiddleware;
use App\Http\Middleware\JsonBodyParserMiddleware;
use App\Http\Middleware\JwtAuthenticationMiddleware;
use App\Http\Middleware\RateLimitMiddleware;
use App\Http\Middleware\RequestIdMiddleware;
use App\Http\Middleware\SecurityHeadersMiddleware;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Relay\Relay;

final class App
{
    private ContainerInterface $container;
    private Relay $relay;

    public function __construct(?ContainerInterface $container = null)
    {
        if ($container === null) {
            $builder = new ContainerBuilder();
            $builder->useAutowiring(true);
            $builder->addDefinitions(dirname(__DIR__, 2) . '/config/dependencies.php');
            $container = $builder->build();
        }

        $this->container = $container;

        $queue = [
            $this->container->get(ErrorHandlerMiddleware::class),
            $this->container->get(RequestIdMiddleware::class),
            $this->container->get(AccessLogMiddleware::class),
            $this->container->get(SecurityHeadersMiddleware::class),
            $this->container->get(CorsMiddleware::class),
            $this->container->get(RateLimitMiddleware::class),
            $this->container->get(JsonBodyParserMiddleware::class),
            $this->container->get(JwtAuthenticationMiddleware::class),
            new Router($this->container),
        ];

        $this->relay = new Relay($queue);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->relay->handle($request);
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }
}
