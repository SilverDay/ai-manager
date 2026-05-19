-- Migration 006: Create refresh tokens table
-- JWT refresh token storage with hashed tokens

CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash VARCHAR(255) NOT NULL UNIQUE COMMENT 'SHA-256 hash of the actual token',

    -- Token metadata
    user_agent TEXT NULL,
    ip_address VARCHAR(45) NULL COMMENT 'IPv4 or IPv6 address',

    -- Lifecycle
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    revoked_reason ENUM('user_logout', 'user_deactivated', 'tenant_deactivated', 'security_breach', 'admin_action') NULL,

    -- Usage tracking
    last_used_at DATETIME NULL,
    use_count INT UNSIGNED NOT NULL DEFAULT 0,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY fk_refresh_tokens_user_id (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_token_hash (token_hash),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    INDEX idx_revoked_at (revoked_at),
    INDEX idx_user_active (user_id, expires_at, revoked_at),
    INDEX idx_cleanup (expires_at, revoked_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='JWT refresh tokens with hashing';