<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Auth\RefreshToken;
use App\Domain\Auth\RefreshTokenRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class PdoRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(RefreshToken $token): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO refresh_tokens (id, user_id, token_family, token_hash, sequence, is_revoked, expires_at, created_at)
             VALUES (:id, :user_id, :token_family, :token_hash, :sequence, :is_revoked, :expires_at, :created_at)'
        );

        $stmt->execute([
            'id' => $token->getId(),
            'user_id' => $token->getUserId(),
            'token_family' => $token->getTokenFamily(),
            'token_hash' => $token->getTokenHash(),
            'sequence' => $token->getSequence(),
            'is_revoked' => $token->isRevoked() ? 1 : 0,
            'expires_at' => $token->getExpiresAt()->format('Y-m-d H:i:s'),
            'created_at' => $token->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByTokenHash(string $hash): ?RefreshToken
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, token_family, token_hash, sequence, is_revoked, expires_at, created_at
             FROM refresh_tokens WHERE token_hash = :hash LIMIT 1'
        );
        $stmt->execute(['hash' => $hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        /** @var array{id: string, user_id: string, token_family: string, token_hash: string, sequence: int|string, is_revoked: int|string|bool, expires_at: string, created_at: string} $row */
        return new RefreshToken(
            $row['id'],
            $row['user_id'],
            $row['token_family'],
            $row['token_hash'],
            (int) $row['sequence'],
            (bool) $row['is_revoked'],
            new DateTimeImmutable($row['expires_at']),
            new DateTimeImmutable($row['created_at'])
        );
    }

    public function revokeFamily(string $familyId): void
    {
        $stmt = $this->pdo->prepare('UPDATE refresh_tokens SET is_revoked = 1 WHERE token_family = :family_id');
        $stmt->execute(['family_id' => $familyId]);
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM refresh_tokens WHERE expires_at < :now OR is_revoked = 1');
        $stmt->execute(['now' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);

        return $stmt->rowCount();
    }
}
