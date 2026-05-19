<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ValidationException extends \Exception
{
    /** @var array<string, array<string>> */
    private array $errors;

    /**
     * @param array<string, array<string>> $errors
     */
    public function __construct(array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
        $this->errors = $errors;
    }

    /**
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Create validation exception from field errors
     *
     * @param array<string, array<string>> $fieldErrors
     */
    public static function fromFieldErrors(array $fieldErrors): self
    {
        $messages = [];
        foreach ($fieldErrors as $field => $errors) {
            foreach ($errors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }

        $message = 'Validation failed: ' . implode(', ', $messages);
        return new self($fieldErrors, $message);
    }

    /**
     * Create validation exception for single field
     *
     * @param string $field
     * @param array<string> $errors
     */
    public static function forField(string $field, array $errors): self
    {
        return new self([$field => $errors]);
    }
}