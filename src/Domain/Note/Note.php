<?php

declare(strict_types=1);

namespace App\Domain\Note;

use DateTimeImmutable;
use JsonSerializable;

final class Note implements JsonSerializable
{
    private string $id;
    private string $userId;
    private string $title;
    private string $content;
    private int $version;
    private string $etag;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $userId,
        string $title,
        string $content,
        int $version,
        string $etag,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->title = $title;
        $this->content = $content;
        $this->version = $version;
        $this->etag = $etag;
        $this->createdAt = $createdAt ?? new DateTimeImmutable('now');
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable('now');
    }

    public static function create(string $id, string $userId, string $title, string $content): self
    {
        $now = new DateTimeImmutable('now');
        $version = 1;
        $etag = self::computeEtag($version, $title, $content);

        return new self($id, $userId, $title, $content, $version, $etag, $now, $now);
    }

    public static function computeEtag(int $version, string $title, string $content): string
    {
        $hash = sha1(sprintf('%d:%s:%s', $version, $title, $content));
        return sprintf('W/"%d-%s"', $version, $hash);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getEtag(): string
    {
        return $this->etag;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function update(string $title, string $content): void
    {
        $this->title = $title;
        $this->content = $content;
        $this->version++;
        $this->etag = self::computeEtag($this->version, $this->title, $this->content);
        $this->updatedAt = new DateTimeImmutable('now');
    }

    public function matchesEtag(string $candidateEtag): bool
    {
        $normalizedCandidate = trim($candidateEtag);
        // Match either weak etag W/"..." or exact hash
        return $this->etag === $normalizedCandidate ||
               str_replace('W/', '', $this->etag) === str_replace('W/', '', $normalizedCandidate);
    }

    /**
     * @return array{id: string, user_id: string, title: string, content: string, version: int, etag: string, created_at: string, updated_at: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'title' => $this->title,
            'content' => $this->content,
            'version' => $this->version,
            'etag' => $this->etag,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt->format(DateTimeImmutable::ATOM),
        ];
    }

    /**
     * @return array{id: string, user_id: string, title: string, content: string, version: int, etag: string, created_at: string, updated_at: string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
