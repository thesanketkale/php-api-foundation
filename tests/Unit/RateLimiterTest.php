<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Infrastructure\RateLimiter\FileRateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private string $tempDir;
    private FileRateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/test_ratelimit_' . bin2hex(random_bytes(6));
        $this->limiter = new FileRateLimiter($this->tempDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function testRateLimiterAllowsUnderLimit(): void
    {
        $key = 'test-ip-1';
        $res1 = $this->limiter->check($key, 3, 60);

        $this->assertTrue($res1['allowed']);
        $this->assertSame(2, $res1['remaining']);

        $res2 = $this->limiter->check($key, 3, 60);
        $this->assertTrue($res2['allowed']);
        $this->assertSame(1, $res2['remaining']);

        $res3 = $this->limiter->check($key, 3, 60);
        $this->assertTrue($res3['allowed']);
        $this->assertSame(0, $res3['remaining']);

        // 4th request exceeds limit
        $res4 = $this->limiter->check($key, 3, 60);
        $this->assertFalse($res4['allowed']);
        $this->assertSame(0, $res4['remaining']);
    }
}
