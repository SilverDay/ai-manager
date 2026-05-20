<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Exceptions\AuthorizationException;
use App\Exceptions\AuthenticationException;

final class RbacMiddleware implements MiddlewareInterface
{
    private string $requiredRole;

    public function __construct(string $requiredRole)
    {
        $this->requiredRole = $requiredRole;
    }

    public function handle(Request $request, callable $next): Response
    {
        // Ensure user is authenticated and tenant context is set
        $user = $request->getUser();

        if (!$user) {
            throw AuthenticationException::tokenInvalid('User context not set');
        }

        $userRole = $user['role_name'] ?? null;
        $userPermissions = $user['permissions'] ?? [];
        $tenantId = $request->getTenantId();

        if (!$userRole) {
            throw AuthorizationException::insufficientRole($this->requiredRole);
        }

        // Check role-based access
        if (!$this->hasAccess($userRole, $userPermissions, $tenantId)) {
            throw AuthorizationException::insufficientRole($this->requiredRole);
        }

        // Set RBAC context for audit logging
        $request->setContext('rbac_check_passed', true);
        $request->setContext('required_role', $this->requiredRole);
        $request->setContext('user_role_verified', $userRole);

        return $next($request);
    }

    /**
     * Check if user has the required access based on role and permissions
     */
    private function hasAccess(string $userRole, array $userPermissions, ?int $tenantId): bool
    {
        // Superadmin has access to everything
        if ($userRole === 'Superadmin') {
            return true;
        }

        // Handle role-based requirements
        switch ($this->requiredRole) {
            case 'superadmin':
                return $userRole === 'Superadmin';

            case 'admin':
                return $this->hasRole($userRole, ['Superadmin', 'Admin']);

            case 'ai_owner':
                return $this->hasRole($userRole, ['Superadmin', 'Admin', 'AI Owner']);

            case 'compliance':
                return $this->hasRole($userRole, ['Superadmin', 'Admin', 'Compliance Officer']);

            case 'dpo':
                return $this->hasRole($userRole, ['Superadmin', 'Admin', 'Data Protection Officer']);

            case 'auditor':
                return $this->hasRole($userRole, ['Superadmin', 'Admin', 'Auditor', 'Compliance Officer']);

            case 'technical':
                return $this->hasRole($userRole, ['Superadmin', 'Admin', 'Technical Staff']);

            case 'any':
                // Any authenticated user with active tenant (if tenant-scoped)
                return true;

            default:
                // Check specific permissions
                return $this->hasPermission($userPermissions, $this->requiredRole);
        }
    }

    /**
     * Check if user has one of the specified roles
     */
    private function hasRole(string $userRole, array $allowedRoles): bool
    {
        return in_array($userRole, $allowedRoles, true);
    }

    /**
     * Check if user has a specific permission
     */
    private function hasPermission(array $userPermissions, string $requiredPermission): bool
    {
        // Direct permission match
        if (in_array($requiredPermission, $userPermissions, true)) {
            return true;
        }

        // Wildcard permission match (e.g., "users.*" allows "users.create", "users.read", etc.)
        foreach ($userPermissions as $permission) {
            if (str_ends_with($permission, '.*')) {
                $prefix = substr($permission, 0, -2);
                if (str_starts_with($requiredPermission, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Create middleware instance for specific role requirement
     */
    public static function requireRole(string $role): self
    {
        return new self($role);
    }

    /**
     * Create middleware instance for specific permission requirement
     */
    public static function requirePermission(string $permission): self
    {
        return new self($permission);
    }

    /**
     * Get role hierarchy for reference
     */
    public static function getRoleHierarchy(): array
    {
        return [
            'Superadmin' => [
                'level' => 100,
                'inherits' => ['*']
            ],
            'Admin' => [
                'level' => 90,
                'inherits' => ['tenant.*']
            ],
            'AI Owner' => [
                'level' => 80,
                'inherits' => ['ai_systems.*', 'risk_assessments.*']
            ],
            'Compliance Officer' => [
                'level' => 80,
                'inherits' => ['compliance.*', 'approvals.*', 'audit_log.read']
            ],
            'Data Protection Officer' => [
                'level' => 75,
                'inherits' => ['privacy.*', 'data_processing.*', 'audit_log.read']
            ],
            'Auditor' => [
                'level' => 70,
                'inherits' => ['audit_log.read', '*.read']
            ],
            'Technical Staff' => [
                'level' => 60,
                'inherits' => ['ai_systems.read', 'technical.*']
            ]
        ];
    }
}