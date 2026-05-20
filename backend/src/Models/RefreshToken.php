<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class RefreshToken
{
    private Database $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Create new refresh token record
     *
     * @param int $userId User ID
     * @param int|null $tenantId Tenant ID (null for superadmin)
     * @param string $tokenHash SHA-256 hash of the refresh token
     * @param \DateTime $expiresAt Token expiry datetime
     * @param string $ipAddress Client IP address
     * @param string $userAgent Client user agent
     * @return int Token record ID
     */
    public function create(
        int $userId,
        ?int $tenantId,
        string $tokenHash,
        \DateTime $expiresAt,
        string $ipAddress,
        string $userAgent
    ): int {
        $stmt = $this->db->query(
            'INSERT INTO refresh_tokens (
                token_hash, user_id, tenant_id, expires_at,
                ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $tokenHash,
                $userId,
                $tenantId,
                $expiresAt->format('Y-m-d H:i:s'),
                $ipAddress,
                substr($userAgent, 0, 500) // Truncate long user agents
            ]
        );

        return (int) $this->db->getConnection()->lastInsertId();
    }

    /**
     * Find refresh token by hash
     * Returns token data with user information if found and not revoked/expired
     */
    public function findByToken(string $tokenHash): ?array
    {
        $stmt = $this->db->query(
            'SELECT rt.*, u.email, u.account_status, u.tenant_id as user_tenant_id,
                    r.name as role_name, r.permissions
             FROM refresh_tokens rt
             JOIN users u ON rt.user_id = u.id
             JOIN roles r ON u.role_id = r.id
             WHERE rt.token_hash = ?
               AND rt.revoked_at IS NULL
               AND rt.expires_at > NOW()
               AND u.account_status = "active"',
            [$tokenHash]
        );

        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($token) {
            // Decode permissions JSON
            $token['permissions'] = json_decode($token['permissions'] ?? '[]', true);
        }

        return $token ?: null;
    }

    /**
     * Revoke a specific refresh token
     */
    public function revoke(string $tokenHash, string $reason = 'logout'): void
    {
        $this->db->query(
            'UPDATE refresh_tokens
             SET revoked_at = NOW(), revoked_reason = ?
             WHERE token_hash = ? AND revoked_at IS NULL',
            [$reason, $tokenHash]
        );
    }

    /**
     * Revoke all refresh tokens for a specific user
     * Used for password changes, account deactivation, security breaches
     */
    public function revokeAllForUser(int $userId, string $reason = 'password_change'): int
    {
        $stmt = $this->db->query(
            'UPDATE refresh_tokens
             SET revoked_at = NOW(), revoked_reason = ?
             WHERE user_id = ? AND revoked_at IS NULL',
            [$reason, $userId]
        );

        return $stmt->rowCount();
    }

    /**
     * Revoke all refresh tokens for a tenant
     * Used for tenant deactivation or security incidents
     */
    public function revokeAllForTenant(int $tenantId, string $reason = 'tenant_deactivated'): int
    {
        $stmt = $this->db->query(
            'UPDATE refresh_tokens
             SET revoked_at = NOW(), revoked_reason = ?
             WHERE tenant_id = ? AND revoked_at IS NULL',
            [$reason, $tenantId]
        );

        return $stmt->rowCount();
    }

    /**
     * Update token usage tracking
     */
    public function recordUsage(string $tokenHash): void
    {
        $this->db->query(
            'UPDATE refresh_tokens
             SET last_used_at = NOW(), use_count = use_count + 1
             WHERE token_hash = ?',
            [$tokenHash]
        );
    }

    /**
     * Clean up expired and revoked tokens
     * Returns number of deleted records
     */
    public function cleanup(int $gracePeriodDays = 30): int
    {
        // Delete tokens that expired more than X days ago or were revoked more than X days ago
        $cutoffDate = (new \DateTime())->modify("-{$gracePeriodDays} days");

        $stmt = $this->db->query(
            'DELETE FROM refresh_tokens
             WHERE (expires_at < ? OR revoked_at < ?)',
            [
                $cutoffDate->format('Y-m-d H:i:s'),
                $cutoffDate->format('Y-m-d H:i:s')
            ]
        );

        return $stmt->rowCount();
    }

    /**
     * Get active token count for a user
     */
    public function getActiveTokenCountForUser(int $userId): int
    {
        $stmt = $this->db->query(
            'SELECT COUNT(*) as count
             FROM refresh_tokens
             WHERE user_id = ?
               AND revoked_at IS NULL
               AND expires_at > NOW()',
            [$userId]
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * List active tokens for a user (for session management UI)
     * Includes limited information for security
     */
    public function listActiveTokensForUser(int $userId, ?int $tenantId = null): array
    {
        $query = 'SELECT
                    id,
                    LEFT(token_hash, 8) as token_prefix,
                    created_at,
                    last_used_at,
                    expires_at,
                    ip_address,
                    LEFT(user_agent, 100) as user_agent_short,
                    use_count
                  FROM refresh_tokens
                  WHERE user_id = ?
                    AND revoked_at IS NULL
                    AND expires_at > NOW()';

        $params = [$userId];

        // Add tenant isolation if specified
        if ($tenantId !== null) {
            $query .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }

        $query .= ' ORDER BY created_at DESC';

        $stmt = $this->db->query($query, $params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Revoke token by ID (for user session management)
     */
    public function revokeById(int $tokenId, int $userId, ?int $tenantId = null): bool
    {
        $query = 'UPDATE refresh_tokens
                  SET revoked_at = NOW(), revoked_reason = ?
                  WHERE id = ? AND user_id = ? AND revoked_at IS NULL';

        $params = ['user_logout', $tokenId, $userId];

        // Add tenant isolation if specified
        if ($tenantId !== null) {
            $query .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }

        $stmt = $this->db->query($query, $params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Get token statistics for monitoring
     */
    public function getTokenStats(?int $tenantId = null): array
    {
        $conditions = ['1=1'];
        $params = [];

        if ($tenantId !== null) {
            $conditions[] = 'tenant_id = ?';
            $params[] = $tenantId;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->db->query(
            "SELECT
                COUNT(*) as total_tokens,
                SUM(CASE WHEN revoked_at IS NULL AND expires_at > NOW() THEN 1 ELSE 0 END) as active_tokens,
                SUM(CASE WHEN revoked_at IS NOT NULL THEN 1 ELSE 0 END) as revoked_tokens,
                SUM(CASE WHEN expires_at <= NOW() THEN 1 ELSE 0 END) as expired_tokens,
                AVG(use_count) as avg_use_count,
                COUNT(DISTINCT user_id) as unique_users
             FROM refresh_tokens
             {$whereClause}",
            $params
        );

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Convert to proper types
        $stats['total_tokens'] = (int) $stats['total_tokens'];
        $stats['active_tokens'] = (int) $stats['active_tokens'];
        $stats['revoked_tokens'] = (int) $stats['revoked_tokens'];
        $stats['expired_tokens'] = (int) $stats['expired_tokens'];
        $stats['avg_use_count'] = round((float) $stats['avg_use_count'], 2);
        $stats['unique_users'] = (int) $stats['unique_users'];

        return $stats;
    }

    /**
     * Find potentially suspicious token usage patterns
     * Returns tokens with unusual activity for security monitoring
     */
    public function findSuspiciousTokens(): array
    {
        $stmt = $this->db->query(
            'SELECT rt.token_hash, rt.user_id, rt.ip_address, rt.user_agent,
                    rt.created_at, rt.last_used_at, rt.use_count,
                    u.email, u.tenant_id
             FROM refresh_tokens rt
             JOIN users u ON rt.user_id = u.id
             WHERE rt.revoked_at IS NULL
               AND rt.expires_at > NOW()
               AND (
                   rt.use_count > 1000 OR  -- High usage
                   rt.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)  -- Very old tokens
               )
             ORDER BY rt.use_count DESC, rt.created_at ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate secure token hash from plaintext token
     * Use this to hash tokens before storing them
     */
    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    /**
     * Check if token hash format is valid
     */
    public static function isValidTokenHash(string $hash): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $hash) === 1;
    }
}