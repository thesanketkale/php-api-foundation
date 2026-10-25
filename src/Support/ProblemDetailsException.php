<?php

declare(strict_types=1);

namespace App\Support;

use Exception;
use Throwable;

class ProblemDetailsException extends Exception
{
    private string $type;
    private string $title;
    private int $status;
    private string $detail;
    private ?string $instance;
    /**
     * @var array<string, mixed>
     */
    private array $extensions;

    /**
     * @param string $type
     * @param string $title
     * @param int $status
     * @param string $detail
     * @param string|null $instance
     * @param array<string, mixed> $extensions
     * @param Throwable|null $previous
     */
    public function __construct(
        string $type,
        string $title,
        int $status,
        string $detail,
        ?string $instance = null,
        array $extensions = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($detail, $status, $previous);
        $this->type = $type;
        $this->title = $title;
        $this->status = $status;
        $this->detail = $detail;
        $this->instance = $instance;
        $this->extensions = $extensions;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getInstance(): ?string
    {
        return $this->instance;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'type' => $this->type,
            'title' => $this->title,
            'status' => $this->status,
            'detail' => $this->detail,
        ];

        if ($this->instance !== null) {
            $payload['instance'] = $this->instance;
        }

        foreach ($this->extensions as $key => $val) {
            $payload[$key] = $val;
        }

        return $payload;
    }
}
