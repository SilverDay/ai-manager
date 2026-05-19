<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\HealthController;

final class Application
{
    private Router $router;
    private ErrorHandler $errorHandler;
    private Config $config;
    private Database $database;

    public function __construct(bool $debug = false)
    {
        $this->config = Config::getInstance();
        $this->database = new Database($this->config);
        $this->router = new Router();
        $this->errorHandler = new ErrorHandler($debug);
        $this->registerRoutes();
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (\Throwable $e) {
            return $this->errorHandler->handle($e);
        }
    }

    private function registerRoutes(): void
    {
        // Health check endpoint
        $this->router->get('/health', [HealthController::class, 'check']);

        // API v1 routes will be added in subsequent work packages
        $this->router->get('/api/v1/health', [HealthController::class, 'check']);

        // Test route with parameters (for router testing)
        $this->router->get('/api/v1/test/{id}', [HealthController::class, 'test']);

        // Test route for request abstraction (JSON parsing, context)
        $this->router->post('/api/v1/request-test', [HealthController::class, 'requestTest']);

        // Test route for response envelope helpers
        $this->router->get('/api/v1/response-test', [HealthController::class, 'responseTest']);

        // Test route for error handler (throws different exceptions)
        $this->router->get('/api/v1/error-test', [HealthController::class, 'errorTest']);

        // Diagnostic route for config and database testing
        $this->router->get('/api/v1/diagnostics', [HealthController::class, 'diagnostics']);
    }
}