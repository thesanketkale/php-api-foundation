<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Config
{
    /**
     * @var array<string, mixed>
     */
    private array $items;

    /**
     * @param array<string, mixed> $items
     */
    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        $segments = explode('.', $key);
        $current = $this->items;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            /** @var mixed $current */
            $current = $current[$segment];
        }

        return $current;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function getString(string $key, ?string $default = null): string
    {
        $value = $this->get($key, $default);
        if (!is_string($value)) {
            if ($default !== null) {
                return $default;
            }
            throw new RuntimeException(sprintf('Configuration key "%s" must be a string.', $key));
        }

        return $value;
    }

    public function getInt(string $key, ?int $default = null): int
    {
        $value = $this->get($key, $default);
        if (is_numeric($value)) {
            return (int) $value;
        }

        if ($default !== null) {
            return $default;
        }

        throw new RuntimeException(sprintf('Configuration key "%s" must be an integer.', $key));
    }

    public function getBool(string $key, ?bool $default = null): bool
    {
        $value = $this->get($key, $default);
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($filtered !== null) {
                return $filtered;
            }
        }

        if ($default !== null) {
            return $default;
        }

        throw new RuntimeException(sprintf('Configuration key "%s" must be a boolean.', $key));
    }

    /**
     * @param string $key
     * @param array<mixed>|null $default
     * @return array<mixed>
     */
    public function getArray(string $key, ?array $default = null): array
    {
        $value = $this->get($key, $default);
        if (is_array($value)) {
            return $value;
        }

        if ($default !== null) {
            return $default;
        }

        throw new RuntimeException(sprintf('Configuration key "%s" must be an array.', $key));
    }

    /**
     * @param list<string> $requiredKeys
     * @throws RuntimeException
     */
    public function validateRequired(array $requiredKeys): void
    {
        $missing = [];
        foreach ($requiredKeys as $key) {
            $val = $this->get($key);
            if ($val === null || (is_string($val) && trim($val) === '')) {
                $missing[] = $key;
            }
        }

        if (count($missing) > 0) {
            throw new RuntimeException(sprintf(
                'Missing required configuration keys: %s. Please check your .env configuration.',
                implode(', ', $missing)
            ));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->items;
    }
}
