<?php

declare(strict_types=1);

namespace App\Support;

use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Validator as RespectValidator;

final class Validator
{
    /**
     * @var array<string, list<string>>
     */
    private array $errors = [];

    /**
     * @param array<string, mixed> $data
     * @param array<string, RespectValidator> $rules
     * @return array<string, mixed> The validated data
     * @throws ValidationException
     */
    public function validate(array $data, array $rules, ?string $instance = null): array
    {
        $this->errors = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;

            try {
                $rule->assert($value);
            } catch (NestedValidationException $e) {
                /** @var array<string, string> $messages */
                $messages = $e->getMessages();
                $fieldErrors = [];
                foreach ($messages as $msg) {
                    if ($msg !== '') {
                        $fieldErrors[] = $msg;
                    }
                }
                $this->errors[$field] = count($fieldErrors) > 0 ? $fieldErrors : [$e->getMessage()];
            }
        }

        if (count($this->errors) > 0) {
            throw new ValidationException($this->errors, $instance);
        }

        return $data;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
