<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    public function testGetWithDefault(): void
    {
        $config = new Config(['foo' => 'bar']);

        $this->assertSame('bar', $config->get('foo'));
        $this->assertSame('baz', $config->get('missing', 'baz'));
        $this->assertNull($config->get('unknown'));
    }

    public function testDotNotation(): void
    {
        $config = new Config([
            'database' => [
                'connections' => [
                    'mysql' => [
                        'host' => '127.0.0.1',
                    ],
                ],
            ],
        ]);

        $this->assertSame('127.0.0.1', $config->get('database.connections.mysql.host'));
        $this->assertNull($config->get('database.connections.postgres.host'));
    }

    public function testStrictTypeGetters(): void
    {
        $config = new Config([
            'str' => 'hello',
            'int' => 42,
            'int_str' => '100',
            'bool_true' => 'true',
            'bool_false' => 'false',
            'arr' => ['a', 'b'],
        ]);

        $this->assertSame('hello', $config->getString('str'));
        $this->assertSame(42, $config->getInt('int'));
        $this->assertSame(100, $config->getInt('int_str'));
        $this->assertTrue($config->getBool('bool_true'));
        $this->assertFalse($config->getBool('bool_false'));
        $this->assertSame(['a', 'b'], $config->getArray('arr'));
    }

    public function testValidateRequiredThrowsExceptionOnMissingKey(): void
    {
        $config = new Config(['key1' => 'val1', 'empty' => '   ']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required configuration keys: empty, missing_key');

        $config->validateRequired(['key1', 'empty', 'missing_key']);
    }
}
