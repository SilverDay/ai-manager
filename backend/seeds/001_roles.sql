-- Seed 001: Role definitions
-- RBAC roles for AI governance platform

INSERT INTO roles (name, display_name, description, is_tenant_role, permissions) VALUES
(
    'Superadmin',
    'Platform Superadmin',
    'Full platform administration rights including tenant management and system configuration',
    FALSE,
    JSON_ARRAY(
        'platform.manage',
        'tenants.create',
        'tenants.read',
        'tenants.update',
        'tenants.delete',
        'users.impersonate',
        'settings.manage_global',
        'audit.platform_level',
        'regulatory.manage',
        'system.diagnostics'
    )
),
(
    'Admin',
    'Tenant Administrator',
    'Full administrative rights within their tenant including user management and tenant configuration',
    TRUE,
    JSON_ARRAY(
        'tenant.manage',
        'users.create',
        'users.read',
        'users.update',
        'users.delete',
        'departments.manage',
        'ai_systems.create',
        'ai_systems.read',
        'ai_systems.update',
        'ai_systems.delete',
        'classifications.manage',
        'assessments.manage',
        'controls.manage',
        'vendors.manage',
        'workflows.manage',
        'approvals.manage',
        'documents.manage',
        'training.manage',
        'settings.manage_tenant',
        'audit.tenant_level',
        'invitations.send',
        'notifications.send'
    )
),
(
    'AI Owner',
    'AI System Owner',
    'Responsible for specific AI systems including registration, classification, and assessment',
    TRUE,
    JSON_ARRAY(
        'ai_systems.create',
        'ai_systems.read',
        'ai_systems.update',
        'classifications.create',
        'classifications.read',
        'classifications.update',
        'assessments.create',
        'assessments.read',
        'assessments.update',
        'controls.create',
        'controls.read',
        'controls.update',
        'vendors.read',
        'documents.upload',
        'documents.read',
        'training.read',
        'training.complete',
        'notifications.read'
    )
),
(
    'Compliance Officer',
    'Compliance Officer',
    'Oversees regulatory compliance, reviews assessments, and manages compliance workflows',
    TRUE,
    JSON_ARRAY(
        'ai_systems.read',
        'classifications.read',
        'classifications.approve',
        'assessments.read',
        'assessments.approve',
        'controls.read',
        'controls.approve',
        'vendors.read',
        'vendors.assess',
        'workflows.read',
        'workflows.manage',
        'approvals.review',
        'documents.read',
        'training.read',
        'training.assign',
        'audit.read',
        'regulatory.read',
        'notifications.read',
        'notifications.send'
    )
),
(
    'Auditor',
    'Internal Auditor',
    'Read-only access for audit and compliance verification purposes',
    TRUE,
    JSON_ARRAY(
        'ai_systems.read',
        'classifications.read',
        'assessments.read',
        'controls.read',
        'vendors.read',
        'workflows.read',
        'approvals.read',
        'documents.read',
        'training.read',
        'audit.read',
        'regulatory.read',
        'notifications.read'
    )
),
(
    'Data Protection Officer',
    'Data Protection Officer',
    'Specialized role for privacy and data protection compliance oversight',
    TRUE,
    JSON_ARRAY(
        'ai_systems.read',
        'classifications.read',
        'assessments.read',
        'assessments.data_protection',
        'controls.read',
        'controls.privacy',
        'vendors.read',
        'vendors.privacy_assess',
        'documents.read',
        'training.read',
        'audit.privacy',
        'regulatory.privacy',
        'notifications.read'
    )
),
(
    'Technical Staff',
    'Technical Staff',
    'Technical team members who implement and maintain AI systems',
    TRUE,
    JSON_ARRAY(
        'ai_systems.read',
        'classifications.read',
        'assessments.read',
        'assessments.technical_input',
        'controls.read',
        'controls.implement',
        'vendors.read',
        'documents.read',
        'documents.upload',
        'training.read',
        'training.complete',
        'notifications.read'
    )
);