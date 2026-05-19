<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<string, array<string, RouteDefinition>> */
    private array $routes = [];

    /** @var array<callable> */
    private array $middleware = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function patch(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Add global middleware that runs for all routes
     */
    public function addGlobalMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param array<string> $middleware
     */
    private function addRoute(string $method, string $path, array $handler, array $middleware = []): void
    {
        // Convert route pattern to regex
        $pattern = $this->convertPathToRegex($path);

        $this->routes[$method][$path] = new RouteDefinition(
            $path,
            $pattern,
            $handler[0],
            $handler[1],
            $middleware
        );
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        // Find matching route
        $matchedRoute = $this->findMatchingRoute($method, $path);

        if (!$matchedRoute) {
            return Response::error(
                ['message' => 'Not found'],
                404
            );
        }

        [$route, $parameters] = $matchedRoute;

        // Add route parameters to request
        $request->setRouteParameters($parameters);

        // Build middleware chain
        $middlewareChain = $this->buildMiddlewareChain($route);

        // Execute middleware chain
        return $this->executeMiddlewareChain($middlewareChain, $request, $route);
    }

    /**
     * Convert route path with parameters to regex pattern
     */
    private function convertPathToRegex(string $path): string
    {
        // Escape special regex characters except our parameter placeholders
        $pattern = preg_quote($path, '#');

        // Convert {param} to named capture groups
        $pattern = preg_replace('#\\\{([^}]+)\\\}#', '(?P<$1>[^/]+)', $pattern);

        return '#^' . $pattern . '$#';
    }

    /**
     * Find route that matches the request path
     *
     * @return array{RouteDefinition, array<string, string>}|null
     */
    private function findMatchingRoute(string $method, string $path): ?array
    {
        if (!isset($this->routes[$method])) {
            return null;
        }

        foreach ($this->routes[$method] as $route) {
            if (preg_match($route->getPattern(), $path, $matches)) {
                // Extract named parameters
                $parameters = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return [$route, $parameters];
            }
        }

        return null;
    }

    /**
     * Build the middleware chain for a route
     *
     * @return array<callable>
     */
    private function buildMiddlewareChain(RouteDefinition $route): array
    {
        $middlewareChain = [];

        // Add global middleware first
        foreach ($this->middleware as $middleware) {
            $middlewareChain[] = $middleware;
        }

        // Add route-specific middleware
        foreach ($route->getMiddleware() as $middlewareName) {
            $middlewareChain[] = $this->resolveMiddleware($middlewareName);
        }

        return $middlewareChain;
    }

    /**
     * Execute the middleware chain and controller
     */
    private function executeMiddlewareChain(array $middlewareChain, Request $request, RouteDefinition $route): Response
    {
        // If no middleware, execute controller directly
        if (empty($middlewareChain)) {
            return $this->executeController($request, $route);
        }

        // Create a chain of callables
        $next = fn(Request $req) => $this->executeController($req, $route);

        // Build chain from right to left (reverse order)
        for ($i = count($middlewareChain) - 1; $i >= 0; $i--) {
            $middleware = $middlewareChain[$i];
            $currentNext = $next;
            $next = fn(Request $req) => $middleware($req, $currentNext);
        }

        return $next($request);
    }

    /**
     * Execute the final controller action
     */
    private function executeController(Request $request, RouteDefinition $route): Response
    {
        $controllerClass = $route->getControllerClass();
        $controllerMethod = $route->getControllerMethod();

        // Instantiate controller and call method
        $controller = new $controllerClass();
        $response = $controller->{$controllerMethod}($request);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Controller must return Response instance');
        }

        return $response;
    }

    /**
     * Resolve middleware by name to callable
     */
    private function resolveMiddleware(string $middlewareName): callable
    {
        // This will be enhanced when middleware classes are added
        // For now, just return a no-op middleware
        return function (Request $request, callable $next) {
            return $next($request);
        };
    }
}

/**
 * Route definition value object
 */
final class RouteDefinition
{
    private string $path;
    private string $pattern;
    private string $controllerClass;
    private string $controllerMethod;
    /** @var array<string> */
    private array $middleware;

    /**
     * @param array<string> $middleware
     */
    public function __construct(
        string $path,
        string $pattern,
        string $controllerClass,
        string $controllerMethod,
        array $middleware = []
    ) {
        $this->path = $path;
        $this->pattern = $pattern;
        $this->controllerClass = $controllerClass;
        $this->controllerMethod = $controllerMethod;
        $this->middleware = $middleware;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getControllerClass(): string
    {
        return $this->controllerClass;
    }

    public function getControllerMethod(): string
    {
        return $this->controllerMethod;
    }

    /**
     * @return array<string>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }
}