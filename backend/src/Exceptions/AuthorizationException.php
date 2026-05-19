<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AuthorizationException extends \Exception
{
    public function __construct(string $message = 'Access denied')
    {
        parent::__construct($message);
    }

    public static function insufficientRole(string $requiredRole): self
    {
        return new self("Access denied. Required role: {$requiredRole}");
    }

    public static function tenantMismatch(): self
    {
        return new self('Access denied. Resource belongs to different tenant');
    }

    public static function resourceOwnershipRequired(): self
    {
        return new self('Access denied. You can only access your own resources');
    }
}