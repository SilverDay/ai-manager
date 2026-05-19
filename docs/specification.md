# AI Governance & EU AI Act Compliance Platform
## Software Specification v2.0
### Vue + LAMP Stack

---

# 1. Executive Summary

This document describes the specification for a web-based AI Governance and EU AI Act Compliance platform designed to help organisations manage Artificial Intelligence systems in accordance with the EU AI Act.

The application is intended to support:

- AI inventory management
- AI Act classification
- Risk assessments
- Approval workflows
- Fundamental Rights Impact Assessments (FRIA)
- AI literacy tracking
- Vendor and GPAI management
- Evidence collection
- Audit readiness
- Compliance reporting

The system is designed for both experts and non-experts and therefore focuses heavily on guided workflows, contextual help, explainability, and user assistance.

---

# 2. Regulatory Context

The platform is aligned with the EU AI Act.

Key obligations covered include:

- AI literacy obligations
- Prohibited AI practices
- High-risk AI systems
- Transparency obligations
- Provider obligations
- Deployer obligations
- Fundamental Rights Impact Assessments
- Human oversight requirements
- Logging and monitoring
- Technical documentation
- Cybersecurity and robustness requirements

The application does NOT provide legal advice or automatic legal certification.

Instead, it provides:

- structured governance workflows,
- compliance evidence management,
- risk assessments,
- approval processes,
- and audit support.

## 2.1 Regulatory Update Strategy

The EU AI Act is supplemented by delegated acts, implementing acts, and evolving guidance from the AI Office. The platform must accommodate regulatory evolution without requiring code changes for every update.

### Approach

Classification logic, prohibited practice definitions, high-risk system categories (Annex III), and obligation mappings shall be stored as configurable rule sets in the database, not hardcoded in application logic. The Admin Panel (Section 7.2) shall include a Regulatory Configuration module allowing authorised administrators to update:

- prohibited AI practice definitions,
- high-risk AI system categories,
- transparency obligation triggers,
- GPAI classification criteria,
- deployer and provider obligation mappings,
- article references and recital links.

All regulatory rule changes shall be versioned and audit-logged. When rules change, previously completed classifications shall retain their original rule version while flagging that a re-assessment against current rules is recommended.

---

# 3. Technology Stack

## Frontend

- Vue 3
- Vite
- Pinia
- Vue Router
- Tailwind CSS
- Axios
- vue-i18n (internationalisation support)

## Backend

- PHP 8.3+
- REST API architecture
- Apache 2.4
- Composer

## Database

- MariaDB 10.11+

## Infrastructure

- Ubuntu LTS
- HTTPS/TLS mandatory
- Cron workers for scheduled tasks
- Optional Docker support later

---

# 4. Core Design Principles

The application must:

- guide users through all compliance workflows,
- not assume prior AI Act knowledge,
- explain legal and technical terminology,
- provide contextual assistance,
- support auditability,
- provide strong security controls,
- support gradual maturity growth,
- support both SMEs and enterprises.

---

# 5. User Experience Requirements

## 5.1 Guided Workflow Philosophy

The system must behave like a guided governance assistant rather than a static database.

Every major workflow shall use:

- step-by-step wizards,
- progress indicators,
- contextual explanations,
- practical examples,
- recommendations,
- validation checks,
- and next-step guidance.

---

## 5.2 Contextual Help System

Every page must provide access to:

- inline help,
- tooltips,
- glossary terms,
- article references,
- examples,
- practical guidance,
- and "Why are we asking this?" explanations.

### Example

Question:

> Does the AI system influence decisions about individuals?

Help:

> This includes systems that support decisions about employees, customers, students, applicants, patients, or citizens.

Why this matters:

> AI systems influencing people may fall under stricter AI Act requirements.

---

## 5.3 Glossary System

The system shall include a built-in glossary explaining terms such as:

- AI system
- Provider
- Deployer
- GPAI
- High-risk AI
- Prohibited AI
- FRIA
- Human oversight
- Transparency obligations
- Technical documentation
- Residual risk
- Personal data
- Sensitive data
- Conformity assessment

The glossary must be searchable.

---

## 5.4 Beginner vs Expert Mode

Users should be able to choose:

### Beginner Mode

- simplified explanations
- guided questions
- recommendations
- examples

### Expert Mode

- condensed UI
- advanced fields
- legal references
- technical details

---

## 5.5 Internationalisation (i18n)

The platform shall be internationalisation-ready from Phase 1.

### Requirements

- All user-facing strings shall be externalised using vue-i18n on the frontend and a PHP translation layer on the backend (API error messages, email templates, notification texts).
- The default and Phase 1 language is English.
- German language support shall be added in Phase 2 to serve the DACH market.
- Admin-managed content (glossary entries, help texts, questionnaire text, AI Act references) shall support multi-language variants via a `locale` column in the respective database tables.
- Date, number, and currency formatting shall respect the active locale.
- The database schema shall use UTF-8mb4 encoding throughout.

---

# 6. User Roles

| Role | Scope | Description |
|---|---|---|
| Superadmin | Platform-wide | Platform-level administration: tenant lifecycle, global settings, regulatory rules, platform health (see Section 7.2.1) |
| Admin | Tenant | Full tenant administration: users, roles, workflows, content, monitoring |
| AI Owner | Tenant | Responsible for AI systems within the tenant |
| Risk Reviewer | Tenant | Reviews assessments |
| Compliance/Legal | Tenant | Reviews AI Act obligations |
| Security Reviewer | Tenant | Reviews cybersecurity aspects |
| DPO | Tenant | Reviews GDPR/privacy aspects |
| Business Approver | Tenant | Business-level approval |
| Auditor | Tenant | Read-only audit access |
| Employee | Tenant | Submit AI usage requests via the AI Request Form (Section 7.4.1) |

### Role Scope

Tenant-scoped roles exist within a single tenant and can only access data belonging to that tenant. The Superadmin role operates across all tenants and has access to the Platform Administration panel (Section 7.2.1). A Superadmin can impersonate a tenant context for support and troubleshooting purposes; all impersonation actions are audit-logged with the original Superadmin identity.

---

# 7. Main Functional Modules

# 7.1 Authentication & User Management

This module is part of Phase 1.

## Features

- User registration (domain-based and invite-based; see 7.1.1)
- User approval workflow (see 7.1.2)
- Login
- Logout
- Forgot password
- Password reset
- Change password
- Change email address
- Email verification
- Session management
- User activation/deactivation
- Failed login tracking
- Account lockout protection
- Password policy enforcement
- MFA-ready architecture
- User invitation management (see 7.1.3)

## Security Requirements

- Secure password hashing (bcrypt, cost factor ≥ 12)
- CSRF protection
- Rate limiting
- Secure session handling
- Audit logging

---

## 7.1.1 Tenant-Aware Registration

In a multi-tenant environment, the system must determine which tenant a new user belongs to. Two registration paths are supported.

### Path 1: Domain-Based Self-Registration

1. User navigates to the registration page and enters their email address.
2. The system extracts the email domain and looks it up in the Tenant Domain Registry (see below).
3. If the domain is mapped to a tenant, the user is associated with that tenant and proceeds with registration (name, password, etc.).
4. If the domain is not found in the registry, registration is rejected with the message: "Your email domain is not associated with a registered organisation. Please contact your organisation's administrator to request an invitation."
5. Upon successful registration, the user account is created in `pending_approval` status. The user receives an email verification link but cannot log in until approved (see 7.1.2).

### Path 2: Invite-Based Registration

1. A tenant Admin (or Superadmin) creates an invitation for a specific email address (see 7.1.3).
2. The invited user receives an email containing a single-use registration link with a signed token.
3. The token pre-associates the user with the correct tenant, bypassing domain lookup entirely. This supports external collaborators, contractors, and users with email domains not in the registry.
4. The user completes registration via the invite link (name, password).
5. Depending on tenant configuration, invited users may be auto-approved (skipping the approval step) or still require explicit approval. The default is auto-approve for invited users.

### Tenant Domain Registry

Each tenant can register one or more email domains. The domain registry is managed by the tenant Admin via the Tenant Admin Panel.

Rules:

- A domain can only be mapped to one tenant (unique constraint).
- Public/freemail domains (gmail.com, outlook.com, yahoo.com, etc.) are maintained on a global blocklist by the Superadmin and cannot be registered by any tenant.
- The Superadmin can manage the freemail blocklist via the Platform Administration panel.
- Domain verification is recommended but not enforced in Phase 1. Phase 2 may add DNS-based domain verification (TXT record).
- When a user changes their email address, the new domain is validated against the same tenant's domain registry. If it does not match, the change is rejected.

### Registration API Endpoints

```text
POST   /api/auth/register
       Request: { email, first_name, last_name, password }
       Behaviour: Domain lookup → tenant association → create user (pending_approval)
       Error: 422 if domain not in registry

POST   /api/auth/register/invite/{token}
       Request: { first_name, last_name, password }
       Behaviour: Token validation → tenant association → create user (approved or pending_approval per tenant config)
       Error: 410 if token expired or already used
```

---

## 7.1.2 User Approval Workflow

New user accounts created via self-registration are placed in `pending_approval` status and cannot log in until approved.

### Approval Flow

1. When a new user registers, the tenant Admin(s) receive an in-app and email notification: "New user registration pending approval."
2. The tenant Admin reviews the pending user in the Tenant Admin Panel under a dedicated "Pending Registrations" section.
3. The Admin can: Approve (account becomes active), Reject (account is marked rejected; user is notified by email with optional reason), or Request Verification (Admin can request additional verification before deciding).
4. All approval decisions are audit-logged.
5. Approved users receive an email confirming their account is active and can now log in.
6. Rejected users receive an email with the rejection reason and instructions to contact their administrator if they believe this is an error.

### Configurable Behaviour

The following settings are configurable per tenant via the Tenant Admin Panel:

- Auto-approve invited users: Yes (default) / No
- Auto-approve users from verified domains: No (default) / Yes (Phase 2, after DNS domain verification is implemented)
- Approval notification recipients: All tenant Admins (default) / Specific users

### Timeout

Pending registrations that are not acted upon within a configurable period (default: 14 days) are automatically expired. The user is notified and may re-register.

---

## 7.1.3 User Invitation Management

Tenant Admins can invite users to their tenant.

### Features

- Invite by email address (single or bulk via CSV upload)
- Assign initial role(s) to the invitation (applied upon registration)
- Assign department (optional)
- Set invitation expiry (default: 7 days, configurable)
- View pending invitations
- Resend invitation
- Revoke invitation
- Track invitation status: pending, accepted, expired, revoked

### Invitation API Endpoints

```text
GET    /api/invitations
POST   /api/invitations
       Request: { email, roles[], department_id?, expires_in_days? }
POST   /api/invitations/bulk
       Request: CSV upload (email, role, department)
DELETE /api/invitations/{id}
POST   /api/invitations/{id}/resend
```

### Security

- Invitation tokens are single-use, cryptographically signed (HMAC-SHA256), and time-limited.
- Expired or revoked tokens return HTTP 410 Gone.
- An invitation to an email address that already has an active account in the tenant is rejected.
- Invitations are audit-logged (created, accepted, expired, revoked).

---

# 7.2 Tenant Admin Panel

The tenant admin panel is included in Phase 1. It is accessible to users with the Admin role and operates within the scope of a single tenant.

## Features

### User Administration

- Manage users within the tenant
- Pending registrations: approve, reject, or request verification (Section 7.1.2)
- User invitations: create, view, resend, revoke (Section 7.1.3)
- Assign tenant-scoped roles
- Assign departments
- Activate/deactivate accounts

### Tenant Domain Management

- Add and remove email domains for the tenant (Section 7.1.1)
- View domain verification status (Phase 2: DNS-based verification)

### System Configuration

- Risk scoring configuration
- Approval workflow configuration (see Section 7.11 for workflow engine details)
- Review interval settings
- Email template management
- Document type configuration
- Regulatory rule management (see Section 2.1)

### Content Administration

- Help text management (with locale support)
- Glossary management (with locale support)
- AI Act references
- Questionnaire configuration (with locale support)

### Monitoring

- Audit log viewer (tenant-scoped)
- System health overview
- Activity dashboard

---

## 7.2.1 Platform Administration (Superadmin)

The platform administration panel is accessible exclusively to users with the Superadmin role. It provides cross-tenant management capabilities.

This module is part of Phase 1.

### Tenant Lifecycle Management

- Create new tenants (name, slug, subscription tier, initial Admin user)
- View and search all tenants
- Edit tenant metadata (name, subscription tier, settings)
- Activate/deactivate tenants (deactivation suspends all tenant users and blocks API access for the tenant; data is retained)
- Delete tenants (soft delete with configurable data retention period; hard delete requires explicit confirmation and is irreversible)

### Tenant Configuration

- Set default settings for new tenants (default review intervals, password policies, file size limits)
- Override tenant-level settings when required (e.g., enforce minimum password policy)
- Manage subscription tiers and feature flags per tier

### Global Regulatory Rule Management

- Manage regulatory rule versions (Section 2.1) — these are global and apply across all tenants
- Publish new rule versions with effective dates
- View rule version history

### Platform User Management

- Create and manage Superadmin accounts
- View user counts and activity across tenants (aggregated, not individual user data)
- Impersonate a tenant context for support purposes (all actions audit-logged with Superadmin identity)

### Freemail / Public Domain Blocklist

- Manage the global blocklist of public email domains (gmail.com, outlook.com, yahoo.com, etc.) that cannot be registered by any tenant
- Pre-seeded with common freemail providers on initial deployment
- All additions and removals are audit-logged

### Platform Monitoring

- Cross-tenant audit log viewer (filterable by tenant)
- Platform health dashboard (database size, active tenants, API request volume, error rates)
- Tenant usage statistics (AI systems registered, active users, storage consumption per tenant)

### Superadmin API Endpoints

```text
# Tenant Management
GET    /api/platform/tenants
POST   /api/platform/tenants
GET    /api/platform/tenants/{id}
PUT    /api/platform/tenants/{id}
PATCH  /api/platform/tenants/{id}/activate
PATCH  /api/platform/tenants/{id}/deactivate
DELETE /api/platform/tenants/{id}

# Freemail Blocklist
GET    /api/platform/freemail-blocklist
POST   /api/platform/freemail-blocklist
DELETE /api/platform/freemail-blocklist/{id}

# Platform Settings
GET    /api/platform/settings
PUT    /api/platform/settings

# Regulatory Rules (global)
GET    /api/platform/regulatory-rules
POST   /api/platform/regulatory-rules
GET    /api/platform/regulatory-rules/{id}

# Platform Monitoring
GET    /api/platform/audit-log
GET    /api/platform/health
GET    /api/platform/tenant-stats

# Impersonation
POST   /api/platform/impersonate/{tenantId}
POST   /api/platform/end-impersonation
```

### Security Considerations

- Superadmin authentication requires MFA (mandatory, not optional).
- All Superadmin actions are logged to a separate platform-level audit log that is not accessible to tenant Admins.
- Superadmin accounts cannot be created via the tenant Admin panel or self-registration; they must be provisioned by an existing Superadmin or via a CLI provisioning tool.
- The impersonation feature creates a scoped session that inherits the tenant Admin role within the target tenant. The impersonating Superadmin's identity is recorded on all audit log entries created during impersonation.

---

# 7.3 AI Inventory Module

Central registry of all AI systems.

## Fields

- Name
- Description
- Department
- Business owner
- Vendor/provider (foreign key to vendors table; basic vendor record required)
- Intended purpose
- Actual usage
- Personal data usage (boolean + description)
- Sensitive data usage (boolean + description)
- GPAI usage (boolean + GPAI model reference)
- AI category
- Deployment status
- Review dates (next review, last review)
- Supporting documents (linked via documents table)

---

# 7.4 AI Registration Wizard

Guided workflow for registering AI systems.

The Registration Wizard is the formal process for adding an AI system to the inventory. It is initiated either directly by an AI Owner or as the result of an approved AI Usage Request (see 7.4.1).

## Steps

1. Basic information
2. Determine whether system is AI
3. Determine organisational role
4. Personal data assessment
5. High-risk screening
6. Transparency screening
7. Initial risk evaluation
8. Upload evidence
9. Submit for review

At the end, the system shall display:

- classification result,
- required actions,
- required evidence,
- pending approvals,
- next review date.

---

## 7.4.1 AI Usage Request Form

The AI Usage Request Form is a lightweight intake mechanism for employees (role: Employee) who wish to use or introduce an AI system.

### Purpose

Employees may not have the knowledge to complete a full AI registration. The request form captures essential information and routes it to the appropriate AI Owner for triage.

### Fields

- Requester name (auto-populated)
- Department (auto-populated)
- AI system or tool name
- Vendor/provider (if known)
- Intended use case (free text)
- Does the system process personal data? (Yes / No / Not sure)
- Business justification
- Urgency (Low / Medium / High)
- Supporting documents (optional upload)

### Workflow

1. Employee submits request.
2. Request is routed to the designated AI Owner for the employee's department (or a default AI Owner if none is configured).
3. AI Owner reviews and decides: Approve (proceed to Registration Wizard), Reject (with reason), or Request More Information.
4. All decisions are audit-logged.
5. Upon approval, the AI Owner initiates the Registration Wizard (Section 7.4) with pre-populated fields from the request.

---

# 7.5 AI Act Classification Wizard

Guided questionnaire for determining:

- prohibited AI risk,
- high-risk classification,
- transparency obligations,
- GPAI involvement,
- deployer obligations,
- provider obligations.

The wizard shall provide:

- explanations,
- examples,
- references,
- confidence indicators.

Classification results shall reference the regulatory rule version used (see Section 2.1).

---

# 7.6 Risk Assessment Module

## Risk Categories

- Fundamental rights
- Privacy
- Security
- Bias/discrimination
- Safety
- Operational dependency
- Explainability
- Human oversight
- Vendor dependency
- Model drift
- Accuracy
- Reputational risk

## Risk Scoring

Risk = Likelihood × Impact

Residual Risk = Inherent Risk - Control Effectiveness

---

# 7.7 Control & Compliance Checklist

The system shall track:

- documentation completeness,
- logging capability,
- cybersecurity measures,
- human oversight,
- transparency controls,
- incident response,
- monitoring activities,
- vendor documentation,
- FRIA requirements,
- DPIA requirements.

---

# 7.8 FRIA Module

Fundamental Rights Impact Assessment module.

### Phase 1 Scope (Basic FRIA)

Phase 1 includes a basic FRIA capability as part of the risk assessment workflow:

- simplified affected groups identification (guided checklist),
- fundamental rights risk scoring (using the risk assessment framework from Section 7.6),
- basic mitigation documentation,
- linkage to the approval workflow.

### Phase 2 Scope (Advanced FRIA)

Phase 2 extends this to full standalone FRIA workflows:

- detailed affected groups analysis with demographic and contextual factors,
- structured fundamental rights risk identification per EU Charter category,
- mitigation plan tracking with status and owner assignment,
- human oversight documentation,
- residual risk evaluation after mitigations,
- dedicated FRIA approval workflow (separate from general approval),
- FRIA versioning and periodic reassessment triggers.

---

# 7.9 AI Literacy Module

Track employee AI literacy obligations.

This module is part of Phase 2.

## Features

- Training assignments
- Completion tracking
- Evidence upload
- Expiration tracking
- Role-based requirements

---

# 7.10 Vendor & GPAI Module

Manage external AI providers and GPAI usage.

### Phase 1 Scope (Basic Vendor Registry)

Phase 1 includes a basic vendor registry to support the AI Inventory foreign key relationship:

- vendor name, contact, website,
- vendor type (AI provider, GPAI provider, integrator, other),
- contract reference (free text or document link),
- basic risk rating (Low / Medium / High / Not Assessed),
- linkage to AI systems in the inventory,
- data processing agreement flag (Yes / No / N/A).

### Phase 2 Scope (Advanced Vendor & GPAI Management)

Phase 2 extends this to full vendor and GPAI lifecycle management:

- detailed vendor assessment questionnaires,
- GPAI model registry with technical details,
- contract lifecycle tracking with expiration alerts,
- structured security assessment workflows,
- documentation completeness tracking,
- data processing agreement management,
- exit strategy documentation,
- automated reassessment scheduling.

---

# 7.11 Approval Workflow Engine

Configurable workflow engine supporting admin-defined approval sequences.

### Workflow Configuration

Approval workflows are defined in the Admin Panel (Section 7.2) and stored as ordered sequences of approval steps. Each step specifies:

- step name,
- required role(s),
- whether the step is mandatory or conditional,
- conditions under which the step is activated (e.g., "only if high-risk classification"),
- timeout/escalation period,
- delegate assignment (who acts if the assigned reviewer is unavailable).

### Default Workflow Template

The system ships with a default workflow that administrators can modify:

Employee Request
→ AI Owner Review
→ Security Review
→ DPO Review
→ Compliance Review
→ Business Approval
→ Final Approval

Administrators may add, remove, reorder, or conditionalise steps. Custom workflows can be created for different AI risk categories (e.g., a shortened workflow for minimal-risk systems).

### Workflow Behaviour

- Steps execute sequentially; parallel steps are not supported in Phase 1.
- Each step records: approver, decision (Approve / Reject / Request Changes), comments, timestamp.
- Rejection at any step returns the submission to the initiator with the reviewer's comments.
- All workflow actions are audit-logged.

---

# 7.12 Evidence & Document Management

## Features

- File uploads
- Version tracking
- Expiration reminders
- Document categorisation
- Access control (RBAC-based; documents inherit visibility from the linked AI system, vendor, or assessment)
- Audit trail

## File Storage

- Files shall be stored on the local filesystem under a dedicated, non-web-accessible directory (e.g., `/var/app/storage/documents/`).
- Files shall be organised by tenant and entity type: `/{tenant_id}/ai-systems/{system_id}/`, `/{tenant_id}/vendors/{vendor_id}/`, etc.
- Files shall be encrypted at rest using AES-256 (via filesystem-level encryption or application-level encryption wrapper).
- File access shall be mediated exclusively through the API with RBAC checks; direct filesystem access is not permitted.
- Allowed file types shall be configurable via the Admin Panel. Default: PDF, DOCX, XLSX, PNG, JPG, ZIP.
- Maximum file size: 50 MB (configurable).
- Phase 2 consideration: migration to S3-compatible object storage for scalability.

## Supported Examples

- DPIAs
- FRIA documents
- Security assessments
- Vendor contracts
- Technical documentation
- AI training evidence

---

# 7.13 Dashboard & Reporting

## Dashboard Widgets

### Phase 1 Widgets

- Total AI systems
- High-risk systems
- Pending approvals
- Overdue reviews
- Open risks
- Missing evidence

### Phase 2 Widgets (added when corresponding modules are complete)

- GPAI systems (requires Phase 2 GPAI model registry)
- Training status (requires Phase 2 AI Literacy module)

## Reports

- AI inventory
- High-risk overview
- Vendor overview
- Outstanding actions
- Residual risk heatmap
- AI literacy status (Phase 2)
- Audit export

Export formats:

- PDF
- CSV
- XLSX

---

# 8. Database Model

## 8.1 Multi-Tenancy Architecture

The platform uses a shared-database, shared-schema multi-tenancy model with row-level tenant isolation.

### Design

- A `tenants` table stores tenant metadata (organisation name, subscription tier, settings).
- Every tenant-scoped table includes a `tenant_id` foreign key column.
- All queries against tenant-scoped data must include a `WHERE tenant_id = ?` clause, enforced at the repository/data access layer.
- A middleware component shall extract the tenant context from the authenticated user's session and inject it into all database queries.
- Tenant-scoped tables: `users`, `departments`, `ai_systems`, `ai_classifications`, `risk_assessments`, `risk_answers`, `controls`, `vendors`, `vendor_assessments`, `fria_assessments`, `documents`, `approvals`, `approval_steps`, `audit_log`, `training_courses`, `training_records`, `notifications`, `ai_requests`, `workflow_definitions`, `workflow_steps`, `glossary_entries`, `help_texts`, `questionnaire_configs`.
- Global tables (not tenant-scoped): `tenants`, `roles`, `settings`, `regulatory_rules`, `regulatory_rule_versions`.

### Tenant Isolation Verification

- Automated tests shall verify that no API endpoint returns data from another tenant.
- Database views or stored procedures may be used as an additional isolation layer if required.

---

## 8.2 Core Table Definitions

All tables use InnoDB engine, UTF-8mb4 character set, and include `created_at` / `updated_at` timestamps unless otherwise noted.

```sql
-- Tenant management
CREATE TABLE tenants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    subscription_tier ENUM('free','standard','enterprise') NOT NULL DEFAULT 'standard',
    settings JSON,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Users and roles
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    department_id INT UNSIGNED,
    is_superadmin TINYINT(1) NOT NULL DEFAULT 0,
    account_status ENUM('pending_approval','active','rejected','deactivated') NOT NULL DEFAULT 'pending_approval',
    ui_mode ENUM('beginner','expert') NOT NULL DEFAULT 'beginner',
    locale VARCHAR(10) NOT NULL DEFAULT 'en',
    email_verified_at DATETIME,
    last_login_at DATETIME,
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME,
    mfa_secret VARCHAR(255),
    mfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
    approved_by INT UNSIGNED,
    approved_at DATETIME,
    rejection_reason TEXT,
    invitation_id INT UNSIGNED,
    registration_expires_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email (email),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (invitation_id) REFERENCES user_invitations(id)
);
-- Note: tenant_id is NULL for Superadmin users (platform-level).
-- Superadmin users have is_superadmin=1 and mfa_enabled must be 1 (enforced at application layer).
-- Tenant-scoped users must have a non-NULL tenant_id (enforced at application layer).
-- account_status replaces the former is_active boolean to support the approval workflow.
-- registration_expires_at is set to NOW + 14 days (configurable) for pending_approval accounts.
-- A cron job expires pending accounts past registration_expires_at by setting status to 'rejected'.

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255)
);

CREATE TABLE user_roles (
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    default_ai_owner_id INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_dept (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (default_ai_owner_id) REFERENCES users(id)
);

-- Tenant Domain Registry (Section 7.1.1)
CREATE TABLE tenant_domains (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    domain VARCHAR(255) NOT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME,
    added_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_domain (domain),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Freemail / Public Domain Blocklist (global, managed by Superadmin)
CREATE TABLE freemail_blocklist (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain VARCHAR(255) NOT NULL UNIQUE,
    added_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- User Invitations (Section 7.1.3)
CREATE TABLE user_invitations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    roles JSON,
    department_id INT UNSIGNED,
    auto_approve TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('pending','accepted','expired','revoked') NOT NULL DEFAULT 'pending',
    invited_by INT UNSIGNED NOT NULL,
    accepted_at DATETIME,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_email_pending (tenant_id, email, status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (invited_by) REFERENCES users(id)
);

-- AI Inventory
CREATE TABLE ai_systems (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    department_id INT UNSIGNED,
    owner_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED,
    intended_purpose TEXT,
    actual_usage TEXT,
    uses_personal_data TINYINT(1) NOT NULL DEFAULT 0,
    personal_data_description TEXT,
    uses_sensitive_data TINYINT(1) NOT NULL DEFAULT 0,
    sensitive_data_description TEXT,
    uses_gpai TINYINT(1) NOT NULL DEFAULT 0,
    gpai_model_reference VARCHAR(255),
    ai_category ENUM('prohibited','high_risk','limited_risk','minimal_risk','not_classified') NOT NULL DEFAULT 'not_classified',
    deployment_status ENUM('planned','pilot','production','decommissioned') NOT NULL DEFAULT 'planned',
    last_review_date DATE,
    next_review_date DATE,
    request_id INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (owner_id) REFERENCES users(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id),
    FOREIGN KEY (request_id) REFERENCES ai_requests(id)
);

-- AI Usage Requests (Section 7.4.1)
CREATE TABLE ai_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    requester_id INT UNSIGNED NOT NULL,
    department_id INT UNSIGNED NOT NULL,
    system_name VARCHAR(255) NOT NULL,
    vendor_name VARCHAR(255),
    intended_use TEXT NOT NULL,
    uses_personal_data ENUM('yes','no','unsure') NOT NULL DEFAULT 'unsure',
    business_justification TEXT,
    urgency ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    status ENUM('submitted','under_review','approved','rejected','info_requested') NOT NULL DEFAULT 'submitted',
    assigned_owner_id INT UNSIGNED,
    decision_comment TEXT,
    decided_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (requester_id) REFERENCES users(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (assigned_owner_id) REFERENCES users(id)
);

-- Classifications (versioned, 1:n with ai_systems)
CREATE TABLE ai_classifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    ai_system_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    regulatory_rule_version_id INT UNSIGNED,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    prohibited_risk TINYINT(1) NOT NULL DEFAULT 0,
    high_risk TINYINT(1) NOT NULL DEFAULT 0,
    transparency_obligations TINYINT(1) NOT NULL DEFAULT 0,
    gpai_involvement TINYINT(1) NOT NULL DEFAULT 0,
    deployer_obligations JSON,
    provider_obligations JSON,
    confidence_level ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
    wizard_responses JSON NOT NULL,
    classified_by INT UNSIGNED NOT NULL,
    classified_at DATETIME NOT NULL,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_system_version (ai_system_id, version),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (ai_system_id) REFERENCES ai_systems(id) ON DELETE CASCADE,
    FOREIGN KEY (classified_by) REFERENCES users(id),
    FOREIGN KEY (regulatory_rule_version_id) REFERENCES regulatory_rule_versions(id)
);

-- Risk Assessments (versioned, 1:n with ai_systems)
CREATE TABLE risk_assessments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    ai_system_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('draft','in_review','approved','rejected') NOT NULL DEFAULT 'draft',
    overall_inherent_risk DECIMAL(5,2),
    overall_residual_risk DECIMAL(5,2),
    assessed_by INT UNSIGNED NOT NULL,
    assessed_at DATETIME NOT NULL,
    approved_by INT UNSIGNED,
    approved_at DATETIME,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_system_ra_version (ai_system_id, version),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (ai_system_id) REFERENCES ai_systems(id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

CREATE TABLE risk_answers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    risk_assessment_id INT UNSIGNED NOT NULL,
    category ENUM('fundamental_rights','privacy','security','bias','safety','operational_dependency','explainability','human_oversight','vendor_dependency','model_drift','accuracy','reputational') NOT NULL,
    likelihood INT UNSIGNED NOT NULL CHECK (likelihood BETWEEN 1 AND 5),
    impact INT UNSIGNED NOT NULL CHECK (impact BETWEEN 1 AND 5),
    inherent_risk DECIMAL(5,2) GENERATED ALWAYS AS (likelihood * impact) STORED,
    control_effectiveness INT UNSIGNED CHECK (control_effectiveness BETWEEN 0 AND 100),
    residual_risk DECIMAL(5,2),
    justification TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (risk_assessment_id) REFERENCES risk_assessments(id) ON DELETE CASCADE
);

-- Controls
CREATE TABLE controls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    ai_system_id INT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('not_started','in_progress','implemented','not_applicable') NOT NULL DEFAULT 'not_started',
    evidence_document_id INT UNSIGNED,
    owner_id INT UNSIGNED,
    due_date DATE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (ai_system_id) REFERENCES ai_systems(id) ON DELETE CASCADE,
    FOREIGN KEY (evidence_document_id) REFERENCES documents(id),
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- Vendors (Phase 1: basic registry; Phase 2: full lifecycle)
CREATE TABLE vendors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    vendor_type ENUM('ai_provider','gpai_provider','integrator','other') NOT NULL DEFAULT 'other',
    website VARCHAR(500),
    contact_name VARCHAR(255),
    contact_email VARCHAR(255),
    contract_reference TEXT,
    risk_rating ENUM('low','medium','high','not_assessed') NOT NULL DEFAULT 'not_assessed',
    dpa_status ENUM('yes','no','na') NOT NULL DEFAULT 'na',
    notes TEXT,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_vendor (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Vendor Assessments (Phase 2)
CREATE TABLE vendor_assessments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    vendor_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    assessment_data JSON,
    risk_rating ENUM('low','medium','high','critical') NOT NULL,
    assessed_by INT UNSIGNED NOT NULL,
    assessed_at DATETIME NOT NULL,
    next_assessment_date DATE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(id)
);

-- FRIA Assessments (versioned)
CREATE TABLE fria_assessments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    ai_system_id INT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    scope ENUM('basic','advanced') NOT NULL DEFAULT 'basic',
    affected_groups JSON,
    rights_risks JSON,
    mitigations JSON,
    human_oversight_documentation TEXT,
    residual_risk_evaluation TEXT,
    status ENUM('draft','in_review','approved','rejected') NOT NULL DEFAULT 'draft',
    assessed_by INT UNSIGNED NOT NULL,
    assessed_at DATETIME NOT NULL,
    approved_by INT UNSIGNED,
    approved_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_system_fria_version (ai_system_id, version),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (ai_system_id) REFERENCES ai_systems(id) ON DELETE CASCADE,
    FOREIGN KEY (assessed_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Documents & Evidence
CREATE TABLE documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    entity_type ENUM('ai_system','vendor','risk_assessment','fria','training','ai_request','general') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    category VARCHAR(100) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    expires_at DATE,
    uploaded_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Approval Workflows
CREATE TABLE workflow_definitions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    applies_to ENUM('ai_registration','ai_request','fria','vendor_assessment','general') NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE workflow_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_definition_id INT UNSIGNED NOT NULL,
    step_order INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    required_role_id INT UNSIGNED NOT NULL,
    is_mandatory TINYINT(1) NOT NULL DEFAULT 1,
    activation_condition JSON,
    timeout_hours INT UNSIGNED,
    delegate_user_id INT UNSIGNED,
    UNIQUE KEY uq_workflow_order (workflow_definition_id, step_order),
    FOREIGN KEY (workflow_definition_id) REFERENCES workflow_definitions(id) ON DELETE CASCADE,
    FOREIGN KEY (required_role_id) REFERENCES roles(id),
    FOREIGN KEY (delegate_user_id) REFERENCES users(id)
);

CREATE TABLE approvals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    workflow_definition_id INT UNSIGNED NOT NULL,
    entity_type ENUM('ai_system','ai_request','fria','vendor_assessment') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    current_step_id INT UNSIGNED,
    initiated_by INT UNSIGNED NOT NULL,
    initiated_at DATETIME NOT NULL,
    completed_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (workflow_definition_id) REFERENCES workflow_definitions(id),
    FOREIGN KEY (current_step_id) REFERENCES workflow_steps(id),
    FOREIGN KEY (initiated_by) REFERENCES users(id)
);

CREATE TABLE approval_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    approval_id INT UNSIGNED NOT NULL,
    workflow_step_id INT UNSIGNED NOT NULL,
    reviewer_id INT UNSIGNED,
    decision ENUM('pending','approved','rejected','request_changes','skipped') NOT NULL DEFAULT 'pending',
    comments TEXT,
    decided_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approval_id) REFERENCES approvals(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_step_id) REFERENCES workflow_steps(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id)
);

-- Audit Log (append-only)
CREATE TABLE audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED,
    user_id INT UNSIGNED,
    impersonated_by INT UNSIGNED,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id INT UNSIGNED,
    previous_value JSON,
    new_value JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_entity (tenant_id, entity_type, entity_id),
    INDEX idx_tenant_user (tenant_id, user_id),
    INDEX idx_created (created_at),
    INDEX idx_impersonation (impersonated_by),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (impersonated_by) REFERENCES users(id)
);
-- Note: tenant_id is NULL for platform-level Superadmin actions (tenant creation, global settings, etc.).
-- impersonated_by records the Superadmin user_id when actions are performed during tenant impersonation.

-- Training & AI Literacy (Phase 2)
CREATE TABLE training_courses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    required_for_roles JSON,
    validity_months INT UNSIGNED,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE TABLE training_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    course_id INT UNSIGNED NOT NULL,
    status ENUM('assigned','in_progress','completed','expired') NOT NULL DEFAULT 'assigned',
    completed_at DATETIME,
    expires_at DATE,
    evidence_document_id INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (course_id) REFERENCES training_courses(id),
    FOREIGN KEY (evidence_document_id) REFERENCES documents(id)
);

-- Notifications
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    entity_type VARCHAR(100),
    entity_id INT UNSIGNED,
    channel ENUM('in_app','email','both') NOT NULL DEFAULT 'both',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    read_at DATETIME,
    email_sent_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (user_id, is_read),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Settings
CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope ENUM('global','tenant') NOT NULL DEFAULT 'global',
    tenant_id INT UNSIGNED,
    setting_key VARCHAR(255) NOT NULL,
    setting_value JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_scope_tenant_key (scope, tenant_id, setting_key),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Regulatory Rules (global, not tenant-scoped)
CREATE TABLE regulatory_rule_versions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version_label VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    effective_from DATE NOT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 0,
    rules_data JSON NOT NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Glossary (tenant-scoped, locale-aware)
CREATE TABLE glossary_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    term VARCHAR(255) NOT NULL,
    locale VARCHAR(10) NOT NULL DEFAULT 'en',
    definition TEXT NOT NULL,
    ai_act_reference VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_term_locale (tenant_id, term, locale),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

## 8.3 Versioning Strategy

Classifications, risk assessments, and FRIA assessments use an explicit versioning model:

- Each new assessment creates a new row with an incremented `version` number.
- The `is_current` flag is set to `1` on the latest version and `0` on all previous versions (updated atomically in a transaction).
- Historical versions are never deleted and remain available for audit queries.
- The query "What was the classification on date X?" is answered by selecting the version where `classified_at <= X` with the highest version number.

This approach avoids the need to reconstruct state from audit log entries while keeping the audit log available for fine-grained change tracking.

---

# 9. API Design

## 9.1 Authentication Mechanism

The API uses stateless JWT bearer token authentication.

### Token Flow

1. Client sends credentials to `POST /api/auth/login`.
2. Server validates credentials and returns an access token (short-lived, 15 minutes) and a refresh token (long-lived, 7 days, stored as HttpOnly secure cookie).
3. Client includes the access token in the `Authorization: Bearer {token}` header for all subsequent requests.
4. When the access token expires, the client calls `POST /api/auth/refresh` with the refresh token cookie to obtain a new access token.
5. On logout, the server invalidates the refresh token.

### Token Payload

The JWT access token contains: `user_id`, `tenant_id`, `roles[]`, `exp`, `iat`. It does NOT contain sensitive PII.

### MFA Integration (MFA-Ready)

When MFA is enabled for a user, the login flow adds an intermediate step:
1. `POST /api/auth/login` returns a partial token (status `mfa_required`) and a challenge.
2. Client sends the TOTP code to `POST /api/auth/mfa/verify`.
3. Server validates and returns the full access + refresh token pair.

### Phase 2: SSO/OIDC

In Phase 2, the authentication layer shall be extended to support SSO via SAML 2.0 and OpenID Connect. The JWT-based internal session model remains the same; the external identity provider replaces the username/password step.

### Session and Token Revocation

Stateless JWTs cannot be individually revoked once issued. The platform uses a layered approach to handle forced invalidation scenarios (user deactivation, tenant deactivation, password change, security incident).

**Refresh token revocation (immediate).** Refresh tokens are stored server-side in a `refresh_tokens` table. Revoking a refresh token prevents the user from obtaining new access tokens. Refresh tokens are revoked when:

- An Admin deactivates or rejects a user account.
- A Superadmin deactivates a tenant (all refresh tokens for the tenant are bulk-revoked).
- The user changes their password.
- The user logs out.
- A Superadmin triggers a forced session termination via the Platform Administration panel.

**Access token short-lived expiry (bounded delay).** Access tokens have a 15-minute lifetime. After refresh token revocation, the user retains access for at most 15 minutes until their current access token expires and the refresh attempt fails. This is an acceptable trade-off for stateless JWT performance.

**Token blocklist for critical events (immediate, optional).** For critical security events (confirmed account compromise, emergency tenant lockdown), an in-memory token blocklist (e.g., Redis or application-level cache) can optionally reject specific access tokens before their natural expiry. The blocklist is checked in the authentication middleware. Entries expire automatically after the token's original TTL. This mechanism is optional in Phase 1 and recommended for Phase 2.

**Database table:**

```sql
CREATE TABLE refresh_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED,
    token_hash VARCHAR(255) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME,
    revoked_reason VARCHAR(100),
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_tenant (tenant_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

---

## 9.2 Standard API Response Envelope

All API responses use a consistent JSON envelope:

```json
{
    "success": true,
    "data": { },
    "errors": [],
    "meta": {
        "page": 1,
        "per_page": 25,
        "total": 142,
        "total_pages": 6
    }
}
```

### Success Response

- `success`: `true`
- `data`: the response payload (object or array)
- `errors`: empty array
- `meta`: pagination metadata (for list endpoints), request ID, or other contextual data

### Error Response

- `success`: `false`
- `data`: `null`
- `errors`: array of error objects, each with `code`, `field` (if applicable), `message`
- HTTP status codes: 400 (validation), 401 (unauthenticated), 403 (unauthorised), 404 (not found), 409 (conflict), 422 (unprocessable entity), 429 (rate limited), 500 (server error)

### Example Error

```json
{
    "success": false,
    "data": null,
    "errors": [
        {
            "code": "VALIDATION_ERROR",
            "field": "intended_purpose",
            "message": "Intended purpose is required."
        }
    ],
    "meta": {
        "request_id": "req_abc123"
    }
}
```

---

## 9.3 REST Endpoints

```text
# Authentication
POST   /api/auth/login
POST   /api/auth/logout
POST   /api/auth/refresh
POST   /api/auth/register
POST   /api/auth/register/invite/{token}
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
POST   /api/auth/mfa/verify

# Users
GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
PATCH  /api/users/{id}/activate
PATCH  /api/users/{id}/deactivate

# User Approval (Section 7.1.2)
GET    /api/users/pending
PATCH  /api/users/{id}/approve
PATCH  /api/users/{id}/reject

# Invitations (Section 7.1.3)
GET    /api/invitations
POST   /api/invitations
POST   /api/invitations/bulk
DELETE /api/invitations/{id}
POST   /api/invitations/{id}/resend

# Tenant Domains (Section 7.1.1)
GET    /api/tenant-domains
POST   /api/tenant-domains
DELETE /api/tenant-domains/{id}

# AI Requests (Section 7.4.1)
GET    /api/ai-requests
POST   /api/ai-requests
GET    /api/ai-requests/{id}
PATCH  /api/ai-requests/{id}/decision

# AI Systems
GET    /api/ai-systems
POST   /api/ai-systems
GET    /api/ai-systems/{id}
PUT    /api/ai-systems/{id}

# Classifications (versioned)
GET    /api/ai-systems/{id}/classifications
POST   /api/ai-systems/{id}/classifications
GET    /api/ai-systems/{id}/classifications/{version}

# Risk Assessments (versioned)
GET    /api/ai-systems/{id}/risk-assessments
POST   /api/ai-systems/{id}/risk-assessments
GET    /api/ai-systems/{id}/risk-assessments/{version}

# FRIA (versioned)
GET    /api/ai-systems/{id}/fria
POST   /api/ai-systems/{id}/fria
GET    /api/ai-systems/{id}/fria/{version}

# Controls
GET    /api/ai-systems/{id}/controls
POST   /api/ai-systems/{id}/controls
PUT    /api/ai-systems/{id}/controls/{controlId}

# Vendors
GET    /api/vendors
POST   /api/vendors
GET    /api/vendors/{id}
PUT    /api/vendors/{id}

# Documents
GET    /api/documents?entity_type={type}&entity_id={id}
POST   /api/documents
GET    /api/documents/{id}/download
DELETE /api/documents/{id}

# Approvals
GET    /api/approvals
GET    /api/approvals/{id}
POST   /api/approvals/{id}/steps/{stepId}/decision

# Notifications
GET    /api/notifications
PATCH  /api/notifications/{id}/read
PATCH  /api/notifications/read-all

# Dashboard & Reports
GET    /api/dashboard/widgets
GET    /api/reports/ai-inventory
GET    /api/reports/high-risk
GET    /api/reports/vendor-overview
GET    /api/reports/outstanding-actions
GET    /api/reports/risk-heatmap
GET    /api/reports/export?format={pdf|csv|xlsx}&report={name}

# Admin
GET    /api/admin/audit-log
GET    /api/admin/settings
PUT    /api/admin/settings
GET    /api/admin/workflow-definitions
POST   /api/admin/workflow-definitions
PUT    /api/admin/workflow-definitions/{id}

# Glossary
GET    /api/glossary?locale={locale}&search={term}
```

---

# 10. Security Requirements

The application must implement:

- HTTPS only
- Secure cookies (Secure, HttpOnly, SameSite=Strict)
- CSRF protection (for any cookie-based state; JWT bearer tokens are inherently CSRF-resistant but the refresh token cookie requires protection)
- RBAC authorisation enforced at the API middleware layer
- Input validation on all endpoints (whitelist approach)
- Output encoding (JSON responses, HTML escaping where applicable)
- Prepared SQL statements (no dynamic query construction)
- File upload restrictions (type whitelist, size limit, virus scanning recommended)
- Audit logging of all material actions
- Rate limiting (configurable per endpoint category: auth endpoints stricter than data endpoints)
- Backup capability
- Content-Security-Policy, X-Content-Type-Options, X-Frame-Options, Referrer-Policy headers
- Dependency vulnerability scanning (Composer audit, npm audit) integrated into CI

---

# 11. Audit Logging

Every material action must be logged.

## Logged Data

- User
- Timestamp
- Action
- Object type
- Object ID
- Previous value (JSON)
- New value (JSON)
- IP address
- User agent

Audit logs must be tamper-resistant. The `audit_log` table is append-only: no UPDATE or DELETE operations are permitted at the application layer. Database-level permissions should restrict the application user to INSERT and SELECT only on this table.

---

# 12. Notifications & Reminders

## Notification Types

- Approval required
- Review overdue
- Missing evidence
- Password reset
- Training expiration (Phase 2)
- Risk escalation
- Vendor reassessment required
- AI request submitted (to AI Owner)
- AI request decision (to Employee)

## Channels

- Email (via queue)
- In-app notifications (via database polling)

## Delivery Architecture

Notifications are created as records in the `notifications` table with `channel` indicating the delivery method. A cron-based queue worker (running every 1–5 minutes) processes unsent email notifications using a configurable SMTP transport. In-app notifications are fetched by the Vue frontend via polling (`GET /api/notifications` every 30 seconds) or, in Phase 2, via Server-Sent Events (SSE) for real-time delivery.

Email templates are managed via the Admin Panel and support locale-specific variants.

---

# 13. Search & Filtering

Global search functionality shall support:

- AI systems
- Vendors
- Risks
- Documents
- Users
- Audit logs

Advanced filtering:

- risk class,
- department,
- owner,
- review date,
- vendor,
- approval status.

---

# 14. Accessibility Requirements

The system should follow WCAG 2.1 Level AA principles.

Requirements:

- keyboard navigation,
- screen reader compatibility,
- accessible forms (labels, ARIA attributes, error announcements),
- contrast compliance (minimum 4.5:1 for normal text),
- responsive design (mobile-friendly down to 375px viewport width).

---

# 15. Data Retention and Deletion Policy

The platform operates within the EU and must comply with GDPR requirements regarding data retention and the right to erasure (Article 17).

## Retention Periods

| Data Category | Retention Period | Rationale |
|---|---|---|
| Audit log entries | Minimum 5 years from creation, configurable per tenant | Regulatory evidence; AI Act requires traceability of decisions |
| AI system records (including classifications, risk assessments, FRIAs) | Retained for the lifetime of the AI system + 10 years after decommissioning | AI Act Article 18 (deployer record-keeping); evidence for post-market monitoring |
| Documents and evidence | Retained until manually deleted or document expiry + configurable grace period (default: 1 year) | Compliance evidence lifecycle |
| User accounts | Active accounts retained indefinitely; deactivated accounts retained for 90 days before anonymisation eligibility | Operational need vs. data minimisation |
| Refresh tokens | Automatically purged 7 days after expiry or revocation | No retention value beyond session lifecycle |
| Notifications | 1 year after creation, then automatically purged | Operational convenience only |
| Tenant data (after tenant deletion) | Configurable retention period (default: 90 days soft delete) before hard deletion eligibility | Grace period for reactivation; contractual obligations |

Retention periods are configurable by the Superadmin at the platform level and may be overridden per tenant where the tenant requires longer retention (but never shorter than the platform minimum).

## User Deletion and Right to Erasure

When a user requests account deletion or an Admin removes a user, the following process applies:

1. **Personal data anonymisation.** The user's `first_name`, `last_name`, and `email` are replaced with anonymised placeholders (e.g., `Deleted User`, `deleted_{user_id}@anonymised.local`). The `password_hash` is cleared. The `account_status` is set to `deleted`.
2. **Audit log preservation.** Audit log entries created by the user are NOT deleted (regulatory requirement). The `user_id` foreign key is retained for referential integrity, but the linked user record is anonymised, so no personal data is exposed.
3. **Ownership reassignment.** AI systems, risk assessments, and other records where the user is `owner_id`, `assessed_by`, or `classified_by` retain the foreign key reference to the anonymised user record. The tenant Admin is notified to reassign ownership where operationally necessary.
4. **Documents.** Documents uploaded by the user are retained (they belong to the entity, not the user). The `uploaded_by` reference points to the anonymised record.
5. **Invitations and training records.** Linked records are retained with the anonymised user reference.
6. **Refresh tokens.** All active refresh tokens are revoked and the records purged.

This approach satisfies GDPR right to erasure while preserving the audit trail integrity required by the AI Act.

## Automated Retention Enforcement

A cron job (daily) shall:

- Purge expired refresh tokens.
- Purge notifications older than the configured retention period.
- Flag deactivated user accounts that have exceeded the anonymisation grace period for review or automatic anonymisation (configurable: manual review vs. automatic).
- Flag soft-deleted tenants that have exceeded the retention period for hard deletion review.

---

# 16. Backup and Disaster Recovery

## Backup Strategy

| Component | Method | Frequency | Retention |
|---|---|---|---|
| MariaDB database | Automated logical dump (mysqldump) + binary log shipping for point-in-time recovery | Full daily; incremental binary logs continuous | 30 days for full backups; 7 days for binary logs |
| Document storage (filesystem) | Filesystem-level snapshot or rsync to offsite/secondary location | Daily incremental | 30 days |
| Application configuration | Version-controlled (Git); deployed via CI/CD | On every change | Full Git history |
| Encryption keys | Separate backup to a different storage location from the data they protect; access restricted to Superadmin and infrastructure team | On every rotation | Indefinite (with key version tracking) |

## Encryption

- All backups shall be encrypted at rest using AES-256.
- Backup transfers shall use encrypted transport (TLS 1.2+ or SSH).
- Encryption keys for backups shall be stored separately from the backup data.

## Recovery Objectives

| Metric | Target | Notes |
|---|---|---|
| Recovery Point Objective (RPO) | ≤ 1 hour | Binary log shipping allows point-in-time recovery within minutes; 1 hour is the worst-case target |
| Recovery Time Objective (RTO) | ≤ 4 hours | Full database restore from dump + binary log replay + document restore + application redeploy |

## Restore Testing

- A full restore test shall be performed at least quarterly.
- Restore tests shall verify: database integrity, document accessibility, application functionality, and tenant data isolation.
- Restore test results shall be documented and retained for audit purposes.

## Disaster Recovery Scenarios

| Scenario | Response |
|---|---|
| Database corruption | Restore from latest full backup + binary log replay to point before corruption |
| Storage failure | Restore documents from offsite backup; database references remain intact |
| Full server loss | Redeploy application from CI/CD; restore database and documents from offsite backups |
| Tenant data loss (accidental deletion) | Restore specific tenant data from backup using tenant_id isolation |

---

# 17. Environments and Deployment

## Environments

| Environment | Purpose | Data | Access |
|---|---|---|---|
| Development (dev) | Local developer workstations or shared dev server | Synthetic/seed data only; no production data | Developers |
| Staging | Pre-production testing, UAT, integration testing | Anonymised copy of production data or realistic synthetic data | Developers, QA, selected tenant Admins for UAT |
| Production | Live system | Real tenant data | End users; infrastructure team for operations |

## Environment Configuration

Configuration shall differ between environments via environment variables (`.env` files, not committed to version control). Environment-specific settings include:

- Database connection credentials
- SMTP server and sender address
- JWT signing secret
- File storage base path
- Debug/logging verbosity (verbose in dev/staging, structured and minimal in production)
- Rate limiting thresholds (relaxed in dev, production-grade in staging and production)
- CORS allowed origins

## CI/CD Expectations

- A CI pipeline shall run on every commit to the main branch: lint (PHP, JS), unit tests, dependency vulnerability scan (Composer audit, npm audit).
- Staging deployment shall be automated on merge to the main branch.
- Production deployment shall require manual approval (or tag-based trigger).
- Database migrations shall be version-controlled (numbered migration files) and applied automatically during deployment.
- Rollback capability: the deployment process must support reverting to the previous application version. Database migrations that are destructive (column drops, table drops) shall be deferred to a separate migration after the new code is confirmed stable.

## Initial Provisioning

On first deployment, the system shall:

1. Run all database migrations to create the schema.
2. Seed the roles table with the default role set (Section 6).
3. Seed the freemail blocklist with a default set of public email domains.
4. Create an initial Superadmin account via a CLI provisioning command (not via the web UI).
5. Seed the initial regulatory rule version with the current AI Act classification logic.

---

# 18. API Versioning

## Strategy

The API uses URL-based versioning: `/api/v1/...`. All endpoints defined in this specification are v1.

### Versioning Rules

- The current version (`v1`) is the default and only supported version in Phase 1.
- When breaking changes are required (field removals, response structure changes, behavioural changes to existing endpoints), a new version (`v2`) is introduced.
- Non-breaking changes (new optional fields, new endpoints, additional enum values) are added to the current version without incrementing.
- The previous version remains supported for a minimum deprecation period of 12 months after the new version is released.
- Deprecation notices are communicated via a `Sunset` HTTP header on responses from deprecated versions and via in-app/email notifications to tenant Admins.

### Implementation

- The Vue frontend always targets the latest API version.
- External API consumers (Phase 2 integrations) specify the version in the URL.
- API version routing is handled at the application router level, not via separate codebases.

---

# 19. Performance Requirements

## Response Time Targets

| Operation | Target (95th percentile) | Notes |
|---|---|---|
| API read endpoints (single resource) | ≤ 200 ms | Includes authentication middleware |
| API read endpoints (list with pagination) | ≤ 500 ms | Standard page size (25 items) |
| API write endpoints | ≤ 500 ms | Excludes file uploads |
| File upload | ≤ 5 seconds | For files up to 50 MB |
| Dashboard load | ≤ 2 seconds | All widgets combined |
| Classification wizard step submission | ≤ 300 ms | Perceived responsiveness is critical in wizard flows |
| Full-text search | ≤ 1 second | Across all searchable entity types |

## Concurrency

- The system shall support at least 50 concurrent authenticated users per tenant without degradation.
- The system shall support at least 20 active tenants on a single deployment without degradation.
- These targets assume a mid-range server configuration (4 vCPU, 8 GB RAM, SSD storage). Higher concurrency requires horizontal scaling or infrastructure upgrades.

## Database Performance

- All foreign key columns shall be indexed.
- List endpoints shall use pagination (default 25, max 100 per page).
- Queries against `audit_log` (which grows unbounded) shall use indexed time-range filters; full table scans are prohibited.
- Dashboard widget queries shall be optimised with materialised views or pre-computed summary tables if query times exceed targets.

## Frontend Performance

- Initial page load (Time to Interactive): ≤ 3 seconds on a broadband connection.
- Vue route transitions: ≤ 500 ms.
- Vite build shall produce code-split bundles; no single JavaScript bundle exceeding 500 KB gzipped.

---

# 20. Data Import and Migration

Organisations onboarding onto the platform may have existing AI inventories, vendor lists, or risk assessment data in spreadsheets, documents, or other tools. The platform shall support data import to reduce onboarding friction.

## Phase 1: CSV/XLSX Import

### Supported Import Targets

| Entity | Required Fields | Optional Fields |
|---|---|---|
| AI systems | name, intended_purpose, department | description, vendor_name, deployment_status, personal_data_flag, sensitive_data_flag |
| Vendors | name, vendor_type | website, contact_name, contact_email, risk_rating, dpa_status |

### Import Workflow

1. Tenant Admin uploads a CSV or XLSX file via the Tenant Admin Panel.
2. The system validates the file: column mapping, required fields, data types, referential integrity (e.g., department names must match existing departments or be flagged for creation).
3. A preview screen shows the parsed data with row-level validation results (valid, warning, error).
4. The Admin reviews and confirms the import.
5. Records are created in `draft` or `not_classified` status; they do not bypass the classification or approval workflows.
6. An import summary is displayed: records created, records skipped (with reasons), warnings.
7. The import action is audit-logged.

### Import Templates

The system shall provide downloadable CSV/XLSX templates for each supported entity with example data and column descriptions.

## Phase 2: Extended Import

- Risk assessment import (structured format TBD).
- Bulk document upload with metadata mapping.
- API-based import for integration with external tools.
- Import from common GRC platforms (export format mapping).

---

# 21. Platform Legal Framework

The platform itself requires legal documentation to operate as a multi-tenant SaaS service.

## Required Documents

| Document | Purpose | Managed By |
|---|---|---|
| Terms of Service (ToS) | Governs the relationship between the platform operator and tenant organisations | Platform operator (Superadmin / legal team) |
| Privacy Policy | Describes how the platform collects, processes, and stores personal data of users | Platform operator |
| Data Processing Agreement (DPA) | GDPR Article 28 compliant DPA between the platform operator (processor) and tenant organisations (controllers) | Platform operator; signed per tenant |
| Sub-processor List | List of sub-processors used by the platform (hosting provider, email service, etc.) | Platform operator; accessible to tenants |
| Acceptable Use Policy | Defines prohibited uses of the platform | Platform operator |
| Cookie Policy | Describes cookie usage (if applicable; the JWT-based architecture minimises cookie use but the refresh token cookie requires disclosure) | Platform operator |

## Platform Integration

- The registration page shall require acceptance of the Terms of Service and Privacy Policy before account creation.
- The ToS and Privacy Policy shall be versioned. When a new version is published, active users shall be prompted to accept the updated terms on their next login.
- Acceptance of ToS and Privacy Policy shall be recorded with timestamp, user ID, and document version in a dedicated `legal_acceptances` table.
- The DPA is managed outside the platform (signed document) but the platform shall provide a DPA download link in the tenant Admin panel and track whether a DPA is on file per tenant.

## Database Table

```sql
CREATE TABLE legal_acceptances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    document_type ENUM('tos','privacy_policy','cookie_policy','aup') NOT NULL,
    document_version VARCHAR(50) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

## Tenants Table Amendment

The `tenants` table shall include:

- `dpa_signed_at` (DATE, nullable) — date the DPA was signed.
- `dpa_document_id` (INT UNSIGNED, nullable, FK to `documents`) — reference to the uploaded signed DPA.

---

# 22. MVP Scope (Phase 1)

## Included

- Authentication (JWT, refresh tokens, token revocation, MFA-ready; MFA mandatory for Superadmin)
- Full user management (domain-based and invite-based registration, user approval workflow)
- Superadmin role and Platform Administration panel (Section 7.2.1)
- Tenant Admin panel (including workflow configuration and regulatory rule management)
- RBAC (tenant-scoped roles + platform-level Superadmin)
- AI inventory
- AI Usage Request Form (Section 7.4.1)
- Guided registration wizard
- Guided classification wizard (with regulatory rule versioning)
- Contextual help system
- Glossary (English, locale-ready)
- Risk assessments (with versioning)
- Basic FRIA (integrated into risk assessment workflow)
- Basic vendor registry
- Approval workflows (configurable via Admin Panel)
- Evidence upload (with file storage architecture per Section 7.12)
- Audit logging (append-only, tamper-resistant)
- Dashboard (Phase 1 widgets)
- Reporting/export
- Internationalisation framework (English default, ready for additional locales)
- Data retention and automated cleanup (Section 15)
- Backup and disaster recovery baseline (Section 16)
- CI/CD pipeline and environment configuration (Section 17)
- API versioning (v1) (Section 18)
- CSV/XLSX import for AI systems and vendors (Section 20)
- Platform legal framework: ToS, Privacy Policy acceptance tracking (Section 21)

---

# 23. Phase 2

## Planned Features

- Advanced FRIA workflows (standalone, versioned, per-Charter-right analysis)
- AI literacy management (training courses, assignments, tracking)
- Vendor/GPAI advanced workflows (assessments, model registry, contract lifecycle)
- German language support
- SSO/SAML/OIDC
- Server-Sent Events for real-time in-app notifications
- GPAI and Training Status dashboard widgets
- Jira integration
- Confluence export
- Advanced analytics
- API integrations
- Automated evidence expiration tracking
- S3-compatible object storage migration option
- Extended data import (risk assessments, bulk documents, API-based, GRC platform mapping)
- Token blocklist (Redis/cache-based) for immediate access token revocation
- DNS-based tenant domain verification
- Auto-approve users from verified domains (configurable)

---

# 24. Future Enhancements

Potential future integrations:

- ISO 42001 mapping
- NIST AI RMF mapping
- GDPR DPIA integrations
- AI discovery/scanning
- Microsoft Copilot integrations
- Azure OpenAI governance
- OpenAI usage tracking
- SIEM integrations

---

# 25. Suggested Product Names

- AIGovDesk
- ActWise AI
- AI Risk Ledger
- AegisAI
- AI Compliance Hub

---

# 26. Final Principle

The platform must not claim automatic legal compliance.

Instead, it shall communicate:

> Based on the provided information, the system appears to fall into the following AI Act category.
> The following controls, approvals, reviews, and evidence items are recommended or required.
> Final legal determination should be performed by qualified compliance or legal personnel.

---

# Appendix A: Change Log from v1.0

| Section | Change | Rationale |
|---|---|---|
| 2.1 | Added Regulatory Update Strategy | Classification logic must accommodate evolving delegated acts without code changes |
| 3 | Added vue-i18n to frontend stack | i18n support required from Phase 1 |
| 5.5 | Added Internationalisation section | DACH market relevance; retrofitting i18n is expensive |
| 6 | Added Superadmin role; added Scope column; clarified Employee role | Multi-tenancy requires a platform-level administrative role |
| 7.1 | Expanded into 7.1.1 (Tenant-Aware Registration), 7.1.2 (User Approval Workflow), 7.1.3 (User Invitation Management) | Multi-tenant registration requires domain-based tenant resolution, invite-based alternative path, and approval gate |
| 7.2 | Renamed to Tenant Admin Panel; added Pending Registrations, Invitations, Tenant Domain Management; scoped to tenant context; added locale support and regulatory rule management | Distinguishes tenant administration from platform administration; supports registration workflow |
| 7.2.1 | New section: Platform Administration (Superadmin); added Freemail Blocklist management | Tenant lifecycle management, global settings, impersonation, platform monitoring, public domain blocking |
| 7.3 | Added field detail annotations | Foreign keys, boolean+description pairs, and references needed clarification |
| 7.4 | Clarified relationship between Registration Wizard and AI Request Form | Employee vs AI Owner workflow was ambiguous |
| 7.4.1 | New section: AI Usage Request Form | Resolved Employee role / registration wizard confusion |
| 7.5 | Added regulatory rule version reference | Classifications must be traceable to rule version used |
| 7.8 | Split FRIA into Phase 1 (basic) and Phase 2 (advanced) scope | Original spec was ambiguous on FRIA phase boundary |
| 7.10 | Split Vendor module into Phase 1 (basic registry) and Phase 2 (advanced) scope | AI Inventory needs vendor FK in Phase 1; full lifecycle is Phase 2 |
| 7.11 | Expanded workflow engine specification | Clarified configurable vs hardcoded; defined step properties |
| 7.12 | Added File Storage architecture | Storage strategy, encryption, access control were undefined |
| 7.13 | Split dashboard widgets into Phase 1 and Phase 2 | GPAI and Training widgets depend on Phase 2 modules |
| 8 | Complete rewrite: multi-tenancy, full schema, versioning strategy; users.tenant_id nullable for Superadmin, account_status enum replacing is_active, approval tracking fields, invitation FK; new tables: tenant_domains, freemail_blocklist, user_invitations; audit_log.impersonated_by for impersonation tracking | Original had table names only; multi-tenant registration requires domain registry, invitation tokens, and approval state machine |
| 9 | Complete rewrite: JWT auth, response envelope, full endpoint catalog including registration, invitation, domain management, user approval, and freemail blocklist endpoints | Auth mechanism, error format, and many endpoints were missing; registration flow requires dedicated endpoints |
| 10 | Expanded security requirements | Added security headers, dependency scanning, cookie attribute specifics |
| 11 | Added tamper-resistance implementation detail | Append-only constraint needed specification |
| 12 | Added notification delivery architecture | Queue mechanism and polling/SSE strategy were undefined |
| 14 | Specified WCAG 2.1 AA and concrete thresholds | Original was too vague |
| 9.1 | Added Session and Token Revocation section with refresh_tokens table | Forced invalidation on user/tenant deactivation was unspecified; stateless JWTs require explicit revocation strategy |
| 15 | New section: Data Retention and Deletion Policy | GDPR right to erasure, audit log preservation, user anonymisation, automated retention enforcement |
| 16 | New section: Backup and Disaster Recovery | RPO/RTO targets, backup strategy, encryption, restore testing, DR scenarios |
| 17 | New section: Environments and Deployment | Dev/staging/production environments, CI/CD expectations, migration strategy, initial provisioning |
| 18 | New section: API Versioning | URL-based versioning strategy, deprecation policy, non-breaking vs breaking change rules |
| 19 | New section: Performance Requirements | API response time targets, concurrency expectations, database and frontend performance criteria |
| 20 | New section: Data Import and Migration | CSV/XLSX import for onboarding, import workflow with validation and preview, Phase 2 extended import |
| 21 | New section: Platform Legal Framework | ToS, Privacy Policy, DPA, sub-processor list, legal_acceptances table, tenants table DPA tracking |
| 22 | Updated Phase 1 scope (renumbered from 15) | Added data retention, backup/DR, CI/CD, API versioning, import, legal framework |
| 23 | Updated Phase 2 scope (renumbered from 16) | Added extended import, token blocklist, DNS domain verification, auto-approve for verified domains |
| 24-26 | Renumbered from 17-19 | Section numbering updated to accommodate new sections |
