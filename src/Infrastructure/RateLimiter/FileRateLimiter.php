<?php

declare(strict_types=1);

namespace App\Infrastructure\RateLimiter;

final class FileRateLimiter implements RateLimiterInterface
{
    private string $storagePath;

    public function __construct(string $storagePath)
    {
        $this->storagePath = rtrim($storagePath, '/\\');
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * @param string $key
     * @param int $limit
     * @param int $windowSeconds
     * @return array{allowed: bool, limit: int, remaining: int, reset: int}
     */
    public function check(string $key, int $limit, int $windowSeconds): array
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $file = $this->storagePath . '/' . $safeKey . '.lock';

        $fp = fopen($file, 'c+');
        if (!$fp) {
            // Fail open if file cannot be opened
            return [
                'allowed' => true,
                'limit' => $limit,
                'remaining' => $limit,
                'reset' => time() + $windowSeconds,
            ];
        }

        flock($fp, LOCK_EX);

        $now = time();
        $contents = stream_get_contents($fp);
        $data = $contents ? json_decode($contents, true) : null;

        if (!is_array($data) || ($data['reset'] ?? 0) <= $now) {
            $count = 1;
            $reset = $now + $windowSeconds;
        } else {
            $count = ((int) ($data['count'] ?? 0)) + 1;
            $reset = (int) $data['reset'];
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) json_encode(['count' => $count, 'reset' => $reset]));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $allowed = $count <= $limit;
        $remaining = max(0, $limit - $count);

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'remaining' => $remaining,
            'reset' => $reset,
        ];
    }
}
