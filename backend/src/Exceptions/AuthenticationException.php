<?php

declare(strict_types=1);

namespace App\Exceptions;

final class AuthenticationException extends \Exception
{
    public function __construct(string $message = 'Authentication required')
    {
        parent::__construct($message);
    }

    public static function invalidCredentials(): self
    {
        return new self('Invalid email or password');
    }

    public static function tokenExpired(): self
    {
        return new self('Authentication token has expired');
    }

    public static function tokenInvalid(): self
    {
        return new self('Invalid authentication token');
    }

    public static function accountDeactivated(): self
    {
        return new self('Account has been deactivated');
    }

    public static function accountPendingApproval(): self
    {
        return new self('Account is pending approval');
    }
}