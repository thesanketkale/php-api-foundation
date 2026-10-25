<?php

declare(strict_types=1);

namespace App\Http;

use App\Support\ProblemDetailsException;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastRoute\simpleDispatcher;

final class Router implements RequestHandlerInterface
{
    private ContainerInterface $container;
    private Dispatcher $dispatcher;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $routesDefinition = require dirname(__DIR__, 2) . '/config/routes.php';

        $this->dispatcher = simpleDispatcher(static function (RouteCollector $r) use ($routesDefinition): void {
            $routesDefinition($r);
        });
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $uri = $request->getUri()->getPath();

        // Strip trailing slash except for root
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        $routeInfo = $this->dispatcher->dispatch($method, $uri);

        switch ($routeInfo[0]) {
            case Dispatcher::NOT_FOUND:
                throw new ProblemDetailsException(
                    type: 'https://api.example.com/errors/not-found',
                    title: 'Not Found',
                    status: 404,
                    detail: sprintf('Route "%s %s" was not found on this server.', $method, $uri),
                    instance: $uri
                );

            case Dispatcher::METHOD_NOT_ALLOWED:
                /** @var list<string> $allowedMethods */
                $allowedMethods = $routeInfo[1];
                throw new ProblemDetailsException(
                    type: 'https://api.example.com/errors/method-not-allowed',
                    title: 'Method Not Allowed',
                    status: 405,
                    detail: sprintf('Method "%s" is not allowed for route "%s". Allowed: %s', $method, $uri, implode(', ', $allowedMethods)),
                    instance: $uri,
                    extensions: ['allowed_methods' => $allowedMethods]
                );

            case Dispatcher::FOUND:
                /** @var class-string<RequestHandlerInterface> $handlerClass */
                $handlerClass = $routeInfo[1];
                /** @var array<string, string> $vars */
                $vars = $routeInfo[2];

                foreach ($vars as $param => $value) {
                    $request = $request->withAttribute($param, $value);
                }

                /** @var RequestHandlerInterface $action */
                $action = $this->container->get($handlerClass);
                return $action->handle($request);

            default:
                throw new ProblemDetailsException(
                    type: 'https://api.example.com/errors/internal-server-error',
                    title: 'Routing Error',
                    status: 500,
                    detail: 'An error occurred while resolving the route.',
                    instance: $uri
                );
        }
    }
}
