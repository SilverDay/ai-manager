<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\JwtService;
use App\Models\User;
use App\Models\RefreshToken;
use App\Exceptions\AuthenticationException;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\RateLimitException;

final class AuthController
{
    private Config $config;
    private Database $database;
    private JwtService $jwtService;
    private User $userModel;
    private RefreshToken $refreshTokenModel;

    public function __construct(
        Config $config,
        Database $database,
        JwtService $jwtService,
        User $userModel,
        RefreshToken $refreshTokenModel
    ) {
        $this->config = $config;
        $this->database = $database;
        $this->jwtService = $jwtService;
        $this->userModel = $userModel;
        $this->refreshTokenModel = $refreshTokenModel;
    }

    /**
     * User login with email and password
     */
    public function login(Request $request): Response
    {
        $email = $request->getBodyParamString('email');
        $password = $request->getBodyParamString('password');

        if (!$email || !$password) {
            throw ValidationException::fromFieldErrors([
                'email' => !$email ? ['Email is required'] : [],
                'password' => !$password ? ['Password is required'] : []
            ]);
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::forField('email', 'Invalid email format');
        }

        // Find and verify user credentials
        $user = $this->userModel->verifyPassword($email, $password);

        if (!$user) {
            throw AuthenticationException::invalidCredentials();
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

        // Check if superadmin and MFA is required
        $requiresMfa = $user['tenant_id'] === null && $user['role_name'] === 'Superadmin';

        if ($requiresMfa && !$this->isMfaVerified($user['id'])) {
            // Generate MFA challenge token
            $challenge = bin2hex(random_bytes(16));
            $mfaToken = $this->jwtService->generateMfaToken($user['id'], $challenge);

            return Response::success([
                'mfa_required' => true,
                'challenge_token' => $mfaToken,
                'challenge' => $challenge
            ], [
                'message' => 'MFA verification required for superadmin access'
            ]);
        }

        // Generate tokens
        $tokenPayload = [
            'user_id' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'role' => $user['role_name'],
            'email' => $user['email'],
            'mfa_verified' => $requiresMfa ? $this->isMfaVerified($user['id']) : true
        ];

        $accessToken = $this->jwtService->generateAccessToken($tokenPayload);
        $refreshTokenPlain = $this->jwtService->generateRefreshToken();
        $refreshTokenHash = RefreshToken::hashToken($refreshTokenPlain);

        // Store refresh token in database
        $expiresAt = new \DateTime();
        $expiresAt->modify('+' . $this->config->get('jwt.refresh_ttl', 604800) . ' seconds');

        $this->refreshTokenModel->create(
            $user['id'],
            $user['tenant_id'],
            $refreshTokenHash,
            $expiresAt,
            $request->getClientIp(),
            $request->getUserAgent()
        );

        // Update last login
        $this->userModel->updateLastLogin($user['id']);

        // Set refresh token as HttpOnly cookie
        $this->setRefreshTokenCookie($refreshTokenPlain);

        // Remove sensitive data from user object
        unset($user['password_hash'], $user['mfa_secret'], $user['email_verification_token']);

        return Response::success([
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => $this->config->get('jwt.access_ttl', 900),
            'user' => $user
        ], [
            'login_time' => time(),
            'expires_at' => time() + $this->config->get('jwt.access_ttl', 900)
        ]);
    }

    /**
     * User logout - revoke refresh token
     */
    public function logout(Request $request): Response
    {
        $refreshToken = $this->getRefreshTokenFromCookie($request);

        if ($refreshToken) {
            $tokenHash = RefreshToken::hashToken($refreshToken);
            $this->refreshTokenModel->revoke($tokenHash, 'user_logout');
        }

        // Clear refresh token cookie
        $this->clearRefreshTokenCookie();

        return Response::success([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * Refresh access token using refresh token
     */
    public function refresh(Request $request): Response
    {
        $refreshToken = $this->getRefreshTokenFromCookie($request);

        if (!$refreshToken) {
            throw AuthenticationException::tokenInvalid('No refresh token provided');
        }

        $tokenHash = RefreshToken::hashToken($refreshToken);
        $tokenData = $this->refreshTokenModel->findByToken($tokenHash);

        if (!$tokenData) {
            throw AuthenticationException::tokenInvalid('Invalid or expired refresh token');
        }

        // Record token usage
        $this->refreshTokenModel->recordUsage($tokenHash);

        // Generate new access token
        $tokenPayload = [
            'user_id' => $tokenData['user_id'],
            'tenant_id' => $tokenData['tenant_id'],
            'role' => $tokenData['role_name'],
            'email' => $tokenData['email'],
            'mfa_verified' => true // If they had a valid refresh token, MFA was already verified
        ];

        $accessToken = $this->jwtService->generateAccessToken($tokenPayload);

        return Response::success([
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => $this->config->get('jwt.access_ttl', 900)
        ], [
            'refresh_time' => time(),
            'expires_at' => time() + $this->config->get('jwt.access_ttl', 900)
        ]);
    }

    /**
     * Initiate password reset flow
     */
    public function forgotPassword(Request $request): Response
    {
        $email = $request->getBodyParamString('email');

        if (!$email) {
            throw ValidationException::forField('email', 'Email is required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::forField('email', 'Invalid email format');
        }

        // Find user by email (platform-wide search for forgot password)
        $user = $this->userModel->findByEmail($email);

        // Always return success to prevent email enumeration
        // But only actually send email if user exists and is active
        if ($user && $user['account_status'] === 'active') {
            // Generate password reset token
            $resetToken = $this->jwtService->generatePasswordResetToken($user['id'], $user['email']);

            // Set reset token in database
            $expiry = new \DateTime();
            $expiry->modify('+1 hour');
            $this->userModel->updatePasswordReset($user['id'], $resetToken, $expiry);

            // In a real application, send email here
            error_log("Password reset token for {$email}: {$resetToken}");

            // TODO: Send email with reset link
            // $resetLink = $this->config->get('app.frontend_url') . "/reset-password?token={$resetToken}";
            // EmailService::sendPasswordReset($email, $resetLink);
        }

        return Response::success([
            'message' => 'If your email address exists in our system, you will receive a password reset link shortly.'
        ]);
    }

    /**
     * Complete password reset with token
     */
    public function resetPassword(Request $request): Response
    {
        $token = $request->getBodyParamString('token');
        $password = $request->getBodyParamString('password');

        if (!$token || !$password) {
            throw ValidationException::fromFieldErrors([
                'token' => !$token ? ['Reset token is required'] : [],
                'password' => !$password ? ['New password is required'] : []
            ]);
        }

        // Validate password strength
        if (!$this->isValidPassword($password)) {
            throw ValidationException::forField('password',
                'Password must be at least 12 characters long and contain uppercase, lowercase, numbers, and special characters'
            );
        }

        // Validate reset token
        $tokenData = $this->jwtService->validatePasswordResetToken($token);

        if (!$tokenData) {
            throw AuthenticationException::tokenInvalid('Invalid or expired reset token');
        }

        // Find user and verify token matches
        $user = $this->userModel->findByPasswordResetToken($token);

        if (!$user || $user['id'] !== $tokenData['user_id']) {
            throw AuthenticationException::tokenInvalid('Invalid reset token');
        }

        // Hash new password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, ['cost' => $this->config->get('security.bcrypt_cost', 12)]);

        // Update password and clear reset token
        $this->userModel->updatePassword($user['id'], $passwordHash);

        // Revoke all existing refresh tokens for security
        $this->refreshTokenModel->revokeAllForUser($user['id'], 'password_reset');

        return Response::success([
            'message' => 'Password has been reset successfully. Please log in with your new password.'
        ]);
    }

    /**
     * MFA verification for superadmin accounts
     * Currently stubbed - to be implemented in future sprint
     */
    public function mfaVerify(Request $request): Response
    {
        $challengeToken = $request->getBodyParamString('challenge_token');
        $code = $request->getBodyParamString('code');

        if (!$challengeToken || !$code) {
            throw ValidationException::fromFieldErrors([
                'challenge_token' => !$challengeToken ? ['Challenge token is required'] : [],
                'code' => !$code ? ['MFA code is required'] : []
            ]);
        }

        // Validate challenge token
        $tokenData = $this->jwtService->validateMfaToken($challengeToken);

        if (!$tokenData) {
            throw AuthenticationException::tokenInvalid('Invalid or expired challenge token');
        }

        // TODO: Implement actual MFA verification
        // For now, accept any 6-digit code
        if (!preg_match('/^\d{6}$/', $code)) {
            throw ValidationException::forField('code', 'MFA code must be 6 digits');
        }

        // Find user and generate full access token
        $user = $this->userModel->findById($tokenData['user_id']);

        if (!$user) {
            throw NotFoundException::forResource('User', $tokenData['user_id']);
        }

        // Generate full access token with MFA verified
        $tokenPayload = [
            'user_id' => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'role' => $user['role_name'],
            'email' => $user['email'],
            'mfa_verified' => true
        ];

        $accessToken = $this->jwtService->generateAccessToken($tokenPayload);
        $refreshTokenPlain = $this->jwtService->generateRefreshToken();
        $refreshTokenHash = RefreshToken::hashToken($refreshTokenPlain);

        // Store refresh token
        $expiresAt = new \DateTime();
        $expiresAt->modify('+' . $this->config->get('jwt.refresh_ttl', 604800) . ' seconds');

        $this->refreshTokenModel->create(
            $user['id'],
            $user['tenant_id'],
            $refreshTokenHash,
            $expiresAt,
            $request->getClientIp(),
            $request->getUserAgent()
        );

        // Update last login
        $this->userModel->updateLastLogin($user['id']);

        // Set refresh token cookie
        $this->setRefreshTokenCookie($refreshTokenPlain);

        // Remove sensitive data
        unset($user['password_hash'], $user['mfa_secret']);

        return Response::success([
            'access_token' => $accessToken,
            'token_type' => 'bearer',
            'expires_in' => $this->config->get('jwt.access_ttl', 900),
            'user' => $user
        ], [
            'mfa_verified' => true,
            'login_time' => time()
        ]);
    }

    /**
     * Set refresh token as HttpOnly cookie
     */
    private function setRefreshTokenCookie(string $refreshToken): void
    {
        $secure = $this->config->get('security.session_secure', false);
        $expiry = time() + $this->config->get('jwt.refresh_ttl', 604800);

        setcookie(
            'refresh_token',
            $refreshToken,
            [
                'expires' => $expiry,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }

    /**
     * Clear refresh token cookie
     */
    private function clearRefreshTokenCookie(): void
    {
        setcookie(
            'refresh_token',
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => '',
                'secure' => $this->config->get('security.session_secure', false),
                'httponly' => true,
                'samesite' => 'Strict'
            ]
        );
    }

    /**
     * Get refresh token from cookie
     */
    private function getRefreshTokenFromCookie(Request $request): ?string
    {
        $cookies = $request->getCookies();
        return $cookies['refresh_token'] ?? null;
    }

    /**
     * Check if user has verified MFA (stubbed for future implementation)
     */
    private function isMfaVerified(int $userId): bool
    {
        // TODO: Implement actual MFA verification checking
        // This could check a session store, temporary verification table, etc.
        return false; // For now, always require MFA for superadmin
    }

    /**
     * Validate password strength
     */
    private function isValidPassword(string $password): bool
    {
        // At least 12 characters, must contain:
        // - uppercase letter
        // - lowercase letter
        // - number
        // - special character
        return strlen($password) >= 12
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }
}