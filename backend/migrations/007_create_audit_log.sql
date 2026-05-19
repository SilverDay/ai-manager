-- Migration 007: Create audit log table
-- Append-only audit trail for all material actions

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL COMMENT 'NULL for platform-level actions',
    user_id INT UNSIGNED NULL COMMENT 'NULL for system actions',
    impersonated_by INT UNSIGNED NULL COMMENT 'User performing impersonation',

    -- Action details
    action VARCHAR(255) NOT NULL COMMENT 'Action type (e.g., user.created, ai_system.updated)',
    entity_type VARCHAR(100) NOT NULL COMMENT 'Type of entity affected',
    entity_id VARCHAR(100) NULL COMMENT 'ID of entity affected (flexible for different ID types)',

    -- Change tracking
    previous_value JSON NULL COMMENT 'Previous state before change',
    new_value JSON NULL COMMENT 'New state after change',

    -- Request context
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_id VARCHAR(100) NULL COMMENT 'Request correlation ID',

    -- Timestamps (NEVER updated)
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    -- Foreign keys (no CASCADE delete - audit log is preserved)
    FOREIGN KEY fk_audit_tenant_id (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL,
    FOREIGN KEY fk_audit_user_id (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY fk_audit_impersonated_by (impersonated_by) REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes for audit queries
    INDEX idx_tenant_action (tenant_id, action),
    INDEX idx_user_action (user_id, action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action_time (action, created_at),
    INDEX idx_created_at (created_at),
    INDEX idx_tenant_time (tenant_id, created_at),
    INDEX idx_user_time (user_id, created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Append-only audit trail (NEVER delete/update)';