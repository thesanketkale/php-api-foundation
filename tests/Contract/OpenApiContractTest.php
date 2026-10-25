<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use App\Tests\TestCase;
use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;

final class OpenApiContractTest extends TestCase
{
    private string $openApiPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openApiPath = dirname(__DIR__, 2) . '/openapi/openapi.yaml';
    }

    public function testHealthEndpointMatchesOpenApiContract(): void
    {
        $this->assertFileExists($this->openApiPath);

        $validator = (new ValidatorBuilder())
            ->fromYamlFile($this->openApiPath)
            ->getResponseValidator();

        $request = $this->createRequest('GET', '/v1/health');
        $response = $this->handleRequest($request);

        $operation = new OperationAddress('/health', 'get');
        $validator->validate($operation, $response);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHealthReadyEndpointMatchesOpenApiContract(): void
    {
        $validator = (new ValidatorBuilder())
            ->fromYamlFile($this->openApiPath)
            ->getResponseValidator();

        $request = $this->createRequest('GET', '/v1/health/ready');
        $response = $this->handleRequest($request);

        $operation = new OperationAddress('/health/ready', 'get');
        $validator->validate($operation, $response);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRegisterEndpointMatchesOpenApiContract(): void
    {
        $validator = (new ValidatorBuilder())
            ->fromYamlFile($this->openApiPath)
            ->getResponseValidator();

        $request = $this->createRequest('POST', '/v1/auth/register', [], [
            'email' => 'contract-user@example.com',
            'password' => 'ContractPassword123!',
        ]);
        $response = $this->handleRequest($request);

        $operation = new OperationAddress('/auth/register', 'post');
        $validator->validate($operation, $response);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testLoginEndpointMatchesOpenApiContract(): void
    {
        $this->createUser('contract-login@example.com', 'SecurePassword123!');

        $validator = (new ValidatorBuilder())
            ->fromYamlFile($this->openApiPath)
            ->getResponseValidator();

        $request = $this->createRequest('POST', '/v1/auth/login', [], [
            'email' => 'contract-login@example.com',
            'password' => 'SecurePassword123!',
        ]);
        $response = $this->handleRequest($request);

        $operation = new OperationAddress('/auth/login', 'post');
        $validator->validate($operation, $response);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testNotesEndpointMatchesOpenApiContract(): void
    {
        $user = $this->createUser('contract-notes@example.com');
        $jwt = $this->generateAccessToken($user);

        $validator = (new ValidatorBuilder())
            ->fromYamlFile($this->openApiPath)
            ->getResponseValidator();

        // 1. Create Note
        $createReq = $this->createRequest('POST', '/v1/notes', [
            'Authorization' => 'Bearer ' . $jwt,
        ], [
            'title' => 'Contract Note',
            'content' => 'Validated against OpenAPI 3.1 specification.',
        ]);
        $createRes = $this->handleRequest($createReq);
        $validator->validate(new OperationAddress('/notes', 'post'), $createRes);
        $this->assertSame(201, $createRes->getStatusCode());

        // 2. List Notes
        $listReq = $this->createRequest('GET', '/v1/notes', [
            'Authorization' => 'Bearer ' . $jwt,
        ]);
        $listRes = $this->handleRequest($listReq);
        $validator->validate(new OperationAddress('/notes', 'get'), $listRes);
        $this->assertSame(200, $listRes->getStatusCode());
    }
}
