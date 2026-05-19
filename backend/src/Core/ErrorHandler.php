<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;
use App\Exceptions\ValidationException;

final class ErrorHandler
{
    private bool $debug;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Handle any throwable and return appropriate Response
     */
    public function handle(\Throwable $e): Response
    {
        // Log the error
        $this->logError($e);

        // Map exception to HTTP status and response
        $statusCode = $this->mapExceptionToStatusCode($e);
        $errors = $this->formatErrorMessages($e);
        $meta = $this->buildErrorMeta($e);

        return Response::error($errors, $statusCode, $meta);
    }

    /**
     * Map exception types to HTTP status codes
     */
    private function mapExceptionToStatusCode(\Throwable $e): int
    {
        return match (true) {
            $e instanceof ValidationException => 422,
            $e instanceof AuthenticationException => 401,
            $e instanceof AuthorizationException => 403,
            $e instanceof NotFoundException => 404,
            $e instanceof ConflictException => 409,
            $e instanceof RateLimitException => 429,
            $e instanceof \InvalidArgumentException => 400,
            $e instanceof \DomainException => 400,
            $e instanceof \PDOException => 500, // Database errors
            $e instanceof \JsonException => 400, // JSON parsing errors
            default => 500, // Internal server error
        };
    }

    /**
     * Format error messages based on exception type and debug mode
     *
     * @return array<string>
     */
    private function formatErrorMessages(\Throwable $e): array
    {
        // For known exception types, use their message
        if ($this->isKnownException($e)) {
            return [$e->getMessage()];
        }

        // For unknown exceptions, be careful about exposing internals
        if ($this->debug) {
            $messages = [$e->getMessage()];

            // Include previous exception messages in debug mode
            $previous = $e->getPrevious();
            while ($previous !== null) {
                $messages[] = 'Caused by: ' . $previous->getMessage();
                $previous = $previous->getPrevious();
            }

            return $messages;
        }

        // In production, return generic error for unknown exceptions
        return ['An unexpected error occurred'];
    }

    /**
     * Build error metadata
     *
     * @return array<string, mixed>
     */
    private function buildErrorMeta(\Throwable $e): array
    {
        $meta = [
            'error_type' => get_class($e),
        ];

        // Add debug information if in debug mode
        if ($this->debug) {
            $meta['debug'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $this->sanitizeStackTrace($e->getTrace()),
            ];

            // Add previous exception info
            if ($e->getPrevious()) {
                $meta['debug']['previous'] = [
                    'type' => get_class($e->getPrevious()),
                    'message' => $e->getPrevious()->getMessage(),
                    'file' => $e->getPrevious()->getFile(),
                    'line' => $e->getPrevious()->getLine(),
                ];
            }
        }

        // Add specific metadata for certain exception types
        if ($e instanceof ValidationException) {
            $meta['validation_errors'] = $e->getErrors();
        }

        if ($e instanceof RateLimitException) {
            $meta['retry_after'] = $e->getRetryAfter();
        }

        return $meta;
    }

    /**
     * Check if this is a known/expected exception type
     */
    private function isKnownException(\Throwable $e): bool
    {
        return $e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof NotFoundException
            || $e instanceof ConflictException
            || $e instanceof RateLimitException
            || $e instanceof \InvalidArgumentException
            || $e instanceof \DomainException;
    }

    /**
     * Log error with appropriate level based on type
     */
    private function logError(\Throwable $e): void
    {
        $context = [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ];

        // Determine log level based on exception type
        if ($e instanceof ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof NotFoundException) {
            // These are expected errors - log as info/warning
            error_log("CLIENT_ERROR: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
        } else {
            // Unexpected errors - log as error with full context
            error_log("SERVER_ERROR: {$e->getMessage()} in {$e->getFile()}:{$e->getLine()}\n{$e->getTraceAsString()}");
        }
    }

    /**
     * Sanitize stack trace for safe output
     *
     * @param array<array<string, mixed>> $trace
     * @return array<array<string, mixed>>
     */
    private function sanitizeStackTrace(array $trace): array
    {
        return array_map(function (array $frame): array {
            // Remove args to avoid exposing sensitive data
            unset($frame['args']);

            // Keep only essential information
            return array_filter([
                'file' => $frame['file'] ?? null,
                'line' => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class' => $frame['class'] ?? null,
            ]);
        }, array_slice($trace, 0, 10)); // Limit to first 10 frames
    }

    /**
     * Set up global error and exception handlers
     */
    public static function register(bool $debug = false): self
    {
        $handler = new self($debug);

        // Set up exception handler
        set_exception_handler([$handler, 'handleUncaught']);

        // Set up error handler for PHP errors
        set_error_handler([$handler, 'handlePhpError'], E_ALL);

        // Handle fatal errors
        register_shutdown_function([$handler, 'handleFatalError']);

        return $handler;
    }

    /**
     * Handle uncaught exceptions
     */
    public function handleUncaught(\Throwable $e): void
    {
        $response = $this->handle($e);
        $response->send();
        exit(1);
    }

    /**
     * Handle PHP errors (convert to exceptions)
     */
    public function handlePhpError(int $level, string $message, string $file = '', int $line = 0): bool
    {
        // Don't handle errors that are suppressed with @
        if (!(error_reporting() & $level)) {
            return false;
        }

        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    /**
     * Handle fatal errors
     */
    public function handleFatalError(): void
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $exception = new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            $response = $this->handle($exception);
            $response->send();
        }
    }
}