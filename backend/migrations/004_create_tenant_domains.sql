-- Migration 004: Create tenant domains table
-- Email domains mapped to tenants for auto-registration

CREATE TABLE IF NOT EXISTS tenant_domains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    domain VARCHAR(255) NOT NULL COMMENT 'Email domain (e.g., company.com)',
    auto_approve BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Auto-approve users from this domain',
    default_role_id INT UNSIGNED NOT NULL COMMENT 'Default role for users from this domain',

    -- Verification
    verified BOOLEAN NOT NULL DEFAULT FALSE,
    verification_token VARCHAR(255) NULL,
    verification_method ENUM('dns', 'email', 'manual') NOT NULL DEFAULT 'manual',
    verified_at DATETIME NULL,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY fk_tenant_domains_tenant_id (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY fk_tenant_domains_default_role_id (default_role_id) REFERENCES roles(id) ON DELETE RESTRICT,

    -- Constraints
    UNIQUE INDEX idx_domain (domain),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_verified (verified),
    INDEX idx_tenant_domain (tenant_id, domain)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email domains mapped to tenants';