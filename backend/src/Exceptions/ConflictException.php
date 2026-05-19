<?php

declare(strict_types=1);

namespace App\Exceptions;

final class ConflictException extends \Exception
{
    public function __construct(string $message = 'Resource conflict')
    {
        parent::__construct($message);
    }

    public static function duplicateResource(string $resource, string $field, string $value): self
    {
        return new self("{$resource} with {$field} '{$value}' already exists");
    }

    public static function resourceInUse(string $resource, int|string $id): self
    {
        return new self("{$resource} {$id} cannot be deleted because it is in use");
    }

    public static function concurrentModification(): self
    {
        return new self('Resource was modified by another user. Please refresh and try again');
    }
}