-- Migration 009: Create legal acceptances table
-- Track user acceptance of Terms of Service, Privacy Policy, etc.

CREATE TABLE IF NOT EXISTS legal_acceptances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,

    -- Document details
    document_type ENUM('terms_of_service', 'privacy_policy', 'data_processing_agreement', 'cookie_policy') NOT NULL,
    document_version VARCHAR(50) NOT NULL COMMENT 'Version identifier (e.g., v1.2, 2024-01-15)',

    -- Acceptance tracking
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,

    -- Context
    acceptance_method ENUM('registration', 'explicit_consent', 'continued_use', 'admin_bulk') NOT NULL DEFAULT 'explicit_consent',

    -- Foreign keys
    FOREIGN KEY fk_legal_user_id (user_id) REFERENCES users(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_user_id (user_id),
    INDEX idx_document_type (document_type),
    INDEX idx_document_version (document_version),
    INDEX idx_user_document (user_id, document_type),
    INDEX idx_acceptance_date (accepted_at),

    -- Ensure one acceptance per user per document version
    UNIQUE INDEX idx_user_document_version (user_id, document_type, document_version)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Legal document acceptance tracking';