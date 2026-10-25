<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeImmutable;

final class RefreshToken
{
    private string $id;
    private string $userId;
    private string $tokenFamily;
    private string $tokenHash;
    private int $sequence;
    private bool $isRevoked;
    private DateTimeImmutable $expiresAt;
    private DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        string $userId,
        string $tokenFamily,
        string $tokenHash,
        int $sequence,
        bool $isRevoked,
        DateTimeImmutable $expiresAt,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->tokenFamily = $tokenFamily;
        $this->tokenHash = $tokenHash;
        $this->sequence = $sequence;
        $this->isRevoked = $isRevoked;
        $this->expiresAt = $expiresAt;
        $this->createdAt = $createdAt ?? new DateTimeImmutable('now');
    }

    public static function create(
        string $id,
        string $userId,
        string $tokenFamily,
        string $plainToken,
        int $sequence,
        int $ttlSeconds
    ): self {
        $hash = hash('sha256', $plainToken);
        $expiresAt = (new DateTimeImmutable('now'))->modify(sprintf('+%d seconds', $ttlSeconds));

        return new self(
            $id,
            $userId,
            $tokenFamily,
            $hash,
            $sequence,
            false,
            $expiresAt,
            new DateTimeImmutable('now')
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTokenFamily(): string
    {
        return $this->tokenFamily;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getSequence(): int
    {
        return $this->sequence;
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function revoke(): void
    {
        $this->isRevoked = true;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new DateTimeImmutable('now');
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
