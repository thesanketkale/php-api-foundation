<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Infrastructure\Token\JwtService;
use App\Support\Config;
use App\Support\ProblemDetailsException;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        $config = new Config([
            'JWT_SECRET' => 'unit_test_secret_key_at_least_32_characters_for_hmac!',
            'JWT_ALGORITHM' => 'HS256',
            'JWT_ISSUER' => 'jwt-test-issuer',
            'JWT_ACCESS_TTL' => 60, // 1 min
        ]);

        $this->jwtService = new JwtService($config);
    }

    public function testCreateAndVerifyAccessToken(): void
    {
        $userId = 'user-uuid-12345';
        $token = $this->jwtService->createAccessToken($userId, ['role' => 'admin']);

        $this->assertNotEmpty($token);

        $claims = $this->jwtService->verifyAccessToken($token);

        $this->assertSame($userId, $claims['sub']);
        $this->assertSame('jwt-test-issuer', $claims['iss']);
        $this->assertSame('admin', $claims['role']);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testVerifyInvalidTokenThrowsProblemDetails(): void
    {
        $this->expectException(ProblemDetailsException::class);
        $this->expectExceptionCode(401);

        $this->jwtService->verifyAccessToken('invalid.token.payload');
    }

    public function testVerifyTamperedSignatureThrowsUnauthorized(): void
    {
        $token = $this->jwtService->createAccessToken('user-1');
        $tampered = $token . 'tampered';

        $this->expectException(ProblemDetailsException::class);
        $this->expectExceptionCode(401);

        $this->jwtService->verifyAccessToken($tampered);
    }
}
