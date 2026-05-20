<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthenticationException;
use App\Exceptions\AuthorizationException;

final class TenantMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        // Ensure user is authenticated (should be set by AuthMiddleware)
        $user = $request->getUser();

        if (!$user) {
            throw AuthenticationException::tokenInvalid('User context not set');
        }

        // Extract tenant ID from user
        $tenantId = $user['tenant_id'] ?? null;

        // Set tenant context on request
        $request->setTenantId($tenantId);

        // Set additional context for audit logging and debugging
        $request->setContext('tenant_id', $tenantId);

        if ($tenantId === null) {
            // Superadmin operation - no tenant isolation
            $request->setContext('is_superadmin_operation', true);
            $request->setContext('tenant_type', 'platform');
        } else {
            // Tenant-scoped operation
            $request->setContext('is_superadmin_operation', false);
            $request->setContext('tenant_type', 'tenant');
            $request->setContext('tenant_subdomain', $user['tenant_subdomain'] ?? null);
            $request->setContext('tenant_name', $user['tenant_name'] ?? null);
        }

        // Set user role context for RBAC
        $request->setContext('user_role', $user['role_name'] ?? null);
        $request->setContext('user_permissions', $user['permissions'] ?? []);

        return $next($request);
    }
}