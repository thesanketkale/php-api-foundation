<?php

declare(strict_types=1);

namespace App\Domain\User;

use DateTimeImmutable;
use JsonSerializable;

final class User implements JsonSerializable
{
    private string $id;
    private string $email;
    private string $passwordHash;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $email,
        string $passwordHash,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->email = strtolower(trim($email));
        $this->passwordHash = $passwordHash;
        $this->createdAt = $createdAt ?? new DateTimeImmutable('now');
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable('now');
    }

    public static function create(string $id, string $email, string $plainPassword): self
    {
        $hash = password_hash($plainPassword, PASSWORD_ARGON2ID);
        $now = new DateTimeImmutable('now');

        return new self($id, $email, $hash, $now, $now);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    public function updatePassword(string $newPlainPassword): void
    {
        $this->passwordHash = password_hash($newPlainPassword, PASSWORD_ARGON2ID);
        $this->updatedAt = new DateTimeImmutable('now');
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return array{id: string, email: string, created_at: string, updated_at: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * @return array{id: string, email: string, created_at: string, updated_at: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
