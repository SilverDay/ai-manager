-- Migration 003: Create users table
-- User accounts with tenant association

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NULL COMMENT 'NULL for Superadmin users',
    role_id INT UNSIGNED NOT NULL,

    -- Authentication
    email VARCHAR(255) NOT NULL,
    email_verified_at DATETIME NULL,
    password_hash VARCHAR(255) NOT NULL,

    -- Profile
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    job_title VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,

    -- Account status
    account_status ENUM('pending_approval', 'active', 'rejected', 'deactivated', 'deleted') NOT NULL DEFAULT 'pending_approval',
    last_login_at DATETIME NULL,
    login_count INT UNSIGNED NOT NULL DEFAULT 0,

    -- Multi-factor authentication
    mfa_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    mfa_secret VARCHAR(255) NULL,
    mfa_recovery_codes JSON NULL,

    -- Password reset
    password_reset_token VARCHAR(255) NULL,
    password_reset_expires_at DATETIME NULL,

    -- Email verification
    email_verification_token VARCHAR(255) NULL,
    email_verification_expires_at DATETIME NULL,

    -- Preferences
    language VARCHAR(10) NOT NULL DEFAULT 'en',
    timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
    preferences JSON NULL,

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys
    FOREIGN KEY fk_users_tenant_id (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY fk_users_role_id (role_id) REFERENCES roles(id) ON DELETE RESTRICT,

    -- Indexes
    UNIQUE INDEX idx_email (email),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_role_id (role_id),
    INDEX idx_account_status (account_status),
    INDEX idx_tenant_status (tenant_id, account_status),
    INDEX idx_email_verification_token (email_verification_token),
    INDEX idx_password_reset_token (password_reset_token),
    INDEX idx_last_login (last_login_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User accounts with tenant association';