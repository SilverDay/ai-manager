<?php

declare(strict_types=1);

namespace App\Exceptions;

final class NotFoundException extends \Exception
{
    public function __construct(string $message = 'Resource not found')
    {
        parent::__construct($message);
    }

    public static function forResource(string $resource, int|string $id): self
    {
        return new self("{$resource} with ID {$id} not found");
    }

    public static function forRoute(string $path): self
    {
        return new self("Route not found: {$path}");
    }
}