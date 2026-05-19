# Phase 1 MVP Implementation Plan (Execution Board)

Date: 2026-05-19
Scope: Section 22 (MVP Scope, Phase 1) from specification
Duration: 14 weeks (7 sprints x 2 weeks)

## 1) Planning Intent

This plan is execution-ready. It is designed to:

- keep Phase 1 scope strict,
- enforce tenant isolation and security as non-negotiable constraints,
- deliver each sprint with measurable acceptance criteria,
- make dependencies, ownership, and risks explicit.

This document is planning-only. It contains no implementation code.

## 2) Team Roles (Owner Codes)

- BE: Backend engineer
- FE: Frontend engineer
- QA: Test/automation engineer
- DO: DevOps engineer
- SEC: Security reviewer
- PM: Product/project owner

## 3) Definition of Done (Global)

A work package is done only if all apply:

1. Functional behavior matches specification section(s).
2. Tenant isolation is verified for affected endpoints.
3. Input validation uses whitelist approach.
4. Audit logging is implemented for material actions.
5. Tests are added/updated and passing.
6. API responses follow the standard envelope.
7. Security checks for changed area pass (headers, authz/authn, file restrictions, rate limiting as applicable).
8. Documentation for changed behavior is updated.

## 4) Phase 1 Scope Guardrail

In-scope for this plan:

- Authentication and full user management
- Superadmin and tenant admin panels
- AI inventory and AI usage request
- Registration wizard and classification wizard
- Risk assessment, basic FRIA, controls
- Approval workflow engine
- Evidence/document management
- Notifications/reminders (polling + email queue)
- Dashboard (Phase 1 widgets)
- Reporting/export
- Search/filtering
- CSV/XLSX import (AI systems, vendors)
- Legal acceptance tracking (ToS/Privacy)
- Data retention baseline
- Backup/DR baseline
- CI/CD baseline and API v1

Explicitly out-of-scope (deferred):

- Advanced FRIA workflows
- AI literacy module
- Advanced vendor/GPAI lifecycle
- SSO/SAML/OIDC
- SSE real-time notifications
- German language content rollout

## 5) Sprint Plan (Execution Board)

## Sprint 0 (Weeks 1-2): Foundation

### S0-WP1: Repo and Runtime Bootstrap
- Owner: DO
- Dependencies: None
- Deliverables:
  - Project structure aligned to CLAUDE.md
  - Frontend and backend skeletons
  - Environment variable templates
  - Local docker stack for app + db
- DoD:
  - Fresh setup succeeds from README commands
  - App starts and returns health response

### S0-WP2: Backend Core Infrastructure
- Owner: BE
- Dependencies: S0-WP1
- Deliverables:
  - Router, Request, Response, ErrorHandler, exception set
  - Config loader and PDO DB connection
- DoD:
  - Middleware chain executes in required order
  - Error mapping and envelope verified by tests

### S0-WP3: Initial Database and Seed Baseline
- Owner: BE
- Dependencies: S0-WP1
- Deliverables:
  - Migrations 001-011
  - Seed data: roles, freemail blocklist, default settings
  - Superadmin provisioning CLI entry point
- DoD:
  - New DB reaches expected schema from zero
  - Seeds idempotent and safe on rerun

### S0-WP4: CI Baseline
- Owner: DO
- Dependencies: S0-WP1
- Deliverables:
  - CI checks for lint/test placeholders
  - Dependency audit steps scaffolded
- DoD:
  - CI runs on branch push and blocks on failure

### Sprint 0 Exit Criteria

1. End-to-end startup from clean machine works.
2. Migrations and seeds run with no manual DB edits.
3. Core API envelope and error handling are operational.

---

## Sprint 1 (Weeks 3-4): Authentication and User Management

### S1-WP1: Auth Backend and Session Lifecycle
- Owner: BE
- Dependencies: Sprint 0
- Deliverables:
  - login/logout/refresh/forgot/reset (+ MFA-ready flows)
  - Refresh token storage, revocation, and cookie behavior
- DoD:
  - Access token TTL and refresh flow match spec
  - Logout revokes refresh token

### S1-WP2: Security Middleware Stack
- Owner: BE
- Dependencies: Sprint 0
- Deliverables:
  - CORS, RateLimit, Auth, Tenant, RBAC middleware
- DoD:
  - Unauthorized -> 401, forbidden -> 403, tenant context injected
  - Rate limits configurable by endpoint category

### S1-WP3: Registration, Approval, Invite, Domain Management
- Owner: BE
- Dependencies: S1-WP1, S1-WP2
- Deliverables:
  - Domain-based registration and invite registration
  - Approval/rejection workflow
  - Invitation and tenant domain management
- DoD:
  - Pending approval path enforced
  - Material actions audit-logged

### S1-WP4: Auth and User Management Frontend
- Owner: FE
- Dependencies: S1-WP1
- Deliverables:
  - Login/register/invite/reset pages
  - Auth store and token refresh behavior
  - Router guards
  - User management views (pending approvals, invitations, domains)
- DoD:
  - Protected routes enforce auth
  - Error states and validation are user-visible and translated

### S1-WP5: Test Coverage
- Owner: QA
- Dependencies: S1-WP1..S1-WP4
- Deliverables:
  - Integration tests for auth and user endpoints
  - Tenant isolation tests
  - Auth store unit tests
- DoD:
  - All mandatory test categories per endpoint are present

### Sprint 1 Exit Criteria

1. Auth flows complete and secure.
2. User lifecycle (register -> approve -> login) is verified.
3. Tenant isolation tests pass for user-facing endpoints.

---

## Sprint 2 (Weeks 5-6): Admin and Platform Administration

### S2-WP1: Superadmin Platform Features
- Owner: BE
- Dependencies: Sprint 1
- Deliverables:
  - Tenant CRUD/activation
  - Freemail blocklist management
  - Impersonation start/end + audit trail
- DoD:
  - Tenant deactivation revokes active refresh tokens
  - Impersonation actions include original actor in logs

### S2-WP2: Tenant Admin Backend
- Owner: BE
- Dependencies: Sprint 1
- Deliverables:
  - Tenant settings APIs
  - Workflow definition CRUD
  - Glossary/help content management
  - Regulatory rule version viewer
- DoD:
  - Tenant scope enforced on every read/write

### S2-WP3: Admin Frontend
- Owner: FE
- Dependencies: S2-WP1, S2-WP2
- Deliverables:
  - Superadmin and tenant admin layouts
  - Workflow builder and settings screens
  - Audit log filtering UI
- DoD:
  - Role-specific navigation and screen access verified

### S2-WP4: Tests
- Owner: QA
- Dependencies: S2-WP1..S2-WP3
- Deliverables:
  - Platform admin tests
  - Workflow CRUD tests
  - Impersonation audit tests
- DoD:
  - Superadmin-only endpoints blocked for tenant roles

### Sprint 2 Exit Criteria

1. Platform and tenant administration features are operational.
2. Impersonation and tenant lifecycle are audit-safe.
3. RBAC enforcement is tested across admin surfaces.

---

## Sprint 3 (Weeks 7-8): AI Inventory, Requests, Registration, Documents

### S3-WP1: Core Domain Schema
- Owner: BE
- Dependencies: Sprint 2
- Deliverables:
  - Migrations 012-015 (vendors, ai_systems, ai_requests, documents)
- DoD:
  - Foreign keys and indexes support expected query patterns

### S3-WP2: Vendor Basic Registry + AI Inventory APIs
- Owner: BE
- Dependencies: S3-WP1
- Deliverables:
  - Vendor CRUD (basic Phase 1)
  - AI system CRUD
- DoD:
  - Data linked correctly between systems and vendors

### S3-WP3: AI Usage Request Workflow
- Owner: BE
- Dependencies: S3-WP1
- Deliverables:
  - Employee request submit/list
  - AI Owner decision endpoints
  - Notifications for request and decision
- DoD:
  - Workflow state transitions validated

### S3-WP4: Registration Wizard Frontend + Submission API
- Owner: FE (UI), BE (submission)
- Dependencies: S3-WP2
- Deliverables:
  - Wizard shell and step components
  - Draft persistence and validation
  - Submission integration
- DoD:
  - Step validation blocks invalid progression

### S3-WP5: Evidence and Document Management
- Owner: BE (service), FE (UI)
- Dependencies: S3-WP1
- Deliverables:
  - Upload/list/download/delete document APIs
  - Secure document UI integration
- DoD:
  - MIME/size validation and access control enforced
  - Storage path policy respected

### S3-WP6: Tests
- Owner: QA
- Dependencies: S3-WP2..S3-WP5
- Deliverables:
  - AI system and request flow tests
  - Document security and RBAC tests
  - Wizard submission integration tests
- DoD:
  - Cross-tenant access attempts return not found/forbidden as designed

### Sprint 3 Exit Criteria

1. End-to-end request -> registration path works.
2. Inventory and vendor basics are stable.
3. Document handling is secure and test-proven.

---

## Sprint 4 (Weeks 9-10): Classification, Risk, Basic FRIA, Controls

### S4-WP1: Assessment Schema
- Owner: BE
- Dependencies: Sprint 3
- Deliverables:
  - Migrations 016-021
- DoD:
  - Versioned entities support current/historical queries

### S4-WP2: Classification Engine and Wizard
- Owner: BE (logic), FE (wizard)
- Dependencies: S4-WP1
- Deliverables:
  - Classification evaluation against rule versions
  - Versioned classification records
  - Wizard UX and results display
- DoD:
  - New classification creates new version, preserves old records

### S4-WP3: Risk Assessment Engine
- Owner: BE (scoring), FE (matrix UI)
- Dependencies: S4-WP1
- Deliverables:
  - Risk scoring, residual risk, control effectiveness
- DoD:
  - Calculations validated by deterministic test cases

### S4-WP4: Basic FRIA and Controls Checklist
- Owner: BE/FE
- Dependencies: S4-WP3
- Deliverables:
  - Basic FRIA integrated in risk flow
  - Controls CRUD and checklist status tracking
- DoD:
  - FRIA remains in Phase 1 basic scope only

### S4-WP5: Tests
- Owner: QA
- Dependencies: S4-WP2..S4-WP4
- Deliverables:
  - Versioning and scoring tests
  - Rule-version linkage tests
- DoD:
  - Historical versions are immutable in behavior

### Sprint 4 Exit Criteria

1. Classification/risk/FRIA basic flows complete.
2. Versioning integrity is validated.
3. Rule-version traceability is audit-ready.

---

## Sprint 5 (Weeks 11-12): Approvals, Notifications, Dashboard, Reporting, Search, Import, Legal

### S5-WP1: Approval Workflow Engine
- Owner: BE
- Dependencies: Sprint 4
- Deliverables:
  - Migrations 022-025
  - Workflow initiation, step advancement, rejection, completion
  - Conditional step skips and escalation handling
- DoD:
  - Full approval timeline and state transitions verifiable

### S5-WP2: Notification Services
- Owner: BE
- Dependencies: S5-WP1
- Deliverables:
  - Notification creation/read APIs
  - Email queue worker via cron
  - In-app polling support
- DoD:
  - Notification events emitted at required workflow events

### S5-WP3: Dashboard and Reporting
- Owner: BE/FE
- Dependencies: S5-WP1
- Deliverables:
  - Phase 1 dashboard widgets
  - Reports + CSV/XLSX/PDF export
- DoD:
  - Report results match source data for sampled cases

### S5-WP4: Search and Filtering
- Owner: BE/FE
- Dependencies: S3-WP2, S4-WP3
- Deliverables:
  - Global search endpoint and UI entry point
  - Advanced filtering on list views
- DoD:
  - Search performance and relevance acceptable for MVP targets

### S5-WP5: Data Import (CSV/XLSX)
- Owner: BE/FE
- Dependencies: S3-WP2
- Deliverables:
  - Upload, parse, map, validate, preview, confirm flow
  - Templates for AI systems and vendors
- DoD:
  - Import never bypasses classification/approval
  - Row-level result reporting and audit logging enabled

### S5-WP6: Legal Acceptance
- Owner: BE/FE
- Dependencies: Sprint 1
- Deliverables:
  - ToS/Privacy acceptance prompts on first login/new version
  - Legal acceptance persistence and retrieval
- DoD:
  - Versioned acceptance evidence available per user

### S5-WP7: Tests
- Owner: QA
- Dependencies: S5-WP1..S5-WP6
- Deliverables:
  - Approval workflow integration tests
  - Notifications and worker tests
  - Import and legal acceptance tests
- DoD:
  - Rejection loops and escalation paths tested

### Sprint 5 Exit Criteria

1. Governance workflow can run from submission to final approval.
2. Dashboard/reporting/search/import/legal functions are operational.
3. Notifications and escalations are verified.

---

## Sprint 6 (Weeks 13-14): Hardening and Launch Readiness

### S6-WP1: Security Hardening
- Owner: SEC + BE + FE
- Dependencies: Sprint 5
- Deliverables:
  - Security checklist execution
  - Headers, rate limits, authz regression validation
  - Dependency vulnerability scans and remediation list
- DoD:
  - High/Critical findings closed or explicitly accepted

### S6-WP2: Performance and Capacity Validation
- Owner: BE + FE + DO
- Dependencies: Sprint 5
- Deliverables:
  - Benchmarks against Section 19 targets
  - Query optimization and index tuning
  - Frontend bundle/perceived performance checks
- DoD:
  - P95 targets met or formal exceptions approved

### S6-WP3: Backup/DR and Operational Readiness
- Owner: DO
- Dependencies: Sprint 5
- Deliverables:
  - Backup jobs configured and verified
  - Restore test evidence recorded
  - Runbooks for incident/deploy/rollback
- DoD:
  - Restore rehearsal succeeds end-to-end

### S6-WP4: Documentation and UAT
- Owner: PM + QA + DO
- Dependencies: S6-WP1..S6-WP3
- Deliverables:
  - API/database/deployment docs finalized
  - UAT execution with realistic seed data
- DoD:
  - All critical user journeys signed off

### Sprint 6 Exit Criteria

1. Security, performance, and operations baselines are proven.
2. UAT passes for cross-role workflows.
3. Release readiness decision can be made with evidence.

## 6) Cross-Sprint Governance Tracks

### Track A: Security and Compliance
- Owner: SEC
- Cadence: every sprint
- Controls:
  - threat-focused review for changed modules
  - audit log coverage checks
  - legal/privacy impact checks

### Track B: Quality and Test Automation
- Owner: QA
- Cadence: every sprint
- Controls:
  - mandatory endpoint test categories
  - tenant isolation tests for every new controller
  - regression suite growth target each sprint

### Track C: DevOps and Reliability
- Owner: DO
- Cadence: every sprint
- Controls:
  - CI gate quality trend
  - deployment/rollback rehearsal
  - backup status and retention checks

### Track D: UX, Accessibility, and i18n Readiness
- Owner: FE + QA
- Cadence: every sprint
- Controls:
  - wizard usability consistency
  - WCAG 2.1 AA checks on changed screens
  - user-facing text externalization and locale correctness

## 7) Stage Gates

### Gate G0 (end Sprint 0): Architecture Baseline
- Must pass:
  - middleware execution order confirmed
  - envelope/error model fixed
  - migrations/seeds reproducible

### Gate G1 (end Sprint 1): Identity and Access Baseline
- Must pass:
  - auth + RBAC functional tests
  - user approval and invite lifecycle verified

### Gate G2 (end Sprint 3): Core Data and Evidence Baseline
- Must pass:
  - AI inventory + request + document security tests
  - tenant isolation for domain entities

### Gate G3 (end Sprint 4): Compliance Logic Baseline
- Must pass:
  - classification/risk/FRIA basic flows
  - versioning integrity and traceability

### Gate G4 (end Sprint 6): Release Readiness
- Must pass:
  - security hardening checklist
  - performance targets or approved exceptions
  - backup/restore evidence and UAT signoff

## 8) Risk Register (Initial)

### R1: Scope Creep into Phase 2
- Probability: High
- Impact: High
- Mitigation:
  - strict scope review at sprint planning
  - explicit deferral list tracked in backlog

### R2: Tenant Isolation Defects
- Probability: Medium
- Impact: Critical
- Mitigation:
  - mandatory tenant filters in model patterns
  - dedicated isolation tests per endpoint

### R3: Workflow Engine Complexity Late in Project
- Probability: Medium
- Impact: High
- Mitigation:
  - design and data model decisions fixed by end Sprint 2
  - early integration spikes before Sprint 5

### R4: Performance Issues on Dashboard/Search
- Probability: Medium
- Impact: Medium
- Mitigation:
  - profile queries early
  - add summary tables/indexes when thresholds are exceeded

### R5: Security Findings Near Release
- Probability: Medium
- Impact: High
- Mitigation:
  - continuous security checks each sprint
  - no carry-over of High/Critical findings past Sprint 6

## 9) Tracking Format for Delivery Board

Use this board schema for each sprint backlog item:

- ID: Sx-WPy-z
- Title: concise deliverable name
- Owner: BE/FE/QA/DO/SEC/PM
- Depends on: item IDs
- Scope note: one-line description
- Acceptance: objective pass criteria
- Evidence: test name, report, or checklist artifact
- Status: planned / in-progress / done / blocked

## 10) Immediate Next Planning Actions

1. Convert each work package into sprint backlog tickets using section 9 schema.
2. Assign named owners to all Sprint 0 and Sprint 1 packages.
3. Baseline acceptance checklists for Gates G0 and G1.
4. Create a Phase 2 defer-list to prevent scope leakage during MVP execution.
