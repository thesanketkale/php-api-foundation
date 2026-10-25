<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Domain\User\UserRepositoryInterface;
use App\Support\ProblemDetailsException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MeAction implements RequestHandlerInterface
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $userId = (string) $request->getAttribute('user_id', '');

        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/unauthorized',
                title: 'User Not Found',
                status: 401,
                detail: 'The authenticated user could not be found.',
                instance: $request->getUri()->getPath()
            );
        }

        $payload = [
            'data' => $user->toArray(),
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
