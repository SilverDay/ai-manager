<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\HealthController;
use App\Controllers\MonitorController;
use App\Controllers\AuthController;
use App\Controllers\UserController;
use App\Controllers\TenantController;

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
        $this->router->setDependencies($this->config, $this->database);
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
        // Health check endpoint (basic)
        $this->router->get('/health', [MonitorController::class, 'health']);

        // API v1 routes will be added in subsequent work packages
        $this->router->get('/api/v1/health', [HealthController::class, 'check']);

        // Production monitoring endpoints
        $this->router->get('/status', [MonitorController::class, 'status']);
        $this->router->get('/metrics', [MonitorController::class, 'metrics']);
        $this->router->get('/security-status', [MonitorController::class, 'securityStatus']);

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

        // Authentication endpoints (API v1)
        $this->registerAuthRoutes();
    }

    /**
     * Register authentication and user management routes
     */
    private function registerAuthRoutes(): void
    {
        // Authentication endpoints - public access with rate limiting
        $this->router->post('/api/v1/auth/login', [AuthController::class, 'login'], ['cors', 'rate-limit']);
        $this->router->post('/api/v1/auth/refresh', [AuthController::class, 'refresh'], ['cors']);
        $this->router->post('/api/v1/auth/forgot-password', [AuthController::class, 'forgotPassword'], ['cors', 'rate-limit']);
        $this->router->post('/api/v1/auth/reset-password', [AuthController::class, 'resetPassword'], ['cors', 'rate-limit']);

        // Authenticated endpoints - require valid JWT
        $this->router->post('/api/v1/auth/logout', [AuthController::class, 'logout'], ['cors', 'auth', 'tenant']);
        $this->router->post('/api/v1/auth/mfa-verify', [AuthController::class, 'mfaVerify'], ['cors', 'rate-limit']);

        // User management endpoints
        $this->registerUserManagementRoutes();
    }

    /**
     * Register user management and tenant domain routes
     */
    private function registerUserManagementRoutes(): void
    {
        // Public registration endpoints (rate limited)
        $this->router->post('/api/v1/users/register', [UserController::class, 'register'], ['cors', 'rate-limit']);
        $this->router->post('/api/v1/users/register/invite/{token}', [UserController::class, 'registerInvite'], ['cors', 'rate-limit']);

        // User administration endpoints (Admin only)
        $this->router->get('/api/v1/users/pending', [UserController::class, 'pending'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->patch('/api/v1/users/{id}/approve', [UserController::class, 'approve'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->patch('/api/v1/users/{id}/reject', [UserController::class, 'reject'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->get('/api/v1/users/stats', [UserController::class, 'getStats'], ['cors', 'auth', 'tenant', 'rbac:admin']);

        // Invitation management endpoints (Admin only)
        $this->router->post('/api/v1/invitations', [UserController::class, 'createInvitation'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->get('/api/v1/invitations', [UserController::class, 'listInvitations'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->post('/api/v1/invitations/{id}/resend', [UserController::class, 'resendInvitation'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->delete('/api/v1/invitations/{id}', [UserController::class, 'revokeInvitation'], ['cors', 'auth', 'tenant', 'rbac:admin']);

        // Tenant domain management endpoints (Admin only)
        $this->router->get('/api/v1/domains', [TenantController::class, 'listDomains'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->post('/api/v1/domains', [TenantController::class, 'createDomain'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->put('/api/v1/domains/{id}', [TenantController::class, 'updateDomain'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->delete('/api/v1/domains/{id}', [TenantController::class, 'deleteDomain'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->post('/api/v1/domains/{id}/verify', [TenantController::class, 'verifyDomain'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->post('/api/v1/domains/{id}/verification-token', [TenantController::class, 'generateVerificationToken'], ['cors', 'auth', 'tenant', 'rbac:admin']);
        $this->router->get('/api/v1/domains/stats', [TenantController::class, 'getDomainStats'], ['cors', 'auth', 'tenant', 'rbac:admin']);
    }
}