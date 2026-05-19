-- Migration 011: Create regulatory rule versions table
-- Track versions of EU AI Act rules and regulatory interpretations

CREATE TABLE IF NOT EXISTS regulatory_rule_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Rule identification
    rule_code VARCHAR(100) NOT NULL COMMENT 'Stable identifier (e.g., EU_AI_ACT_ART_6, GDPR_ART_22)',
    version VARCHAR(50) NOT NULL COMMENT 'Version identifier (e.g., v1.0, 2024-08-01)',

    -- Rule content
    title VARCHAR(500) NOT NULL,
    description TEXT NOT NULL,
    regulatory_text LONGTEXT NULL COMMENT 'Full text of the rule or regulation',

    -- Classification
    regulation_type ENUM('eu_ai_act', 'gdpr', 'nis2', 'dsa', 'dga', 'other') NOT NULL DEFAULT 'eu_ai_act',
    severity_level ENUM('critical', 'high', 'medium', 'low', 'informational') NOT NULL DEFAULT 'medium',
    article_reference VARCHAR(255) NULL COMMENT 'Article/section reference (e.g., Article 6, Section 2.1)',

    -- Applicability
    applies_to_risk_categories JSON NULL COMMENT 'Array of risk categories this rule applies to',
    applies_to_system_types JSON NULL COMMENT 'Array of AI system types this rule applies to',

    -- Lifecycle
    effective_from DATE NOT NULL COMMENT 'When this rule version becomes effective',
    effective_until DATE NULL COMMENT 'When this rule version is superseded (null = current)',
    is_current BOOLEAN NOT NULL DEFAULT TRUE,

    -- Source tracking
    source_url VARCHAR(1000) NULL COMMENT 'Official source URL',
    interpretation_notes TEXT NULL COMMENT 'Implementation guidance and notes',

    -- Metadata
    created_by_user_id INT UNSIGNED NULL COMMENT 'User who created this version',

    -- Timestamps
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Foreign keys (soft reference - don't restrict deletion)
    FOREIGN KEY fk_regulatory_created_by (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,

    -- Unique constraints
    UNIQUE INDEX idx_rule_version (rule_code, version),

    -- Indexes
    INDEX idx_rule_code (rule_code),
    INDEX idx_regulation_type (regulation_type),
    INDEX idx_current_rules (rule_code, is_current),
    INDEX idx_effective_dates (effective_from, effective_until),
    INDEX idx_severity (severity_level),
    INDEX idx_type_severity (regulation_type, severity_level)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Versioned regulatory rules and EU AI Act requirements';