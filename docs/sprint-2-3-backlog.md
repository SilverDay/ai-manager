# Sprint 2 and Sprint 3 Backlog (Execution Tickets)

Date: 2026-05-19
Source: docs/implementation-plan.md

Status values: planned | in-progress | done | blocked

## Sprint 2 (Weeks 5-6)

### S2-WP1-01
- Title: Implement platform tenant lifecycle APIs
- Owner: BE
- Depends on: Sprint 1 complete
- Scope note: Add create/read/update/deactivate/reactivate operations for tenants in platform administration.
- Acceptance:
  - Superadmin-only access is enforced.
  - Deactivation state is reflected consistently in tenant queries.
- Evidence:
  - Platform tenant API integration tests.
- Status: planned

### S2-WP1-02
- Title: Enforce token revocation on tenant deactivation
- Owner: BE
- Depends on: S2-WP1-01
- Scope note: Revoke active refresh tokens when tenant is deactivated.
- Acceptance:
  - Existing sessions for deactivated tenant cannot refresh access.
  - Revocation is audit-logged.
- Evidence:
  - Auth lifecycle tests with deactivation scenario.
- Status: planned

### S2-WP1-03
- Title: Implement freemail blocklist management endpoints
- Owner: BE
- Depends on: S2-WP1-01
- Scope note: Add platform CRUD for blocked public email domains.
- Acceptance:
  - Registration validation references latest blocklist values.
  - Changes are restricted to superadmin users.
- Evidence:
  - Blocklist API tests.
- Status: planned

### S2-WP1-04
- Title: Implement impersonation start and stop endpoints
- Owner: BE
- Depends on: S2-WP1-01
- Scope note: Superadmin can enter/exit tenant context for support workflows.
- Acceptance:
  - Impersonation context is explicit in request metadata.
  - Original actor identity persists for all actions.
- Evidence:
  - Impersonation integration tests.
- Status: planned

### S2-WP1-05
- Title: Add platform audit log view APIs
- Owner: BE
- Depends on: S2-WP1-04
- Scope note: Provide cross-tenant audit query endpoints with filtering.
- Acceptance:
  - Time-range and entity filters work.
  - Access limited to superadmin/auditor platform permissions.
- Evidence:
  - Audit query test suite.
- Status: planned

### S2-WP2-01
- Title: Implement tenant settings API
- Owner: BE
- Depends on: Sprint 1 complete
- Scope note: Add tenant-scoped settings read/update endpoints.
- Acceptance:
  - Only tenant admins can change tenant settings.
  - Changes are validated and audited.
- Evidence:
  - Settings endpoint tests.
- Status: planned

### S2-WP2-02
- Title: Implement workflow definition CRUD
- Owner: BE
- Depends on: S2-WP2-01
- Scope note: Add workflow definitions and ordered steps management endpoints.
- Acceptance:
  - Step order persistence is deterministic.
  - Role assignment validation is enforced.
- Evidence:
  - Workflow CRUD tests.
- Status: planned

### S2-WP2-03
- Title: Implement glossary management API
- Owner: BE
- Depends on: S2-WP2-01
- Scope note: Add tenant glossary CRUD with locale-aware records.
- Acceptance:
  - Search by term works within tenant scope.
  - Locale variants can coexist per term.
- Evidence:
  - Glossary API tests.
- Status: planned

### S2-WP2-04
- Title: Implement contextual help text API
- Owner: BE
- Depends on: S2-WP2-03
- Scope note: Add per-page/per-field help text management endpoints.
- Acceptance:
  - Help content can be targeted by screen context key.
  - Locale and version metadata are stored.
- Evidence:
  - Help text endpoint tests.
- Status: planned

### S2-WP2-05
- Title: Implement regulatory rule version viewer API
- Owner: BE
- Depends on: S2-WP2-01
- Scope note: Add read endpoints for versioned regulatory rule sets used in classification.
- Acceptance:
  - Current and historical rule versions can be retrieved.
  - Access is role-restricted.
- Evidence:
  - Rule version retrieval tests.
- Status: planned

### S2-WP3-01
- Title: Build superadmin platform UI shell
- Owner: FE
- Depends on: S2-WP1-01
- Scope note: Create navigation and layout for platform administration views.
- Acceptance:
  - Platform routes are hidden from non-superadmin users.
  - Navigation and route guards align with RBAC.
- Evidence:
  - UI routing smoke tests.
- Status: planned

### S2-WP3-02
- Title: Build tenant management UI
- Owner: FE
- Depends on: S2-WP1-01
- Scope note: Add list/detail/actions interface for tenant lifecycle actions.
- Acceptance:
  - Create/update/deactivate/reactivate actions work end-to-end.
  - Confirmation and error states are clearly handled.
- Evidence:
  - Manual QA scenario checklist.
- Status: planned

### S2-WP3-03
- Title: Build tenant admin UI shell
- Owner: FE
- Depends on: S2-WP2-01
- Scope note: Create tenant admin layout, sidebar, and route grouping.
- Acceptance:
  - Tenant admin pages share consistent shell and guards.
  - Unauthorized users are redirected.
- Evidence:
  - Route guard tests.
- Status: planned

### S2-WP3-04
- Title: Build workflow builder UI
- Owner: FE
- Depends on: S2-WP2-02
- Scope note: Add ordered step editor with role mapping for approval workflows.
- Acceptance:
  - Step reorder and persistence behavior is stable.
  - Invalid role assignments are blocked.
- Evidence:
  - Component integration tests.
- Status: planned

### S2-WP3-05
- Title: Build glossary and help management UI
- Owner: FE
- Depends on: S2-WP2-03, S2-WP2-04
- Scope note: Provide CRUD views for glossary terms and contextual help entries.
- Acceptance:
  - Locale-specific content management is supported.
  - Search/filter interactions perform correctly.
- Evidence:
  - UI tests for create/edit/search flows.
- Status: planned

### S2-WP3-06
- Title: Build platform audit log viewer UI
- Owner: FE
- Depends on: S2-WP1-05
- Scope note: Add filterable audit log list for platform users.
- Acceptance:
  - Time/entity/action filters work as expected.
  - Pagination and sorting are stable.
- Evidence:
  - UI smoke tests for filter matrix.
- Status: planned

### S2-WP4-01
- Title: Add platform admin integration tests
- Owner: QA
- Depends on: S2-WP1-01..S2-WP1-05
- Scope note: Cover auth, authz, validation, and cross-tenant boundaries for platform APIs.
- Acceptance:
  - Superadmin-only paths are comprehensively tested.
  - Unauthorized role access returns correct status.
- Evidence:
  - CI test report artifact.
- Status: planned

### S2-WP4-02
- Title: Add impersonation audit trail tests
- Owner: QA
- Depends on: S2-WP1-04
- Scope note: Verify audit logs record both acting and impersonated identities.
- Acceptance:
  - Start/stop impersonation events are logged.
  - Material actions during impersonation include origin actor metadata.
- Evidence:
  - Audit assertion tests.
- Status: planned

### S2-WP4-03
- Title: Add workflow definition tests
- Owner: QA
- Depends on: S2-WP2-02
- Scope note: Verify workflow steps ordering, role constraints, and tenant isolation.
- Acceptance:
  - Cross-tenant workflow access is blocked.
  - Invalid configurations return 422.
- Evidence:
  - Workflow test suite output.
- Status: planned

## Sprint 2 Milestone Check

### M2 (end Sprint 2)
- Platform administration and tenant administration capabilities are operational.
- Impersonation and audit transparency are validated.
- Workflow definitions are configurable and tenant-safe.

## Sprint 3 (Weeks 7-8)

### S3-WP1-01
- Title: Deliver migrations for vendors, ai systems, ai requests, documents
- Owner: BE
- Depends on: Sprint 2 complete
- Scope note: Add schema and indexes required for core domain modules.
- Acceptance:
  - Migrations run cleanly from zero state.
  - Tenant and foreign-key indexes support expected list and lookup queries.
- Evidence:
  - Migration and schema verification run.
- Status: planned

### S3-WP2-01
- Title: Implement basic vendor CRUD APIs
- Owner: BE
- Depends on: S3-WP1-01
- Scope note: Add vendor registry endpoints for Phase 1 basic fields.
- Acceptance:
  - CRUD operations are tenant-scoped.
  - Validation enforces required fields and enum values.
- Evidence:
  - Vendor API integration tests.
- Status: planned

### S3-WP2-02
- Title: Implement ai system CRUD APIs
- Owner: BE
- Depends on: S3-WP1-01, S3-WP2-01
- Scope note: Add AI system create/list/detail/update flows with vendor linkage.
- Acceptance:
  - Vendor relationship integrity is enforced.
  - List pagination defaults and max limits are enforced.
- Evidence:
  - AI system API tests.
- Status: planned

### S3-WP2-03
- Title: Implement review date and status logic
- Owner: BE
- Depends on: S3-WP2-02
- Scope note: Apply service rules for review scheduling and initial classification state.
- Acceptance:
  - New records initialize to expected workflow status.
  - Review date calculation follows configurable rules.
- Evidence:
  - Service unit tests.
- Status: planned

### S3-WP3-01
- Title: Implement ai usage request submit/list APIs
- Owner: BE
- Depends on: S3-WP1-01
- Scope note: Allow employee request submission and role-scoped list access.
- Acceptance:
  - Employee can create request in own tenant.
  - AI Owner sees only relevant requests.
- Evidence:
  - AI request integration tests.
- Status: planned

### S3-WP3-02
- Title: Implement ai usage request decision API
- Owner: BE
- Depends on: S3-WP3-01
- Scope note: Add approve/reject decision handling by AI Owner role.
- Acceptance:
  - Decision status transitions are valid and audited.
  - Decision triggers notification events.
- Evidence:
  - Decision flow tests.
- Status: planned

### S3-WP4-01
- Title: Build vendor list and detail views
- Owner: FE
- Depends on: S3-WP2-01
- Scope note: Create tenant admin pages for vendor registry operations.
- Acceptance:
  - CRUD actions complete through UI.
  - Error and loading states are handled.
- Evidence:
  - UI smoke test checklist.
- Status: planned

### S3-WP4-02
- Title: Build ai inventory list/detail/edit views
- Owner: FE
- Depends on: S3-WP2-02
- Scope note: Provide inventory management screens and filters.
- Acceptance:
  - Users can create and edit AI systems with vendor assignment.
  - Pagination and filtering work on list page.
- Evidence:
  - Component/integration tests.
- Status: planned

### S3-WP4-03
- Title: Build ai usage request form and queue UI
- Owner: FE
- Depends on: S3-WP3-01, S3-WP3-02
- Scope note: Add employee request form and AI Owner pending decisions queue.
- Acceptance:
  - Form validation prevents invalid submission.
  - Decision updates reflect immediately in queue state.
- Evidence:
  - End-to-end smoke flow record.
- Status: planned

### S3-WP5-01
- Title: Build registration wizard shell and store
- Owner: FE
- Depends on: S3-WP2-02
- Scope note: Create shared wizard frame and persisted step state.
- Acceptance:
  - Step progress persists on refresh.
  - Back/next navigation obeys validation gating.
- Evidence:
  - Wizard store and shell tests.
- Status: planned

### S3-WP5-02
- Title: Build registration wizard step components
- Owner: FE
- Depends on: S3-WP5-01
- Scope note: Implement all required Phase 1 registration steps with contextual help hooks.
- Acceptance:
  - Step forms capture required fields.
  - Help panel content renders per step key.
- Evidence:
  - Step-level component tests.
- Status: planned

### S3-WP5-03
- Title: Implement registration wizard submission endpoint
- Owner: BE
- Depends on: S3-WP2-02, S3-WP5-02
- Scope note: Receive validated wizard payload and persist AI system registration data.
- Acceptance:
  - Payload whitelist validation enforced.
  - Submission creates consistent records and audit entries.
- Evidence:
  - Submission integration tests.
- Status: planned

### S3-WP6-01
- Title: Implement document upload/list/download/delete APIs
- Owner: BE
- Depends on: S3-WP1-01
- Scope note: Add secure document endpoints and entity linkage.
- Acceptance:
  - File type/size checks enforced.
  - Download access follows RBAC and tenant rules.
- Evidence:
  - Document API security tests.
- Status: planned

### S3-WP6-02
- Title: Implement document storage and naming policy
- Owner: BE
- Depends on: S3-WP6-01
- Scope note: Store files outside web root with UUID-based on-disk names.
- Acceptance:
  - No direct public file URL access.
  - Original filename and metadata persisted correctly.
- Evidence:
  - Storage policy verification tests.
- Status: planned

### S3-WP6-03
- Title: Build document management UI
- Owner: FE
- Depends on: S3-WP6-01
- Scope note: Add upload/list/download/delete interactions in AI system context.
- Acceptance:
  - Upload progress and validation messages are visible.
  - Authorized users can download and delete within policy.
- Evidence:
  - UI integration tests.
- Status: planned

### S3-WP7-01
- Title: Add ai inventory and vendor tenant isolation tests
- Owner: QA
- Depends on: S3-WP2-01, S3-WP2-02
- Scope note: Verify cross-tenant list/detail/update access is blocked.
- Acceptance:
  - Tenant A cannot access tenant B vendor/system data.
  - List endpoints only return caller tenant records.
- Evidence:
  - Isolation test report.
- Status: planned

### S3-WP7-02
- Title: Add ai request workflow integration tests
- Owner: QA
- Depends on: S3-WP3-01, S3-WP3-02
- Scope note: Verify submit -> decision lifecycle and notifications.
- Acceptance:
  - Request state transitions match allowed workflow.
  - Unauthorized decision attempts are rejected.
- Evidence:
  - Workflow test suite report.
- Status: planned

### S3-WP7-03
- Title: Add document security and RBAC tests
- Owner: QA
- Depends on: S3-WP6-01, S3-WP6-02
- Scope note: Validate file restrictions, authorization, and tenant boundaries.
- Acceptance:
  - Invalid file types/sizes are rejected.
  - Cross-tenant downloads fail correctly.
- Evidence:
  - Security test results.
- Status: planned

### S3-WP7-04
- Title: Add registration wizard submission integration test
- Owner: QA
- Depends on: S3-WP5-03
- Scope note: Verify full frontend submission payload to backend persistence path.
- Acceptance:
  - Required data is persisted accurately.
  - Validation errors map correctly to UI fields.
- Evidence:
  - End-to-end test output.
- Status: planned

## Sprint 3 Milestone Check

### M3 (end Sprint 3)
- Vendor registry, AI inventory, and AI usage request flows are complete.
- Registration wizard is operational with validated submission.
- Document management is secure and tenant-isolated.
