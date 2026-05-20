<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\CorsMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\TenantMiddleware;
use App\Middleware\RbacMiddleware;
use App\Services\JwtService;
use App\Services\RegistrationService;
use App\Models\User;
use App\Models\RefreshToken;
use App\Models\TenantDomain;
use App\Models\UserInvitation;

final class Router
{
    /** @var array<string, array<string, RouteDefinition>> */
    private array $routes = [];

    /** @var array<callable> */
    private array $middleware = [];

    private ?Config $config = null;
    private ?Database $database = null;

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
     * Set dependencies needed for middleware instantiation
     */
    public function setDependencies(Config $config, Database $database): void
    {
        $this->config = $config;
        $this->database = $database;
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

        // Handle OPTIONS requests for CORS preflight
        if ($method === 'OPTIONS') {
            $corsRoute = $this->findCorsRouteForPath($path);
            if ($corsRoute) {
                // Create synthetic route with just CORS middleware
                $syntheticRoute = new RouteDefinition(
                    $path,
                    $corsRoute->getPattern(),
                    'CorsController', // Dummy controller
                    'preflight',      // Dummy method
                    ['cors']          // Only CORS middleware
                );
                $middlewareChain = $this->buildMiddlewareChain($syntheticRoute);
                return $this->executeMiddlewareChain($middlewareChain, $request, $syntheticRoute);
            }
        }

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
     * Find a route with CORS middleware for the given path (for OPTIONS handling)
     */
    private function findCorsRouteForPath(string $path): ?RouteDefinition
    {
        // Check all methods for this path
        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $route) {
                if (preg_match($route->getPattern(), $path) && in_array('cors', $route->getMiddleware(), true)) {
                    return $route;
                }
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

        // Instantiate controller with dependency injection
        $controller = $this->instantiateController($controllerClass);
        $response = $controller->{$controllerMethod}($request);

        if (!$response instanceof Response) {
            throw new \RuntimeException('Controller must return Response instance');
        }

        return $response;
    }

    /**
     * Instantiate controller with dependency injection
     */
    private function instantiateController(string $controllerClass): object
    {
        // Simple dependency injection for core services
        $config = $this->config ?? Config::getInstance();
        $database = $this->database ?? new Database($config);

        try {
            $reflector = new \ReflectionClass($controllerClass);
            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                return new $controllerClass();
            }

            $parameters = $constructor->getParameters();
            $dependencies = [];

            foreach ($parameters as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();

                    switch ($typeName) {
                        case Config::class:
                        case 'App\Core\Config':
                            $dependencies[] = $config;
                            break;
                        case Database::class:
                        case 'App\Core\Database':
                            $dependencies[] = $database;
                            break;
                        case JwtService::class:
                        case 'App\Services\JwtService':
                            $dependencies[] = new \App\Services\JwtService($config);
                            break;
                        case User::class:
                        case 'App\Models\User':
                            $dependencies[] = new \App\Models\User($database);
                            break;
                        case RefreshToken::class:
                        case 'App\Models\RefreshToken':
                            $dependencies[] = new \App\Models\RefreshToken($database);
                            break;
                        case RegistrationService::class:
                        case 'App\Services\RegistrationService':
                            $dependencies[] = new \App\Services\RegistrationService(
                                $config,
                                $database,
                                new \App\Models\User($database),
                                new \App\Models\TenantDomain($database),
                                new \App\Models\UserInvitation($database),
                                new \App\Services\JwtService($config)
                            );
                            break;
                        case TenantDomain::class:
                        case 'App\Models\TenantDomain':
                            $dependencies[] = new \App\Models\TenantDomain($database);
                            break;
                        case UserInvitation::class:
                        case 'App\Models\UserInvitation':
                            $dependencies[] = new \App\Models\UserInvitation($database);
                            break;
                        default:
                            if (!$parameter->isOptional()) {
                                throw new \RuntimeException("Cannot resolve dependency: {$typeName}");
                            }
                            break;
                    }
                }
            }

            return $reflector->newInstanceArgs($dependencies);

        } catch (\ReflectionException $e) {
            error_log("Controller instantiation failed: {$controllerClass} - " . $e->getMessage());
            throw new \RuntimeException("Cannot instantiate controller: {$controllerClass}", 0, $e);
        } catch (\Throwable $e) {
            error_log("Controller dependency injection failed: {$controllerClass} - " . $e->getMessage());
            throw new \RuntimeException("Cannot instantiate controller: {$controllerClass}", 0, $e);
        }
    }

    /**
     * Resolve middleware by name to callable
     */
    private function resolveMiddleware(string $middlewareName): callable
    {
        if (!$this->config || !$this->database) {
            throw new \LogicException('Dependencies not set on Router. Call setDependencies() first.');
        }

        // Parse middleware name and parameters
        $parts = explode(':', $middlewareName);
        $name = $parts[0];
        $param = $parts[1] ?? null;

        return match($name) {
            'cors' => function (Request $request, callable $next) {
                $middleware = new CorsMiddleware($this->config);
                return $middleware->handle($request, $next);
            },

            'rate-limit' => function (Request $request, callable $next) {
                $middleware = new RateLimitMiddleware($this->config);
                return $middleware->handle($request, $next);
            },

            'auth' => function (Request $request, callable $next) {
                $jwtService = new JwtService($this->config);
                $userModel = new User($this->database);
                $middleware = new AuthMiddleware($jwtService, $userModel);
                return $middleware->handle($request, $next);
            },

            'tenant' => function (Request $request, callable $next) {
                $middleware = new TenantMiddleware();
                return $middleware->handle($request, $next);
            },

            'rbac' => function (Request $request, callable $next) use ($param) {
                $role = $param ?? 'any';
                $middleware = new RbacMiddleware($role);
                return $middleware->handle($request, $next);
            },

            default => function (Request $request, callable $next) use ($name) {
                // Log unknown middleware for debugging
                error_log("Unknown middleware: {$name}");
                return $next($request);
            }
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