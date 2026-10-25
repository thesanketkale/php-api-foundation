<?php

declare(strict_types=1);

namespace App\Http\Action\Auth;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Support\ProblemDetailsException;
use App\Support\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RegisterAction implements RequestHandlerInterface
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var array<string, mixed> $body */
        $body = (array) ($request->getParsedBody() ?? []);

        $email = trim((string) ($body['email'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = ['A valid email address is required.'];
        }

        if (strlen($password) < 8) {
            $errors['password'] = ['Password must be at least 8 characters long.'];
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors, $request->getUri()->getPath());
        }

        if ($this->userRepository->existsByEmail($email)) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/conflict',
                title: 'Conflict',
                status: 409,
                detail: sprintf('The email address "%s" is already registered.', $email),
                instance: $request->getUri()->getPath()
            );
        }

        // Generate UUIDv4 for user
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        $userId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $user = User::create($userId, $email, $password);
        $this->userRepository->save($user);

        $payload = [
            'data' => $user->toArray(),
            'meta' => [
                'timestamp' => time(),
            ],
        ];

        return new Response(
            201,
            ['Content-Type' => 'application/json; charset=utf-8'],
            (string) json_encode($payload, JSON_UNESCAPED_SLASHES)
        );
    }
}
