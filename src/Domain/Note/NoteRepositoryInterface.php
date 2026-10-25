<?php

declare(strict_types=1);

namespace App\Domain\Note;

interface NoteRepositoryInterface
{
    public function findById(string $id, string $userId): ?Note;

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
    ): array;

    public function countByFilter(string $userId, ?string $search = null): int;

    public function save(Note $note): void;

    public function updateWithOptimisticLock(Note $note, int $expectedVersion): bool;

    public function delete(string $id, string $userId): bool;
}
