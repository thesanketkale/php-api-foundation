<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class PdoUserRepository implements UserRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password_hash, created_at, updated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        /** @var array{id: string, email: string, password_hash: string, created_at: string, updated_at: string} $row */
        return new User(
            $row['id'],
            $row['email'],
            $row['password_hash'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->pdo->prepare('SELECT id, email, password_hash, created_at, updated_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        /** @var array{id: string, email: string, password_hash: string, created_at: string, updated_at: string} $row */
        return new User(
            $row['id'],
            $row['email'],
            $row['password_hash'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }

    public function save(User $user): void
    {
        $existing = $this->findById($user->getId());

        if ($existing === null) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (id, email, password_hash, created_at, updated_at) VALUES (:id, :email, :password_hash, :created_at, :updated_at)'
            );
            $stmt->execute([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'password_hash' => $user->getPasswordHash(),
                'created_at' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'UPDATE users SET email = :email, password_hash = :password_hash, updated_at = :updated_at WHERE id = :id'
            );
            $stmt->execute([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'password_hash' => $user->getPasswordHash(),
                'updated_at' => $user->getUpdatedAt()->format('Y-m-d H:i:s'),
            ]);
        }
    }

    public function existsByEmail(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $stmt->execute(['email' => strtolower(trim($email))]);

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
