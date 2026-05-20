<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * Middleware interface for request processing chain
 *
 * Middleware classes implement this interface to participate in the
 * request processing pipeline. Each middleware can modify the request,
 * perform validation/authorization, and either continue the chain or
 * return an early response.
 */
interface MiddlewareInterface
{
    /**
     * Handle the incoming request
     *
     * @param Request $request The incoming request object
     * @param callable $next The next middleware/controller in the chain
     * @return Response The response (either from this middleware or the chain)
     */
    public function handle(Request $request, callable $next): Response;
}