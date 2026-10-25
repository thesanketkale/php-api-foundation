<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use App\Infrastructure\Token\JwtService;
use App\Support\Config;
use App\Support\ProblemDetailsException;
use PDO;

final class TokenFamilyService
{
    private RefreshTokenRepositoryInterface $refreshTokenRepository;
    private UserRepositoryInterface $userRepository;
    private JwtService $jwtService;
    private PDO $pdo;
    private int $refreshTtl;

    public function __construct(
        RefreshTokenRepositoryInterface $refreshTokenRepository,
        UserRepositoryInterface $userRepository,
        JwtService $jwtService,
        PDO $pdo,
        Config $config
    ) {
        $this->refreshTokenRepository = $refreshTokenRepository;
        $this->userRepository = $userRepository;
        $this->jwtService = $jwtService;
        $this->pdo = $pdo;
        $this->refreshTtl = $config->getInt('REFRESH_TOKEN_TTL', 2592000); // 30 days
    }

    /**
     * @param User $user
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issueTokens(User $user): array
    {
        $accessToken = $this->jwtService->createAccessToken($user->getId(), [
            'email' => $user->getEmail(),
        ]);

        $tokenFamily = bin2hex(random_bytes(16));
        $plainRefreshToken = bin2hex(random_bytes(32));
        $tokenId = bin2hex(random_bytes(16));

        $refreshToken = RefreshToken::create(
            id: $tokenId,
            userId: $user->getId(),
            tokenFamily: $tokenFamily,
            plainToken: $plainRefreshToken,
            sequence: 1,
            ttlSeconds: $this->refreshTtl
        );

        $this->refreshTokenRepository->save($refreshToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => $plainRefreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtService->getAccessTtl(),
        ];
    }

    /**
     * @param string $plainRefreshToken
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     * @throws ProblemDetailsException
     */
    public function rotateTokens(string $plainRefreshToken): array
    {
        $hash = hash('sha256', $plainRefreshToken);

        $this->pdo->beginTransaction();

        try {
            $token = $this->refreshTokenRepository->findByTokenHash($hash);

            if ($token === null) {
                $this->pdo->rollBack();
                throw new ProblemDetailsException(
                    type: 'https://api.example.com/errors/unauthorized',
                    title: 'Invalid Refresh Token',
                    status: 401,
                    detail: 'The provided refresh token is unknown or invalid.'
                );
            }

            // CRITICAL SECURITY: TOKEN FAMILY REVOCATION UPON REUSE
            if ($token->isRevoked() || $token->isExpired()) {
                // Suspicious activity: A revoked or expired token was presented again.
                // Revoke the ENTIRE token family immediately to protect the compromised user!
                $this->refreshTokenRepository->revokeFamily($token->getTokenFamily());
                $this->pdo->commit();

                throw new ProblemDetailsException(
                    type: 'https://api.example.com/errors/unauthorized',
                    title: 'Token Compromised',
                    status: 401,
                    detail: 'Refresh token reuse detected. All tokens in this session family have been revoked.',
                    extensions: ['code' => 'TOKEN_REUSE_REVOKED']
                );
            }

            // Revoke current token (mark as consumed)
            $stmt = $this->pdo->prepare('UPDATE refresh_tokens SET is_revoked = 1 WHERE id = :id');
            $stmt->execute(['id' => $token->getId()]);

            // Retrieve user
            $user = $this->userRepository->findById($token->getUserId());
            if ($user === null) {
                $this->pdo->rollBack();
                throw new ProblemDetailsException(
                    type: 'https://api.example.com/errors/unauthorized',
                    title: 'User Not Found',
                    status: 401,
                    detail: 'The user associated with this token no longer exists.'
                );
            }

            // Issue new generation in the same family
            $newPlainRefreshToken = bin2hex(random_bytes(32));
            $newTokenId = bin2hex(random_bytes(16));

            $newToken = RefreshToken::create(
                id: $newTokenId,
                userId: $user->getId(),
                tokenFamily: $token->getTokenFamily(),
                plainToken: $newPlainRefreshToken,
                sequence: $token->getSequence() + 1,
                ttlSeconds: $this->refreshTtl
            );

            $this->refreshTokenRepository->save($newToken);

            $newAccessToken = $this->jwtService->createAccessToken($user->getId(), [
                'email' => $user->getEmail(),
            ]);

            $this->pdo->commit();

            return [
                'access_token' => $newAccessToken,
                'refresh_token' => $newPlainRefreshToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getAccessTtl(),
            ];
        } catch (ProblemDetailsException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new ProblemDetailsException(
                type: 'https://api.example.com/errors/server-error',
                title: 'Token Rotation Failed',
                status: 500,
                detail: 'An error occurred while rotating authentication tokens.',
                previous: $e
            );
        }
    }

    public function revokeToken(string $plainRefreshToken): void
    {
        $hash = hash('sha256', $plainRefreshToken);
        $token = $this->refreshTokenRepository->findByTokenHash($hash);

        if ($token !== null) {
            $this->refreshTokenRepository->revokeFamily($token->getTokenFamily());
        }
    }
}
