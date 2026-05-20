<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class UserInvitation
{
    private Database $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Create new invitation
     */
    public function create(array $invitationData): int
    {
        $stmt = $this->db->query(
            'INSERT INTO user_invitations (
                tenant_id, role_id, email, invited_by_user_id,
                token, expires_at, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $invitationData['tenant_id'],
                $invitationData['role_id'],
                strtolower($invitationData['email']),
                $invitationData['invited_by_user_id'],
                $invitationData['token'],
                $invitationData['expires_at'],
                $invitationData['status'] ?? 'pending'
            ]
        );

        return (int) $this->db->getConnection()->lastInsertId();
    }

    /**
     * Find invitation by token
     */
    public function findByToken(string $token): ?array
    {
        $stmt = $this->db->query(
            'SELECT ui.*, r.name as role_name, r.permissions,
                    t.name as tenant_name, t.subdomain,
                    inviter.first_name as inviter_first_name, inviter.last_name as inviter_last_name
             FROM user_invitations ui
             JOIN roles r ON ui.role_id = r.id
             JOIN tenants t ON ui.tenant_id = t.id
             JOIN users inviter ON ui.invited_by_user_id = inviter.id
             WHERE ui.token = ?',
            [$token]
        );

        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invitation) {
            $invitation['permissions'] = json_decode($invitation['permissions'] ?? '[]', true);
        }

        return $invitation ?: null;
    }

    /**
     * Find invitation by email and tenant
     */
    public function findByEmail(string $email, int $tenantId, string $status = null): ?array
    {
        $sql = 'SELECT ui.*, r.name as role_name
                FROM user_invitations ui
                JOIN roles r ON ui.role_id = r.id
                WHERE ui.email = ? AND ui.tenant_id = ?';
        $params = [strtolower($email), $tenantId];

        if ($status !== null) {
            $sql .= ' AND ui.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY ui.created_at DESC LIMIT 1';

        $stmt = $this->db->query($sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List invitations for a tenant
     */
    public function listByTenant(int $tenantId, string $status = null, int $offset = 0, int $limit = 50): array
    {
        $conditions = ['ui.tenant_id = ?'];
        $params = [$tenantId];

        if ($status !== null) {
            $conditions[] = 'ui.status = ?';
            $params[] = $status;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->query(
            "SELECT ui.id, ui.email, ui.status, ui.created_at, ui.expires_at,
                    ui.accepted_at, ui.accepted_by_user_id,
                    r.name as role_name,
                    inviter.first_name as inviter_first_name, inviter.last_name as inviter_last_name,
                    accepter.first_name as accepter_first_name, accepter.last_name as accepter_last_name
             FROM user_invitations ui
             JOIN roles r ON ui.role_id = r.id
             JOIN users inviter ON ui.invited_by_user_id = inviter.id
             LEFT JOIN users accepter ON ui.accepted_by_user_id = accepter.id
             {$whereClause}
             ORDER BY ui.created_at DESC
             LIMIT ? OFFSET ?",
            $params
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count invitations for a tenant
     */
    public function countByTenant(int $tenantId, string $status = null): int
    {
        $conditions = ['tenant_id = ?'];
        $params = [$tenantId];

        if ($status !== null) {
            $conditions[] = 'status = ?';
            $params[] = $status;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM user_invitations {$whereClause}",
            $params
        );

        return (int) $stmt->fetchColumn();
    }

    /**
     * Find invitation by ID with tenant isolation
     */
    public function findById(int $invitationId, int $tenantId): ?array
    {
        $stmt = $this->db->query(
            'SELECT ui.*, r.name as role_name, r.permissions,
                    inviter.first_name as inviter_first_name, inviter.last_name as inviter_last_name
             FROM user_invitations ui
             JOIN roles r ON ui.role_id = r.id
             JOIN users inviter ON ui.invited_by_user_id = inviter.id
             WHERE ui.id = ? AND ui.tenant_id = ?',
            [$invitationId, $tenantId]
        );

        $invitation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($invitation) {
            $invitation['permissions'] = json_decode($invitation['permissions'] ?? '[]', true);
        }

        return $invitation ?: null;
    }

    /**
     * Update invitation status
     */
    public function updateStatus(int $invitationId, int $tenantId, string $status): bool
    {
        $validStatuses = ['pending', 'accepted', 'expired', 'revoked'];

        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid invitation status: {$status}");
        }

        $stmt = $this->db->query(
            'UPDATE user_invitations
             SET status = ?
             WHERE id = ? AND tenant_id = ?',
            [$status, $invitationId, $tenantId]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Mark invitation as accepted
     */
    public function markAsAccepted(int $invitationId, int $acceptedByUserId): bool
    {
        $stmt = $this->db->query(
            'UPDATE user_invitations
             SET status = ?, accepted_at = NOW(), accepted_by_user_id = ?
             WHERE id = ? AND status = "pending"',
            ['accepted', $acceptedByUserId, $invitationId]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Update invitation expiry
     */
    public function updateExpiry(int $invitationId, \DateTime $newExpiry): bool
    {
        $stmt = $this->db->query(
            'UPDATE user_invitations
             SET expires_at = ?
             WHERE id = ?',
            [$newExpiry->format('Y-m-d H:i:s'), $invitationId]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Clean up expired invitations
     */
    public function cleanupExpired(): int
    {
        $stmt = $this->db->query(
            'UPDATE user_invitations
             SET status = "expired"
             WHERE status = "pending" AND expires_at < NOW()'
        );

        return $stmt->rowCount();
    }

    /**
     * Get invitation statistics for a tenant
     */
    public function getStats(int $tenantId): array
    {
        $stmt = $this->db->query(
            'SELECT
                COUNT(*) as total_invitations,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_invitations,
                SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted_invitations,
                SUM(CASE WHEN status = "expired" THEN 1 ELSE 0 END) as expired_invitations,
                SUM(CASE WHEN status = "revoked" THEN 1 ELSE 0 END) as revoked_invitations
             FROM user_invitations
             WHERE tenant_id = ?',
            [$tenantId]
        );

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_invitations' => (int) $stats['total_invitations'],
            'pending_invitations' => (int) $stats['pending_invitations'],
            'accepted_invitations' => (int) $stats['accepted_invitations'],
            'expired_invitations' => (int) $stats['expired_invitations'],
            'revoked_invitations' => (int) $stats['revoked_invitations']
        ];
    }

    /**
     * Revoke all pending invitations for a user
     */
    public function revokeAllForUser(int $invitedByUserId): int
    {
        $stmt = $this->db->query(
            'UPDATE user_invitations
             SET status = "revoked"
             WHERE invited_by_user_id = ? AND status = "pending"',
            [$invitedByUserId]
        );

        return $stmt->rowCount();
    }

    /**
     * Revoke all pending invitations for a tenant
     */
    public function revokeAllForTenant(int $tenantId): int
    {
        $stmt = $this->db->query(
            'UPDATE user_invitations
             SET status = "revoked"
             WHERE tenant_id = ? AND status = "pending"',
            [$tenantId]
        );

        return $stmt->rowCount();
    }

    /**
     * Find invitations expiring soon (for notification purposes)
     */
    public function findExpiringSoon(int $hoursBeforeExpiry = 24): array
    {
        $stmt = $this->db->query(
            'SELECT ui.*, r.name as role_name, t.name as tenant_name,
                    inviter.email as inviter_email
             FROM user_invitations ui
             JOIN roles r ON ui.role_id = r.id
             JOIN tenants t ON ui.tenant_id = t.id
             JOIN users inviter ON ui.invited_by_user_id = inviter.id
             WHERE ui.status = "pending"
               AND ui.expires_at > NOW()
               AND ui.expires_at <= DATE_ADD(NOW(), INTERVAL ? HOUR)
             ORDER BY ui.expires_at ASC',
            [$hoursBeforeExpiry]
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}