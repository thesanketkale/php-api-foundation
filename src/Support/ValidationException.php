<?php

declare(strict_types=1);

namespace App\Support;

final class ValidationException extends ProblemDetailsException
{
    /**
     * @param array<string, list<string>> $errors
     * @param string|null $instance
     */
    public function __construct(array $errors, ?string $instance = null)
    {
        parent::__construct(
            type: 'https://api.example.com/errors/validation',
            title: 'Unprocessable Content',
            status: 422,
            detail: 'One or more request parameters failed validation.',
            instance: $instance,
            extensions: ['invalid_params' => $errors]
        );
    }
}
