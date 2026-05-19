-- Migration 008: Create settings table
-- Key-value configuration store with scoping

CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL COMMENT 'NULL for global settings',
    scope ENUM('global', 'tenant') NOT NULL DEFAULT 'tenant',

    -- Setting identification
    setting_key VARCHAR(255) NOT NULL,
    setting_category VARCHAR(100) NOT NULL DEFAULT 'general',

    -- Value storage
    setting_value JSON NOT NULL COMMENT 'Flexible value storage',
    data_type ENUM('string', 'integer', 'float', 'boolean', 'array', 'object') NOT NULL DEFAULT 'string',

    -- Metadata
    description TEXT NULL,
    is_encrypted BOOLEAN NOT NULL DEFAULT FALSE,
    is_readonly BOOLEAN NOT NULL DEFAULT FALSE,
    default_value JSON NULL,

    -- Validation
    validation_rules JSON NULL COMMENT 'Validation constraints',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY fk_settings_tenant_id (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,

    -- Unique constraints
    UNIQUE INDEX idx_tenant_setting (tenant_id, setting_key),

    -- Indexes
    INDEX idx_scope (scope),
    INDEX idx_category (setting_category),
    INDEX idx_tenant_category (tenant_id, setting_category),
    INDEX idx_readonly (is_readonly)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Hierarchical settings with tenant scoping';