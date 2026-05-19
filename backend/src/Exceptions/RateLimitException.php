<?php

declare(strict_types=1);

namespace App\Exceptions;

final class RateLimitException extends \Exception
{
    private int $retryAfter;

    public function __construct(string $message = 'Rate limit exceeded', int $retryAfter = 60)
    {
        parent::__construct($message);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public static function tooManyRequests(int $retryAfter = 60): self
    {
        return new self("Too many requests. Please try again in {$retryAfter} seconds", $retryAfter);
    }

    public static function authAttemptsExceeded(int $retryAfter = 900): self
    {
        return new self("Too many authentication attempts. Please try again in {$retryAfter} seconds", $retryAfter);
    }
}