<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class TenantDomain
{
    private Database $db;

    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Find domain configuration by domain name
     */
    public function findByDomain(string $domain): ?array
    {
        $stmt = $this->db->query(
            'SELECT td.*, t.name as tenant_name, t.subdomain, t.status as tenant_status,
                    r.name as default_role_name
             FROM tenant_domains td
             JOIN tenants t ON td.tenant_id = t.id
             JOIN roles r ON td.default_role_id = r.id
             WHERE td.domain = ?',
            [strtolower($domain)]
        );

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Convert boolean fields
            $result['auto_approve'] = (bool) $result['auto_approve'];
            $result['verified'] = (bool) $result['verified'];
        }

        return $result ?: null;
    }

    /**
     * List all domains for a tenant
     */
    public function listByTenant(int $tenantId): array
    {
        $stmt = $this->db->query(
            'SELECT td.*, r.name as default_role_name
             FROM tenant_domains td
             JOIN roles r ON td.default_role_id = r.id
             WHERE td.tenant_id = ?
             ORDER BY td.domain ASC',
            [$tenantId]
        );

        $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($domains as &$domain) {
            $domain['auto_approve'] = (bool) $domain['auto_approve'];
            $domain['verified'] = (bool) $domain['verified'];
        }

        return $domains;
    }

    /**
     * Create new tenant domain
     */
    public function create(array $domainData): int
    {
        $stmt = $this->db->query(
            'INSERT INTO tenant_domains (
                tenant_id, domain, auto_approve, default_role_id,
                verification_method, verification_token, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                $domainData['tenant_id'],
                strtolower($domainData['domain']),
                $domainData['auto_approve'] ? 1 : 0,
                $domainData['default_role_id'],
                $domainData['verification_method'] ?? 'manual',
                $domainData['verification_token'] ?? null
            ]
        );

        return (int) $this->db->getConnection()->lastInsertId();
    }

    /**
     * Update domain configuration
     */
    public function update(int $domainId, int $tenantId, array $updates): bool
    {
        $allowedFields = ['auto_approve', 'default_role_id', 'verification_method'];
        $setClause = [];
        $params = [];

        foreach ($updates as $field => $value) {
            if (in_array($field, $allowedFields, true)) {
                $setClause[] = "{$field} = ?";
                $params[] = $value;
            }
        }

        if (empty($setClause)) {
            return false;
        }

        $params[] = $domainId;
        $params[] = $tenantId;

        $sql = 'UPDATE tenant_domains SET ' . implode(', ', $setClause) . ' WHERE id = ? AND tenant_id = ?';
        $stmt = $this->db->query($sql, $params);

        return $stmt->rowCount() > 0;
    }

    /**
     * Delete domain
     */
    public function delete(int $domainId, int $tenantId): bool
    {
        $stmt = $this->db->query(
            'DELETE FROM tenant_domains WHERE id = ? AND tenant_id = ?',
            [$domainId, $tenantId]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Verify domain ownership
     */
    public function markAsVerified(int $domainId, int $tenantId): bool
    {
        $stmt = $this->db->query(
            'UPDATE tenant_domains
             SET verified = 1, verified_at = NOW(), verification_token = NULL
             WHERE id = ? AND tenant_id = ?',
            [$domainId, $tenantId]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if domain is already registered
     */
    public function domainExists(string $domain, ?int $excludeTenantId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM tenant_domains WHERE domain = ?';
        $params = [strtolower($domain)];

        if ($excludeTenantId !== null) {
            $sql .= ' AND tenant_id != ?';
            $params[] = $excludeTenantId;
        }

        $stmt = $this->db->query($sql, $params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Find domain by ID with tenant isolation
     */
    public function findById(int $domainId, int $tenantId): ?array
    {
        $stmt = $this->db->query(
            'SELECT td.*, t.name as tenant_name, r.name as default_role_name
             FROM tenant_domains td
             JOIN tenants t ON td.tenant_id = t.id
             JOIN roles r ON td.default_role_id = r.id
             WHERE td.id = ? AND td.tenant_id = ?',
            [$domainId, $tenantId]
        );

        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            $result['auto_approve'] = (bool) $result['auto_approve'];
            $result['verified'] = (bool) $result['verified'];
        }

        return $result ?: null;
    }

    /**
     * Generate verification token for domain ownership verification
     */
    public function generateVerificationToken(int $domainId, int $tenantId, string $method = 'dns'): ?string
    {
        $token = 'aigov-verify-' . bin2hex(random_bytes(16));

        $stmt = $this->db->query(
            'UPDATE tenant_domains
             SET verification_token = ?, verification_method = ?
             WHERE id = ? AND tenant_id = ?',
            [$token, $method, $domainId, $tenantId]
        );

        return $stmt->rowCount() > 0 ? $token : null;
    }

    /**
     * Get domain statistics for a tenant
     */
    public function getStats(int $tenantId): array
    {
        $stmt = $this->db->query(
            'SELECT
                COUNT(*) as total_domains,
                SUM(CASE WHEN verified = 1 THEN 1 ELSE 0 END) as verified_domains,
                SUM(CASE WHEN auto_approve = 1 THEN 1 ELSE 0 END) as auto_approve_domains
             FROM tenant_domains
             WHERE tenant_id = ?',
            [$tenantId]
        );

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total_domains' => (int) $stats['total_domains'],
            'verified_domains' => (int) $stats['verified_domains'],
            'unverified_domains' => (int) $stats['total_domains'] - (int) $stats['verified_domains'],
            'auto_approve_domains' => (int) $stats['auto_approve_domains']
        ];
    }
}