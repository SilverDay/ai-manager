<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\JwtService;
use App\Models\User;
use App\Exceptions\AuthenticationException;

final class AuthMiddleware implements MiddlewareInterface
{
    private JwtService $jwtService;
    private User $userModel;

    public function __construct(JwtService $jwtService, User $userModel)
    {
        $this->jwtService = $jwtService;
        $this->userModel = $userModel;
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $this->extractTokenFromRequest($request);

        if (!$token) {
            throw AuthenticationException::tokenInvalid('No authentication token provided');
        }

        // Validate JWT token
        $payload = $this->jwtService->validateToken($token);

        if ($payload === false) {
            throw AuthenticationException::tokenExpired();
        }

        // Verify token type (should be 'access')
        if (($payload['token_type'] ?? 'access') !== 'access') {
            throw AuthenticationException::tokenInvalid('Invalid token type');
        }

        // Get user data from token payload
        $userId = $payload['user_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;

        if (!$userId) {
            throw AuthenticationException::tokenInvalid('Invalid token payload');
        }

        // Verify user still exists and is active
        $user = $this->userModel->findById((int) $userId, $tenantId);

        if (!$user) {
            throw AuthenticationException::accountDeleted();
        }

        // Check account status
        if ($user['account_status'] !== 'active') {
            throw match ($user['account_status']) {
                'pending_approval' => AuthenticationException::accountPendingApproval(),
                'rejected' => AuthenticationException::accountRejected(),
                'deactivated' => AuthenticationException::accountDeactivated(),
                'deleted' => AuthenticationException::accountDeleted(),
                default => AuthenticationException::invalidCredentials()
            };
        }

        // Verify token data matches user data
        if ($user['email'] !== ($payload['email'] ?? '')) {
            throw AuthenticationException::tokenInvalid('Token user mismatch');
        }

        // Check MFA verification for superadmin
        if ($user['tenant_id'] === null && $user['role_name'] === 'Superadmin') {
            if (!($payload['mfa_verified'] ?? false)) {
                throw AuthenticationException::mfaRequired();
            }
        }

        // Attach user data to request
        $request->setUser($user);

        // Set context information for logging/auditing
        $request->setContext('authenticated_user_id', $user['id']);
        $request->setContext('authenticated_at', time());
        $request->setContext('token_issued_at', $payload['iat'] ?? null);

        return $next($request);
    }

    /**
     * Extract Bearer token from Authorization header or query parameter
     */
    private function extractTokenFromRequest(Request $request): ?string
    {
        // Try Authorization header first
        $authHeader = $request->getHeader('Authorization');

        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Fallback to query parameter (for some API integrations)
        $queryToken = $request->getQueryParam('access_token');

        if ($queryToken && is_string($queryToken)) {
            return $queryToken;
        }

        return null;
    }
}