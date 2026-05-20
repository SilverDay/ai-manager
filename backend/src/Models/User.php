<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class User
{
    private Database $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Find user by email with tenant isolation
     * For superadmin users, tenant_id is NULL
     *
     * @param string $email User email address
     * @param int|null $tenantId Tenant ID for isolation (null for platform search)
     * @return array|null User record or null if not found
     */
    public function findByEmail(string $email, ?int $tenantId = null): ?array
    {
        if ($tenantId === null) {
            // Platform-level search (for superadmin or cross-tenant operations)
            $stmt = $this->db->query(
                'SELECT u.*, r.name as role_name, r.permissions, r.is_tenant_role
                 FROM users u
                 JOIN roles r ON u.role_id = r.id
                 WHERE u.email = ?',
                [$email]
            );
        } else {
            // Tenant-scoped search
            $stmt = $this->db->query(
                'SELECT u.*, r.name as role_name, r.permissions, r.is_tenant_role
                 FROM users u
                 JOIN roles r ON u.role_id = r.id
                 WHERE u.email = ? AND u.tenant_id = ?',
                [$email, $tenantId]
            );
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Decode JSON fields
            $user['permissions'] = json_decode($user['permissions'] ?? '[]', true);
            $user['mfa_recovery_codes'] = json_decode($user['mfa_recovery_codes'] ?? '[]', true);
            $user['preferences'] = json_decode($user['preferences'] ?? '{}', true);

            // Convert boolean fields
            $user['mfa_enabled'] = (bool) $user['mfa_enabled'];
            $user['is_tenant_role'] = (bool) $user['is_tenant_role'];
        }

        return $user ?: null;
    }

    /**
     * Verify user password and return user data if valid
     * Includes account status and tenant validation
     */
    public function verifyPassword(string $email, string $password, ?int $tenantId = null): ?array
    {
        $user = $this->findByEmail($email, $tenantId);

        if (!$user) {
            return null;
        }

        // Check if account is active
        if ($user['account_status'] !== 'active') {
            return null;
        }

        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Remove sensitive data
        unset($user['password_hash'], $user['mfa_secret']);

        return $user;
    }

    /**
     * Find user by ID with tenant isolation
     */
    public function findById(int $id, ?int $tenantId = null): ?array
    {
        if ($tenantId === null) {
            // Platform-level search (for superadmin operations)
            $stmt = $this->db->query(
                'SELECT u.*, r.name as role_name, r.permissions, r.is_tenant_role,
                        t.subdomain as tenant_subdomain, t.name as tenant_name
                 FROM users u
                 JOIN roles r ON u.role_id = r.id
                 LEFT JOIN tenants t ON u.tenant_id = t.id
                 WHERE u.id = ?',
                [$id]
            );
        } else {
            // Tenant-scoped search
            $stmt = $this->db->query(
                'SELECT u.*, r.name as role_name, r.permissions, r.is_tenant_role,
                        t.subdomain as tenant_subdomain, t.name as tenant_name
                 FROM users u
                 JOIN roles r ON u.role_id = r.id
                 LEFT JOIN tenants t ON u.tenant_id = t.id
                 WHERE u.id = ? AND u.tenant_id = ?',
                [$id, $tenantId]
            );
        }

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Decode JSON fields
            $user['permissions'] = json_decode($user['permissions'] ?? '[]', true);
            $user['mfa_recovery_codes'] = json_decode($user['mfa_recovery_codes'] ?? '[]', true);
            $user['preferences'] = json_decode($user['preferences'] ?? '{}', true);

            // Convert boolean fields
            $user['mfa_enabled'] = (bool) $user['mfa_enabled'];
            $user['is_tenant_role'] = (bool) $user['is_tenant_role'];

            // Remove sensitive data
            unset($user['password_hash'], $user['mfa_secret']);
        }

        return $user ?: null;
    }

    /**
     * Update user's last login timestamp
     */
    public function updateLastLogin(int $userId): void
    {
        $this->db->query(
            'UPDATE users SET last_login_at = NOW() WHERE id = ?',
            [$userId]
        );
    }

    /**
     * Update user's password hash
     */
    public function updatePassword(int $userId, string $passwordHash): void
    {
        $this->db->query(
            'UPDATE users SET password_hash = ?, password_reset_token = NULL, password_reset_expires_at = NULL WHERE id = ?',
            [$passwordHash, $userId]
        );
    }

    /**
     * Set or clear password reset token
     */
    public function updatePasswordReset(int $userId, ?string $token = null, ?\DateTime $expiry = null): void
    {
        $expiryString = $expiry ? $expiry->format('Y-m-d H:i:s') : null;

        $this->db->query(
            'UPDATE users SET password_reset_token = ?, password_reset_expires_at = ? WHERE id = ?',
            [$token, $expiryString, $userId]
        );
    }

    /**
     * Find user by password reset token
     */
    public function findByPasswordResetToken(string $token): ?array
    {
        $stmt = $this->db->query(
            'SELECT u.*, r.name as role_name, r.permissions
             FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE u.password_reset_token = ? AND u.password_reset_expires_at > NOW()',
            [$token]
        );

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user['permissions'] = json_decode($user['permissions'] ?? '[]', true);
            unset($user['password_hash'], $user['mfa_secret']);
        }

        return $user ?: null;
    }

    /**
     * Create new user with tenant association
     */
    public function create(array $userData): int
    {
        $stmt = $this->db->query(
            'INSERT INTO users (
                tenant_id, role_id, email, password_hash, first_name, last_name,
                account_status, language, timezone, email_verification_token,
                email_verification_expires_at, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $userData['tenant_id'] ?? null,
                $userData['role_id'],
                $userData['email'],
                $userData['password_hash'],
                $userData['first_name'],
                $userData['last_name'],
                $userData['account_status'] ?? 'pending_approval',
                $userData['language'] ?? 'en',
                $userData['timezone'] ?? 'UTC',
                $userData['email_verification_token'] ?? null,
                $userData['email_verification_expires_at'] ?? null
            ]
        );

        return (int) $this->db->getConnection()->lastInsertId();
    }

    /**
     * Update account status (approve/reject/deactivate)
     */
    public function updateAccountStatus(int $userId, string $status, ?int $tenantId = null): bool
    {
        $validStatuses = ['pending_approval', 'active', 'rejected', 'deactivated', 'deleted'];

        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid account status: {$status}");
        }

        if ($tenantId === null) {
            // Platform-level operation (superadmin)
            $result = $this->db->query(
                'UPDATE users SET account_status = ? WHERE id = ?',
                [$status, $userId]
            );
        } else {
            // Tenant-scoped operation
            $result = $this->db->query(
                'UPDATE users SET account_status = ? WHERE id = ? AND tenant_id = ?',
                [$status, $userId, $tenantId]
            );
        }

        return $result->rowCount() > 0;
    }

    /**
     * List users with tenant isolation
     */
    public function listUsers(?int $tenantId, string $status = null, int $offset = 0, int $limit = 50): array
    {
        $conditions = [];
        $params = [];

        // Tenant isolation
        if ($tenantId === null) {
            // Platform-level view (superadmin only)
            $conditions[] = '1=1';
        } else {
            $conditions[] = 'u.tenant_id = ?';
            $params[] = $tenantId;
        }

        // Status filter
        if ($status !== null) {
            $conditions[] = 'u.account_status = ?';
            $params[] = $status;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->query(
            "SELECT u.id, u.tenant_id, u.email, u.first_name, u.last_name,
                    u.account_status, u.last_login_at, u.created_at,
                    r.name as role_name, t.name as tenant_name, t.subdomain
             FROM users u
             JOIN roles r ON u.role_id = r.id
             LEFT JOIN tenants t ON u.tenant_id = t.id
             {$whereClause}
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count users with filters
     */
    public function countUsers(?int $tenantId, string $status = null): int
    {
        $conditions = [];
        $params = [];

        if ($tenantId === null) {
            $conditions[] = '1=1';
        } else {
            $conditions[] = 'tenant_id = ?';
            $params[] = $tenantId;
        }

        if ($status !== null) {
            $conditions[] = 'account_status = ?';
            $params[] = $status;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->db->query(
            "SELECT COUNT(*) as total FROM users {$whereClause}",
            $params
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * Set email verification token
     */
    public function setEmailVerificationToken(int $userId, string $token, \DateTime $expiry): void
    {
        $this->db->query(
            'UPDATE users SET email_verification_token = ?, email_verification_expires_at = ? WHERE id = ?',
            [$token, $expiry->format('Y-m-d H:i:s'), $userId]
        );
    }

    /**
     * Mark email as verified
     */
    public function markEmailVerified(int $userId): void
    {
        $this->db->query(
            'UPDATE users SET email_verified_at = NOW(), email_verification_token = NULL, email_verification_expires_at = NULL WHERE id = ?',
            [$userId]
        );
    }

    /**
     * Update user preferences
     */
    public function updatePreferences(int $userId, array $preferences, ?int $tenantId = null): bool
    {
        $preferencesJson = json_encode($preferences);

        if ($tenantId === null) {
            $result = $this->db->query(
                'UPDATE users SET preferences = ? WHERE id = ?',
                [$preferencesJson, $userId]
            );
        } else {
            $result = $this->db->query(
                'UPDATE users SET preferences = ? WHERE id = ? AND tenant_id = ?',
                [$preferencesJson, $userId, $tenantId]
            );
        }

        return $result->rowCount() > 0;
    }
}