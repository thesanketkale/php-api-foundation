<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Support\ProblemDetailsException;
use App\Support\ValidationException;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsTest extends TestCase
{
    public function testProblemDetailsExceptionSerialization(): void
    {
        $ex = new ProblemDetailsException(
            type: 'https://api.example.com/errors/not-found',
            title: 'Not Found',
            status: 404,
            detail: 'The resource does not exist.',
            instance: '/v1/notes/123',
            extensions: ['custom_key' => 'custom_val']
        );

        $array = $ex->toArray();

        $this->assertSame('https://api.example.com/errors/not-found', $array['type']);
        $this->assertSame('Not Found', $array['title']);
        $this->assertSame(404, $array['status']);
        $this->assertSame('The resource does not exist.', $array['detail']);
        $this->assertSame('/v1/notes/123', $array['instance']);
        $this->assertSame('custom_val', $array['custom_key']);
    }

    public function testValidationExceptionPayload(): void
    {
        $errors = [
            'email' => ['Invalid email format.'],
            'password' => ['Too short.'],
        ];

        $ex = new ValidationException($errors, '/v1/auth/register');
        $array = $ex->toArray();

        $this->assertSame(422, $array['status']);
        $this->assertSame('Unprocessable Content', $array['title']);
        $this->assertSame($errors, $array['invalid_params']);
    }
}
