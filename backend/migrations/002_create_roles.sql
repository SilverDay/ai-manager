-- Migration 002: Create roles table
-- RBAC role definitions (global, not tenant-scoped)

CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_tenant_role BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'FALSE for platform-level roles like Superadmin',
    permissions JSON NOT NULL COMMENT 'Array of permission strings',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_name (name),
    INDEX idx_is_tenant_role (is_tenant_role)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Role definitions for RBAC';