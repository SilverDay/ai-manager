<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    private array $data;
    private int $statusCode;
    private array $headers;

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function __construct(array $data, int $statusCode = 200, array $headers = [])
    {
        $this->data = $data;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    /**
     * Create a success response following the standard envelope
     *
     * @param mixed $data
     * @param array<string, mixed> $meta
     */
    public static function success(mixed $data = null, array $meta = []): self
    {
        $envelope = self::buildEnvelope(true, $data, [], $meta);
        return new self($envelope, 200);
    }

    /**
     * Create an error response following the standard envelope
     *
     * @param array<string>|string $errors
     * @param array<string, mixed> $meta
     */
    public static function error(array|string $errors, int $statusCode = 400, array $meta = []): self
    {
        // Normalize errors to array format
        $errorArray = is_string($errors) ? [$errors] : $errors;

        $envelope = self::buildEnvelope(false, null, $errorArray, $meta);
        return new self($envelope, $statusCode);
    }

    /**
     * Create a validation error response (422)
     *
     * @param array<string, array<string>> $fieldErrors
     * @param array<string, mixed> $meta
     */
    public static function validationError(array $fieldErrors, array $meta = []): self
    {
        $errors = [];
        foreach ($fieldErrors as $field => $fieldErrorList) {
            foreach ($fieldErrorList as $error) {
                $errors[] = "{$field}: {$error}";
            }
        }

        $meta['validation_errors'] = $fieldErrors;

        return self::error($errors, 422, $meta);
    }

    /**
     * Create a not found response (404)
     */
    public static function notFound(string $message = 'Resource not found', array $meta = []): self
    {
        return self::error([$message], 404, $meta);
    }

    /**
     * Create an unauthorized response (401)
     */
    public static function unauthorized(string $message = 'Authentication required', array $meta = []): self
    {
        return self::error([$message], 401, $meta);
    }

    /**
     * Create a forbidden response (403)
     */
    public static function forbidden(string $message = 'Access denied', array $meta = []): self
    {
        return self::error([$message], 403, $meta);
    }

    /**
     * Create a rate limited response (429)
     */
    public static function rateLimited(string $message = 'Rate limit exceeded', array $meta = []): self
    {
        return self::error([$message], 429, $meta);
    }

    /**
     * Create an internal server error response (500)
     */
    public static function serverError(string $message = 'Internal server error', array $meta = []): self
    {
        return self::error([$message], 500, $meta);
    }

    /**
     * Create a paginated success response
     *
     * @param array<mixed> $items
     * @param array{page: int, per_page: int, total: int, total_pages: int} $pagination
     * @param array<string, mixed> $meta
     */
    public static function paginated(array $items, array $pagination, array $meta = []): self
    {
        $meta['pagination'] = $pagination;

        return self::success($items, $meta);
    }

    /**
     * Create a created response (201)
     *
     * @param mixed $data
     * @param array<string, mixed> $meta
     */
    public static function created(mixed $data, array $meta = []): self
    {
        $envelope = self::buildEnvelope(true, $data, [], $meta);
        return new self($envelope, 201);
    }

    /**
     * Create a no content response (204)
     */
    public static function noContent(): self
    {
        $envelope = self::buildEnvelope(true, null, [], []);
        return new self($envelope, 204);
    }

    /**
     * Build the standard API envelope structure
     *
     * @param array<string> $errors
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private static function buildEnvelope(bool $success, mixed $data, array $errors, array $meta): array
    {
        // Add standard meta fields
        if (!isset($meta['timestamp'])) {
            $meta['timestamp'] = date('c');
        }

        if (!isset($meta['request_id'])) {
            $meta['request_id'] = uniqid('req_', true);
        }

        return [
            'success' => $success,
            'data' => $data,
            'errors' => $errors,
            'meta' => $meta,
        ];
    }

    /**
     * Validate that the response follows the envelope contract
     */
    public function validateEnvelope(): bool
    {
        $required = ['success', 'data', 'errors', 'meta'];

        foreach ($required as $field) {
            if (!array_key_exists($field, $this->data)) {
                return false;
            }
        }

        // Validate field types
        if (!is_bool($this->data['success'])) {
            return false;
        }

        if (!is_array($this->data['errors'])) {
            return false;
        }

        if (!is_array($this->data['meta'])) {
            return false;
        }

        // Success responses should have empty errors array
        if ($this->data['success'] === true && !empty($this->data['errors'])) {
            return false;
        }

        // Error responses should have null data and non-empty errors
        if ($this->data['success'] === false) {
            if ($this->data['data'] !== null) {
                return false;
            }
            if (empty($this->data['errors'])) {
                return false;
            }
        }

        return true;
    }

    public function send(): void
    {
        // Validate envelope structure before sending
        if (!$this->validateEnvelope()) {
            error_log('Warning: Response envelope validation failed');
        }

        // Set status code
        http_response_code($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Ensure JSON content type
        if (!isset($this->headers['Content-Type'])) {
            header('Content-Type: application/json; charset=utf-8');
        }

        // Add security headers if not already set
        if (!isset($this->headers['X-Content-Type-Options'])) {
            header('X-Content-Type-Options: nosniff');
        }

        // Output JSON with proper encoding options
        $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

        // Add pretty print for development
        if (($_ENV['APP_ENV'] ?? 'production') === 'development') {
            $jsonOptions |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($this->data, $jsonOptions);

        if ($json === false) {
            // JSON encoding failed - create emergency error response
            error_log('JSON encoding failed: ' . json_last_error_msg());

            $emergency = [
                'success' => false,
                'data' => null,
                'errors' => ['Failed to encode response as JSON'],
                'meta' => [
                    'timestamp' => date('c'),
                    'request_id' => uniqid('emergency_', true),
                ],
            ];

            echo json_encode($emergency);
        } else {
            echo $json;
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Create a new response with an additional header (immutable)
     */
    public function withHeader(string $name, string $value): self
    {
        $newHeaders = $this->headers;
        $newHeaders[$name] = $value;

        return new self($this->data, $this->statusCode, $newHeaders);
    }

    /**
     * Create a new response with multiple headers (immutable)
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): self
    {
        $newHeaders = array_merge($this->headers, $headers);

        return new self($this->data, $this->statusCode, $newHeaders);
    }
}