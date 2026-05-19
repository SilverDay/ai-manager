-- Migration 001: Create tenants table
-- Multi-tenancy foundation table

CREATE TABLE IF NOT EXISTS tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subdomain VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    subscription_tier ENUM('free', 'professional', 'enterprise') NOT NULL DEFAULT 'free',
    max_users INT UNSIGNED DEFAULT 10,
    max_ai_systems INT UNSIGNED DEFAULT 5,

    -- EU AI Act compliance tracking
    dpa_signed_at DATE NULL,
    dpa_document_id INT UNSIGNED NULL,

    -- Contact information
    contact_name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(50) NULL,

    -- Address
    address_line1 VARCHAR(255) NULL,
    address_line2 VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    state_province VARCHAR(100) NULL,
    postal_code VARCHAR(20) NULL,
    country VARCHAR(2) NULL COMMENT 'ISO 3166-1 alpha-2 country code',

    -- Billing
    billing_email VARCHAR(255) NULL,

    -- Feature flags
    features JSON NULL COMMENT 'Tenant-specific feature enablements',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_status (status),
    INDEX idx_subdomain (subdomain),
    INDEX idx_contact_email (contact_email),
    INDEX idx_created_at (created_at)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Multi-tenant organizations';