<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\SignatureInvalidException;

final class JwtService
{
    private Config $config;
    private string $secret;
    private string $algorithm;
    private int $accessTtl;
    private int $refreshTtl;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->secret = $this->config->get('jwt.secret');
        $this->algorithm = $this->config->get('jwt.algorithm', 'HS256');
        $this->accessTtl = (int) $this->config->get('jwt.access_ttl', 900); // 15 minutes
        $this->refreshTtl = (int) $this->config->get('jwt.refresh_ttl', 604800); // 7 days

        // Validate JWT secret strength
        if (strlen($this->secret) < 32) {
            throw new \InvalidArgumentException('JWT secret must be at least 32 characters');
        }
    }

    /**
     * Generate access token with user and tenant context
     */
    public function generateAccessToken(array $payload): string
    {
        $now = time();

        $tokenPayload = [
            'iss' => $this->config->get('app.url', 'aigov-platform'),
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
            'sub' => (string) $payload['user_id'],
            'user_id' => $payload['user_id'],
            'tenant_id' => $payload['tenant_id'] ?? null,
            'role' => $payload['role'] ?? null,
            'email' => $payload['email'] ?? null,
            'mfa_verified' => $payload['mfa_verified'] ?? false,
            'token_type' => 'access'
        ];

        return JWT::encode($tokenPayload, $this->secret, $this->algorithm);
    }

    /**
     * Generate refresh token (simple random string for database storage)
     */
    public function generateRefreshToken(): string
    {
        // Generate cryptographically secure random token
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate JWT token and return payload
     *
     * @param string $token
     * @return array|false Returns decoded payload or false if invalid
     */
    public function validateToken(string $token): array|false
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return (array) $decoded;
        } catch (ExpiredException) {
            error_log('JWT token expired');
            return false;
        } catch (BeforeValidException) {
            error_log('JWT token not yet valid');
            return false;
        } catch (SignatureInvalidException) {
            error_log('JWT signature invalid');
            return false;
        } catch (\Exception $e) {
            error_log('JWT validation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract tenant ID from JWT token
     */
    public function extractTenantId(string $token): ?int
    {
        $payload = $this->validateToken($token);

        if ($payload === false) {
            return null;
        }

        return isset($payload['tenant_id']) ? (int) $payload['tenant_id'] : null;
    }

    /**
     * Extract user ID from JWT token
     */
    public function extractUserId(string $token): ?int
    {
        $payload = $this->validateToken($token);

        if ($payload === false) {
            return null;
        }

        return isset($payload['user_id']) ? (int) $payload['user_id'] : null;
    }

    /**
     * Extract user role from JWT token
     */
    public function extractUserRole(string $token): ?string
    {
        $payload = $this->validateToken($token);

        if ($payload === false) {
            return null;
        }

        return $payload['role'] ?? null;
    }

    /**
     * Check if token requires MFA verification
     */
    public function requiresMfaVerification(string $token): bool
    {
        $payload = $this->validateToken($token);

        if ($payload === false) {
            return false;
        }

        return !($payload['mfa_verified'] ?? false);
    }

    /**
     * Generate a short-lived MFA challenge token
     */
    public function generateMfaToken(int $userId, string $challenge): string
    {
        $now = time();

        $payload = [
            'iss' => $this->config->get('app.url', 'aigov-platform'),
            'iat' => $now,
            'exp' => $now + 300, // 5 minutes for MFA completion
            'sub' => (string) $userId,
            'user_id' => $userId,
            'challenge' => $challenge,
            'token_type' => 'mfa_challenge'
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Validate MFA challenge token and extract user ID
     */
    public function validateMfaToken(string $token): array|false
    {
        $payload = $this->validateToken($token);

        if ($payload === false || ($payload['token_type'] ?? '') !== 'mfa_challenge') {
            return false;
        }

        return [
            'user_id' => $payload['user_id'],
            'challenge' => $payload['challenge']
        ];
    }

    /**
     * Create password reset token
     */
    public function generatePasswordResetToken(int $userId, string $email): string
    {
        $now = time();

        $payload = [
            'iss' => $this->config->get('app.url', 'aigov-platform'),
            'iat' => $now,
            'exp' => $now + 3600, // 1 hour for password reset
            'sub' => (string) $userId,
            'user_id' => $userId,
            'email' => $email,
            'token_type' => 'password_reset'
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Validate password reset token
     */
    public function validatePasswordResetToken(string $token): array|false
    {
        $payload = $this->validateToken($token);

        if ($payload === false || ($payload['token_type'] ?? '') !== 'password_reset') {
            return false;
        }

        return [
            'user_id' => $payload['user_id'],
            'email' => $payload['email']
        ];
    }

    /**
     * Create email verification token
     */
    public function generateEmailVerificationToken(int $userId, string $email): string
    {
        $now = time();

        $payload = [
            'iss' => $this->config->get('app.url', 'aigov-platform'),
            'iat' => $now,
            'exp' => $now + 86400, // 24 hours for email verification
            'sub' => (string) $userId,
            'user_id' => $userId,
            'email' => $email,
            'token_type' => 'email_verification'
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    /**
     * Validate email verification token
     */
    public function validateEmailVerificationToken(string $token): array|false
    {
        $payload = $this->validateToken($token);

        if ($payload === false || ($payload['token_type'] ?? '') !== 'email_verification') {
            return false;
        }

        return [
            'user_id' => $payload['user_id'],
            'email' => $payload['email']
        ];
    }
}