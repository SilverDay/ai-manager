-- Migration 010: Create freemail blocklist table
-- Global blocklist of domains not allowed for user registration

CREATE TABLE IF NOT EXISTS freemail_blocklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Domain identification
    domain VARCHAR(255) NOT NULL UNIQUE COMMENT 'Domain to block (e.g., gmail.com, yahoo.com)',

    -- Blocking configuration
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    reason VARCHAR(255) NULL COMMENT 'Why this domain is blocked',

    -- Metadata
    added_by_user_id INT UNSIGNED NULL COMMENT 'User who added this entry',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys (soft reference - don't restrict deletion)
    FOREIGN KEY fk_blocklist_added_by (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    -- Indexes
    INDEX idx_domain (domain),
    INDEX idx_active (is_active),
    INDEX idx_active_domain (is_active, domain)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Global freemail domain blocklist';