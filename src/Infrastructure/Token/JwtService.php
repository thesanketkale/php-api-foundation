<?php

declare(strict_types=1);

namespace App\Infrastructure\Token;

use App\Support\Config;
use App\Support\ProblemDetailsException;
use DateTimeImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtService
{
    private string $secret;
    private string $algorithm;
    private string $issuer;
    private int $accessTtl;

    public function __construct(Config $config)
    {
        $this->secret = $config->getString('JWT_SECRET');
        $this->algorithm = $config->getString('JWT_ALGORITHM', 'HS256');
        $this->issuer = $config->getString('JWT_ISSUER', 'php-api-foundation');
        $this->accessTtl = $config->getInt('JWT_ACCESS_TTL', 900); // 15 mins
    }

    /**
     * @param string $userId
     * @param array<string, mixed> $customClaims
     * @return string
     */
    public function createAccessToken(string $userId, array $customClaims = []): string
    {
        $now = (new DateTimeImmutable('now'))->getTimestamp();
        $payload = array_merge([
            'iss' => $this->issuer,
            'sub' => $userId,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
            'jti' => bin2hex(random_bytes(16)),
        ], $customClaims);

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * @param string $token
     * @return array<string, mixed>
     * @throws ProblemDetailsException
     */
    public function verifyAccessToken(string $token): array
    {
        try {
            $key = new Key($this->secret, $this->algorithm);
            $decoded = JWT::decode($token, $key);

            /** @var array<string, mixed> $array */
            $array = (array) $decoded;

            if (($array['iss'] ?? '') !== $this->issuer) {
                throw new ProblemDetailsException(
                    type: 'https://api.example.com/errors/unauthorized',
                    title: 'Unauthorized',
                    status: 401,
                    detail: 'Token issuer is invalid.'
                );
            }

            return $array;
        } catch (ProblemDetailsException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/unauthorized',
                title: 'Unauthorized',
                status: 401,
                detail: 'Access token is invalid or expired: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }
}
