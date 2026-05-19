-- Migration 005: Create user invitations table
-- Tokenized user invitations for specific tenants and roles

CREATE TABLE IF NOT EXISTS user_invitations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    invited_by_user_id INT UNSIGNED NOT NULL,

    -- Invitation details
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100) NULL,
    last_name VARCHAR(100) NULL,
    message TEXT NULL COMMENT 'Optional message from inviter',

    -- Status and lifecycle
    status ENUM('pending', 'accepted', 'expired', 'revoked') NOT NULL DEFAULT 'pending',
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    accepted_by_user_id INT UNSIGNED NULL COMMENT 'User created from this invitation',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY fk_invitations_tenant_id (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY fk_invitations_role_id (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY fk_invitations_invited_by (invited_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY fk_invitations_accepted_by (accepted_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_token (token),
    INDEX idx_email (email),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_status (status),
    INDEX idx_tenant_status (tenant_id, status),
    INDEX idx_expires_at (expires_at),
    INDEX idx_invited_by (invited_by_user_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User invitations with tokens';