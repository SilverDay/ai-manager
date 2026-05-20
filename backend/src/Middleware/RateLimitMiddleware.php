<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\RateLimitException;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private Config $config;
    private array $rateLimits;
    private static array $requestCounts = [];

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
        $this->loadRateLimits();
    }

    public function handle(Request $request, callable $next): Response
    {
        $category = $this->determineRateCategory($request);
        $identifier = $this->getRequestIdentifier($request);
        $limit = $this->rateLimits[$category] ?? $this->rateLimits['default'];

        // Check if request is within rate limit
        $isWithinLimit = $this->checkRateLimit($identifier, $category, $limit);

        if (!$isWithinLimit) {
            $retryAfter = $this->getRetryAfter($identifier, $category);

            throw RateLimitException::tooManyRequests([
                'category' => $category,
                'limit' => $limit,
                'retry_after' => $retryAfter
            ]);
        }

        // Record the request
        $this->recordRequest($identifier, $category);

        // Process the request
        $response = $next($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders($response, $identifier, $category, $limit);
    }

    /**
     * Load rate limit configuration
     */
    private function loadRateLimits(): void
    {
        $this->rateLimits = [
            'auth' => $this->config->get('rate_limit.auth', 10),        // 10 requests per minute
            'api_read' => $this->config->get('rate_limit.api_read', 120), // 120 requests per minute
            'api_write' => $this->config->get('rate_limit.api_write', 60), // 60 requests per minute
            'upload' => $this->config->get('rate_limit.upload', 10),    // 10 requests per minute
            'default' => $this->config->get('rate_limit.default', 60)   // 60 requests per minute
        ];
    }

    /**
     * Determine rate limit category based on request
     */
    private function determineRateCategory(Request $request): string
    {
        $path = $request->getPath();
        $method = $request->getMethod();

        // Authentication endpoints
        if (str_starts_with($path, '/api/v1/auth/') || str_starts_with($path, '/api/auth/')) {
            return 'auth';
        }

        // File upload endpoints
        if (str_contains($path, '/upload') || str_contains($path, '/documents')) {
            return 'upload';
        }

        // Determine read vs write based on HTTP method
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return 'api_read';
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return 'api_write';
        }

        return 'default';
    }

    /**
     * Get unique identifier for request (IP + User ID if available)
     */
    private function getRequestIdentifier(Request $request): string
    {
        $ip = $request->getClientIp();
        $user = $request->getUser();

        // If user is authenticated, use IP + user ID for more granular control
        if ($user && isset($user['id'])) {
            return "user_{$user['id']}_{$ip}";
        }

        // For unauthenticated requests, use IP only
        return "ip_{$ip}";
    }

    /**
     * Check if request is within rate limit
     */
    private function checkRateLimit(string $identifier, string $category, int $limit): bool
    {
        $window = 60; // 1 minute window
        $currentTime = time();
        $windowStart = $currentTime - $window;

        $key = "{$identifier}:{$category}";

        // Initialize if not exists
        if (!isset(self::$requestCounts[$key])) {
            self::$requestCounts[$key] = [];
        }

        // Clean old entries outside the time window
        self::$requestCounts[$key] = array_filter(
            self::$requestCounts[$key],
            fn($timestamp) => $timestamp > $windowStart
        );

        // Check if adding this request would exceed the limit
        return count(self::$requestCounts[$key]) < $limit;
    }

    /**
     * Record a request
     */
    private function recordRequest(string $identifier, string $category): void
    {
        $key = "{$identifier}:{$category}";

        if (!isset(self::$requestCounts[$key])) {
            self::$requestCounts[$key] = [];
        }

        self::$requestCounts[$key][] = time();
    }

    /**
     * Get retry after seconds
     */
    private function getRetryAfter(string $identifier, string $category): int
    {
        $key = "{$identifier}:{$category}";

        if (!isset(self::$requestCounts[$key]) || empty(self::$requestCounts[$key])) {
            return 60;
        }

        // Find the oldest request in the current window
        $oldestRequest = min(self::$requestCounts[$key]);
        $retryAfter = $oldestRequest + 60 - time();

        return max(1, $retryAfter); // At least 1 second
    }

    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders(Response $response, string $identifier, string $category, int $limit): Response
    {
        $key = "{$identifier}:{$category}";
        $currentCount = count(self::$requestCounts[$key] ?? []);
        $remaining = max(0, $limit - $currentCount);

        return $response
            ->withHeader('X-Rate-Limit-Category', $category)
            ->withHeader('X-Rate-Limit-Limit', (string) $limit)
            ->withHeader('X-Rate-Limit-Remaining', (string) $remaining)
            ->withHeader('X-Rate-Limit-Reset', (string) (time() + 60));
    }

    /**
     * Clean up old entries (called periodically)
     */
    public static function cleanup(): void
    {
        $cutoff = time() - 300; // Keep 5 minutes of history

        foreach (self::$requestCounts as $key => &$timestamps) {
            $timestamps = array_filter($timestamps, fn($timestamp) => $timestamp > $cutoff);

            // Remove empty arrays
            if (empty($timestamps)) {
                unset(self::$requestCounts[$key]);
            }
        }
    }

    /**
     * Get current rate limit statistics for monitoring
     */
    public static function getStats(): array
    {
        $stats = [];
        $currentTime = time();

        foreach (self::$requestCounts as $key => $timestamps) {
            [$identifier, $category] = explode(':', $key, 2);

            if (!isset($stats[$category])) {
                $stats[$category] = [
                    'total_identifiers' => 0,
                    'total_requests' => 0,
                    'requests_last_minute' => 0
                ];
            }

            $stats[$category]['total_identifiers']++;
            $stats[$category]['total_requests'] += count($timestamps);

            // Count requests in the last minute
            $lastMinuteCount = count(array_filter(
                $timestamps,
                fn($timestamp) => $timestamp > ($currentTime - 60)
            ));

            $stats[$category]['requests_last_minute'] += $lastMinuteCount;
        }

        return $stats;
    }
}