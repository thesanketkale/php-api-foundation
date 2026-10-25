<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\TestCase;

final class HealthTest extends TestCase
{
    public function testLivenessProbeReturnsPass(): void
    {
        $request = $this->createRequest('GET', '/v1/health');
        $response = $this->handleRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('pass', $body['status']);
        $this->assertArrayHasKey('timestamp', $body);
    }

    public function testReadinessProbeReturnsConnectedDatabase(): void
    {
        $request = $this->createRequest('GET', '/v1/health/ready');
        $response = $this->handleRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('pass', $body['status']);
        $this->assertSame('connected', $body['database']);
    }
}
