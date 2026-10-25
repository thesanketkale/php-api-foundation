<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Tests\TestCase;

final class AuthIntegrationTest extends TestCase
{
    public function testRegisterSuccess(): void
    {
        $payload = [
            'email' => 'newuser@example.com',
            'password' => 'SecureP@ss123!',
        ];

        $request = $this->createRequest('POST', '/v1/auth/register', [], $payload);
        $response = $this->handleRequest($request);

        $this->assertSame(201, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('data', $body);
        $this->assertSame('newuser@example.com', $body['data']['email']);
        $this->assertArrayNotHasKey('password_hash', $body['data']);
    }

    public function testRegisterDuplicateEmailReturnsConflict(): void
    {
        $this->createUser('existing@example.com', 'P@ssw0rd123!');

        $request = $this->createRequest('POST', '/v1/auth/register', [], [
            'email' => 'existing@example.com',
            'password' => 'NewPassword123!',
        ]);
        $response = $this->handleRequest($request);

        $this->assertSame(409, $response->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testRegisterInvalidEmailReturnsValidationProblem(): void
    {
        $request = $this->createRequest('POST', '/v1/auth/register', [], [
            'email' => 'not-an-email',
            'password' => 'short',
        ]);
        $response = $this->handleRequest($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Unprocessable Content', $body['title']);
        $this->assertArrayHasKey('invalid_params', $body);
    }

    public function testLoginSuccessAndIssueTokens(): void
    {
        $this->createUser('login@example.com', 'CorrectPassword123!');

        $request = $this->createRequest('POST', '/v1/auth/login', [], [
            'email' => 'login@example.com',
            'password' => 'CorrectPassword123!',
        ]);
        $response = $this->handleRequest($request);

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertArrayHasKey('access_token', $body['data']);
        $this->assertArrayHasKey('refresh_token', $body['data']);
        $this->assertSame('Bearer', $body['data']['token_type']);
        $this->assertSame(900, $body['data']['expires_in']);
    }

    public function testLoginInvalidPasswordReturnsUnauthorized(): void
    {
        $this->createUser('login2@example.com', 'CorrectPassword123!');

        $request = $this->createRequest('POST', '/v1/auth/login', [], [
            'email' => 'login2@example.com',
            'password' => 'WrongPassword!',
        ]);
        $response = $this->handleRequest($request);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testRefreshTokenRotationAndReuseRevocation(): void
    {
        $user = $this->createUser('rotate@example.com', 'P@ssw0rd123!');

        // 1. Initial Login
        $loginRes = $this->handleRequest($this->createRequest('POST', '/v1/auth/login', [], [
            'email' => 'rotate@example.com',
            'password' => 'P@ssw0rd123!',
        ]));
        /** @var array<string, mixed> $loginBody */
        $loginBody = json_decode((string) $loginRes->getBody(), true);
        $token1 = (string) $loginBody['data']['refresh_token'];

        // 2. Successful Rotation (consumes token1, issues token2)
        $refreshRes1 = $this->handleRequest($this->createRequest('POST', '/v1/auth/refresh', [], [
            'refresh_token' => $token1,
        ]));
        $this->assertSame(200, $refreshRes1->getStatusCode());
        /** @var array<string, mixed> $refreshBody1 */
        $refreshBody1 = json_decode((string) $refreshRes1->getBody(), true);
        $token2 = (string) $refreshBody1['data']['refresh_token'];
        $this->assertNotSame($token1, $token2);

        // 3. REUSE ATTACK: Present token1 AGAIN!
        // Should detect reuse, revoke the entire family, and return 401
        $reuseRes = $this->handleRequest($this->createRequest('POST', '/v1/auth/refresh', [], [
            'refresh_token' => $token1,
        ]));
        $this->assertSame(401, $reuseRes->getStatusCode());
        /** @var array<string, mixed> $reuseBody */
        $reuseBody = json_decode((string) $reuseRes->getBody(), true);
        $this->assertSame('TOKEN_REUSE_REVOKED', $reuseBody['code']);

        // 4. Token2 (which was valid before) MUST now also fail because entire family was revoked!
        $token2Res = $this->handleRequest($this->createRequest('POST', '/v1/auth/refresh', [], [
            'refresh_token' => $token2,
        ]));
        $this->assertSame(401, $token2Res->getStatusCode());
    }

    public function testMeEndpointRequiresAuthentication(): void
    {
        $unauthReq = $this->createRequest('GET', '/v1/me');
        $unauthRes = $this->handleRequest($unauthReq);
        $this->assertSame(401, $unauthRes->getStatusCode());

        $user = $this->createUser('profile@example.com');
        $jwt = $this->generateAccessToken($user);

        $authReq = $this->createRequest('GET', '/v1/me', ['Authorization' => 'Bearer ' . $jwt]);
        $authRes = $this->handleRequest($authReq);

        $this->assertSame(200, $authRes->getStatusCode());
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $authRes->getBody(), true);
        $this->assertSame('profile@example.com', $body['data']['email']);
    }

    public function testLogoutRevokesTokenFamily(): void
    {
        $this->createUser('logout@example.com', 'P@ssw0rd123!');
        $loginRes = $this->handleRequest($this->createRequest('POST', '/v1/auth/login', [], [
            'email' => 'logout@example.com',
            'password' => 'P@ssw0rd123!',
        ]));
        /** @var array<string, mixed> $loginBody */
        $loginBody = json_decode((string) $loginRes->getBody(), true);
        $refreshToken = (string) $loginBody['data']['refresh_token'];

        $logoutRes = $this->handleRequest($this->createRequest('POST', '/v1/auth/logout', [], [
            'refresh_token' => $refreshToken,
        ]));
        $this->assertSame(204, $logoutRes->getStatusCode());

        // Subsequent refresh must fail
        $refreshRes = $this->handleRequest($this->createRequest('POST', '/v1/auth/refresh', [], [
            'refresh_token' => $refreshToken,
        ]));
        $this->assertSame(401, $refreshRes->getStatusCode());
    }
}
