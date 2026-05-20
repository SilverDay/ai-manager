<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;

final class CorsMiddleware implements MiddlewareInterface
{
    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
    }

    public function handle(Request $request, callable $next): Response
    {
        $origin = $request->getHeader('Origin');
        $allowedOrigins = $this->config->get('cors.allowed_origins', ['*']);

        // Check if origin is allowed
        $isOriginAllowed = in_array('*', $allowedOrigins, true) ||
                          in_array($origin, $allowedOrigins, true);

        // Handle preflight requests (OPTIONS)
        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($request, $isOriginAllowed);
        }

        // Process the actual request
        $response = $next($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($response, $origin, $isOriginAllowed);
    }

    /**
     * Handle OPTIONS preflight requests
     */
    private function handlePreflightRequest(Request $request, bool $isOriginAllowed): Response
    {
        if (!$isOriginAllowed) {
            return Response::error(['CORS origin not allowed'], 403);
        }

        $requestedMethod = $request->getHeader('Access-Control-Request-Method');
        $requestedHeaders = $request->getHeader('Access-Control-Request-Headers');

        $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        $allowedHeaders = [
            'Content-Type',
            'Authorization',
            'X-Requested-With',
            'X-Monitor-Key',
            'Accept',
            'Origin'
        ];

        // Check if requested method is allowed
        if ($requestedMethod && !in_array($requestedMethod, $allowedMethods, true)) {
            return Response::error(['HTTP method not allowed for CORS'], 405);
        }

        // Prepare response with CORS headers
        $response = Response::success(null, ['preflight' => true]);

        // Add CORS headers
        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $request->getHeader('Origin'))
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $allowedHeaders))
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '86400') // 24 hours
            ->withHeader('Vary', 'Origin');

        return $response;
    }

    /**
     * Add CORS headers to actual responses
     */
    private function addCorsHeaders(Response $response, string $origin, bool $isOriginAllowed): Response
    {
        if (!$isOriginAllowed) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin ?: '*')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Expose-Headers', 'X-Request-ID, X-Rate-Limit-Remaining')
            ->withHeader('Vary', 'Origin');
    }
}