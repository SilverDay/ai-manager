<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    private string $method;
    private string $path;
    private array $query;
    private array $headers;
    private ?string $body;
    private array $parsedBody;
    private array $routeParameters;
    private mixed $user = null;
    private ?int $tenantId = null;
    private array $context;

    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     * @param array<string, mixed> $parsedBody
     * @param array<string, mixed> $routeParameters
     */
    public function __construct(
        string $method,
        string $path,
        array $query = [],
        array $headers = [],
        ?string $body = null,
        array $parsedBody = [],
        array $routeParameters = []
    ) {
        $this->method = strtoupper($method);
        $this->path = $path;
        $this->query = $query;
        $this->headers = $headers;
        $this->body = $body;
        $this->parsedBody = $parsedBody;
        $this->routeParameters = $routeParameters;
        $this->context = [];
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $query = $_GET;

        // Extract headers
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $header = str_replace('_', '-', substr($key, 5));
                $headers[strtolower($header)] = $value;
            }
        }

        // Get raw body
        $body = file_get_contents('php://input') ?: null;

        // Parse JSON body with enhanced safety
        $parsedBody = [];
        if ($body !== null && $body !== '') {
            $contentType = $headers['content-type'] ?? '';
            if (str_contains($contentType, 'application/json')) {
                try {
                    $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $parsedBody = $decoded;
                    } else {
                        // Handle case where JSON is valid but not an object/array
                        $parsedBody = ['_raw' => $decoded];
                    }
                } catch (\JsonException $e) {
                    // Invalid JSON - store error info for debugging
                    $parsedBody = [
                        '_json_error' => true,
                        '_json_error_message' => $e->getMessage(),
                        '_raw_body' => $body,
                    ];
                }
            } elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
                // Parse form data
                parse_str($body, $parsedBody);
            }
        }

        return new self($method, $path, $query, $headers, $body, $parsedBody);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, mixed>
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    public function getBodyParam(string $key, mixed $default = null): mixed
    {
        return $this->parsedBody[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function getRouteParameter(string $key, mixed $default = null): mixed
    {
        return $this->routeParameters[$key] ?? $default;
    }

    /**
     * Set route parameters (called by router during dispatch)
     *
     * @param array<string, mixed> $parameters
     */
    public function setRouteParameters(array $parameters): void
    {
        $this->routeParameters = $parameters;
    }

    // ===== User Context Management =====

    /**
     * Get authenticated user (set by auth middleware)
     */
    public function getUser(): mixed
    {
        return $this->user;
    }

    /**
     * Set authenticated user (called by auth middleware)
     */
    public function setUser(mixed $user): void
    {
        $this->user = $user;
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    // ===== Tenant Context Management =====

    /**
     * Get current tenant ID (set by tenant middleware)
     */
    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    /**
     * Set current tenant ID (called by tenant middleware)
     */
    public function setTenantId(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    /**
     * Check if request has tenant context
     */
    public function hasTenantContext(): bool
    {
        return $this->tenantId !== null;
    }

    // ===== General Context Management =====

    /**
     * Set arbitrary context value
     */
    public function setContext(string $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    /**
     * Get context value
     */
    public function getContext(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Get all context data
     *
     * @return array<string, mixed>
     */
    public function getAllContext(): array
    {
        return $this->context;
    }

    /**
     * Check if request has valid JSON body
     */
    public function hasValidJsonBody(): bool
    {
        return !isset($this->parsedBody['_json_error']);
    }

    /**
     * Get JSON parsing error if any
     */
    public function getJsonError(): ?string
    {
        return $this->parsedBody['_json_error_message'] ?? null;
    }

    /**
     * Safe parameter access with type validation
     */
    public function getBodyParamInt(string $key, ?int $default = null): ?int
    {
        $value = $this->getBodyParam($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Safe parameter access with type validation
     */
    public function getBodyParamString(string $key, ?string $default = null): ?string
    {
        $value = $this->getBodyParam($key, $default);
        return is_string($value) || is_numeric($value) ? (string) $value : $default;
    }

    /**
     * Safe parameter access with type validation
     */
    public function getBodyParamBool(string $key, ?bool $default = null): ?bool
    {
        $value = $this->getBodyParam($key, $default);
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            return in_array(strtolower($value), ['true', '1', 'yes', 'on']);
        }
        return $default;
    }

    /**
     * Safe query parameter access with type validation
     */
    public function getQueryParamInt(string $key, ?int $default = null): ?int
    {
        $value = $this->getQueryParam($key, $default);
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * Safe query parameter access with type validation
     */
    public function getQueryParamString(string $key, ?string $default = null): ?string
    {
        $value = $this->getQueryParam($key, $default);
        return is_string($value) || is_numeric($value) ? (string) $value : $default;
    }
}