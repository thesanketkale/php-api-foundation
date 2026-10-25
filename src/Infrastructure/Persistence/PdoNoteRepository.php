<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Note\Note;
use App\Domain\Note\NoteRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class PdoNoteRepository implements NoteRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findById(string $id, string $userId): ?Note
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, title, content, version, etag, created_at, updated_at
             FROM notes WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        /** @var array{id: string, user_id: string, title: string, content: string, version: int|string, etag: string, created_at: string, updated_at: string} $row */
        return new Note(
            $row['id'],
            $row['user_id'],
            $row['title'],
            $row['content'],
            (int) $row['version'],
            $row['etag'],
            new DateTimeImmutable($row['created_at']),
            new DateTimeImmutable($row['updated_at'])
        );
    }

    /**
     * @return list<Note>
     */
    public function findByFilter(
        string $userId,
        ?string $search = null,
        ?string $sort = 'created_at',
        string $order = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $allowedSorts = ['created_at', 'updated_at', 'title'];
        $sortColumn = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $sortOrder = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

        $sql = 'SELECT id, user_id, title, content, version, etag, created_at, updated_at FROM notes WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (title LIKE :search OR content LIKE :search)';
            $params['search'] = '%' . trim($search) . '%';
        }

        $sql .= sprintf(' ORDER BY %s %s LIMIT %d OFFSET %d', $sortColumn, $sortOrder, $limit, $offset);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $notes = [];
        foreach ($rows as $row) {
            /** @var array{id: string, user_id: string, title: string, content: string, version: int|string, etag: string, created_at: string, updated_at: string} $row */
            $notes[] = new Note(
                $row['id'],
                $row['user_id'],
                $row['title'],
                $row['content'],
                (int) $row['version'],
                $row['etag'],
                new DateTimeImmutable($row['created_at']),
                new DateTimeImmutable($row['updated_at'])
            );
        }

        return $notes;
    }

    public function countByFilter(string $userId, ?string $search = null): int
    {
        $sql = 'SELECT COUNT(*) FROM notes WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (title LIKE :search OR content LIKE :search)';
            $params['search'] = '%' . trim($search) . '%';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function save(Note $note): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notes (id, user_id, title, content, version, etag, created_at, updated_at)
             VALUES (:id, :user_id, :title, :content, :version, :etag, :created_at, :updated_at)'
        );

        $stmt->execute([
            'id' => $note->getId(),
            'user_id' => $note->getUserId(),
            'title' => $note->getTitle(),
            'content' => $note->getContent(),
            'version' => $note->getVersion(),
            'etag' => $note->getEtag(),
            'created_at' => $note->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $note->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateWithOptimisticLock(Note $note, int $expectedVersion): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notes
             SET title = :title, content = :content, version = :version, etag = :etag, updated_at = :updated_at
             WHERE id = :id AND user_id = :user_id AND version = :expected_version'
        );

        $stmt->execute([
            'title' => $note->getTitle(),
            'content' => $note->getContent(),
            'version' => $note->getVersion(),
            'etag' => $note->getEtag(),
            'updated_at' => $note->getUpdatedAt()->format('Y-m-d H:i:s'),
            'id' => $note->getId(),
            'user_id' => $note->getUserId(),
            'expected_version' => $expectedVersion,
        ]);

        return $stmt->rowCount() === 1;
    }

    public function delete(string $id, string $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM notes WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $id, 'user_id' => $userId]);

        return $stmt->rowCount() > 0;
    }
}
