<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Domain\Auth\TokenFamilyService;
use App\Domain\User\UserRepositoryInterface;
use App\Support\ProblemDetailsException;
use App\Support\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class LoginAction implements RequestHandlerInterface
{
    private UserRepositoryInterface $userRepository;
    private TokenFamilyService $tokenFamilyService;

    public function __construct(UserRepositoryInterface $userRepository, TokenFamilyService $tokenFamilyService)
    {
        $this->userRepository = $userRepository;
        $this->tokenFamilyService = $tokenFamilyService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);

        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $errors = [];
        if ($email === '') {
            $errors['email'] = ['Email is required.'];
        }
        if ($password === '') {
            $errors['password'] = ['Password is required.'];
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors, $request->getUri()->getPath());
        }

        $user = $this->userRepository->findByEmail($email);
        if ($user === null || !$user->verifyPassword($password)) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/unauthorized',
                title: 'Invalid Credentials',
                status: 401,
                detail: 'The provided email or password does not match our records.',
                instance: $request->getUri()->getPath()
            );
        }

        $tokens = $this->tokenFamilyService->issueTokens($user);

        $payload = [
            'data' => $tokens,
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
