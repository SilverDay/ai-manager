# Sprint 0 and Sprint 1 Backlog (Execution Tickets)

Date: 2026-05-19
Source: docs/implementation-plan.md

Status values: planned | in-progress | done | blocked

## Sprint 0 (Weeks 1-2)

### S0-WP1-01
- Title: Initialize project structure
- Owner: DO
- Depends on: none
- Scope note: Align repository layout with CLAUDE.md target structure.
- Acceptance:
  - Required top-level directories exist.
  - Frontend/backend/docs/scripts paths match agreed structure.
- Evidence:
  - Directory tree snapshot in PR description.
- Status: done

### S0-WP1-02
- Title: Scaffold frontend baseline
- Owner: FE
- Depends on: S0-WP1-01
- Scope note: Create Vue 3 + Vite + Pinia + Router + Tailwind + i18n baseline.
- Acceptance:
  - Frontend builds and runs in dev mode.
  - Lint command executes successfully.
- Evidence:
  - CI log for frontend lint/build.
- Status: done

### S0-WP1-03
- Title: Scaffold backend baseline
- Owner: BE
- Depends on: S0-WP1-01
- Scope note: Create PHP backend skeleton with Composer autoloading and public entrypoint.
- Acceptance:
  - Backend starts locally.
  - Health endpoint returns standard envelope.
- Evidence:
  - API response sample in PR.
- Status: done

### S0-WP1-04
- Title: Define environment templates
- Owner: DO
- Depends on: S0-WP1-01
- Scope note: Complete .env.example with required keys for app, db, auth, mail, and storage.
- Acceptance:
  - All required keys documented.
  - Fresh clone setup requires no hidden variables.
- Evidence:
  - Setup run notes.
- Status: done

### S0-WP1-05
- Title: Configure local docker stack
- Owner: DO
- Depends on: S0-WP1-03
- Scope note: Provision app runtime services for local development and testing.
- Acceptance:
  - Docker stack boots cleanly.
  - App and DB are reachable.
- Evidence:
  - Container status output in PR notes.
- Status: done

### S0-WP2-01
- Title: Implement API router core
- Owner: BE
- Depends on: S0-WP1-03
- Scope note: Build method/path matching, parameter extraction, and middleware dispatch.
- Acceptance:
  - Route resolution supports params and methods.
  - Middleware chain runs deterministically.
- Evidence:
  - Unit/integration tests for route dispatch.
- Status: done

### S0-WP2-02
- Title: Implement request abstraction
- Owner: BE
- Depends on: S0-WP2-01
- Scope note: Standardize access to query, params, body, and context fields.
- Acceptance:
  - Request body parsing handles JSON safely.
  - user and tenant context slots available.
- Evidence:
  - Request parsing tests.
- Status: done

### S0-WP2-03
- Title: Implement response envelope helpers
- Owner: BE
- Depends on: S0-WP2-02
- Scope note: Add success/error helpers consistent with spec section 9.2.
- Acceptance:
  - Every response conforms to envelope contract.
  - Error responses include standardized fields.
- Evidence:
  - Contract test snapshots.
- Status: done

### S0-WP2-04
- Title: Implement global error handler
- Owner: BE
- Depends on: S0-WP2-03
- Scope note: Map known exceptions to HTTP statuses and error envelope.
- Acceptance:
  - Exception-to-status mapping matches agreed list.
  - Unexpected errors are handled safely.
- Evidence:
  - Integration tests for exception mapping.
- Status: done

### S0-WP2-05
- Title: Implement config loader and DB connector
- Owner: BE
- Depends on: S0-WP1-04
- Scope note: Load environment config and initialize PDO connection safely.
- Acceptance:
  - Config defaults and overrides work.
  - DB connection failures handled gracefully.
- Evidence:
  - Boot diagnostics and connection tests.
- Status: done

### S0-WP3-01
- Title: Create migration runner
- Owner: BE
- Depends on: S0-WP2-05
- Scope note: Run numbered SQL migrations with tracking table.
- Acceptance:
  - Migrations execute idempotently.
  - Applied migration history persists.
- Evidence:
  - Migration run log.
- Status: done

### S0-WP3-02
- Title: Deliver base schema migrations 001-011
- Owner: BE
- Depends on: S0-WP3-01
- Scope note: Implement tenants, users/roles, domains, invitations, auth/session, audit, settings, legal acceptance tables.
- Acceptance:
  - Schema matches Phase 1 baseline.
  - FK/index constraints present for tenant and lifecycle queries.
- Evidence:
  - Schema diff and migration test run.
- Status: done

### S0-WP3-03
- Title: Create seed runner and initial seed data
- Owner: BE
- Depends on: S0-WP3-02
- Scope note: Seed role catalog, blocklist, defaults, and bootstrap superadmin flow.
- Acceptance:
  - Seeding is repeatable and safe.
  - Required baseline records exist after seed.
- Evidence:
  - Seed run report.
- Status: done

### S0-WP4-01
- Title: Enable CI lint and test gates
- Owner: DO
- Depends on: S0-WP1-02, S0-WP1-03
- Scope note: Add CI checks for frontend/backend lint and tests.
- Acceptance:
  - CI runs on push.
  - Pipeline fails on lint/test errors.
- Evidence:
  - CI workflow run links.
- Status: done

### S0-WP4-02
- Title: Add dependency security scans
- Owner: DO
- Depends on: S0-WP4-01
- Scope note: Integrate composer audit and npm audit into CI.
- Acceptance:
  - Security scan stage executes automatically.
  - High/Critical findings are surfaced as failing conditions.
- Evidence:
  - CI security stage output.
- Status: done

## Sprint 1 (Weeks 3-4)

### S1-WP1-01
- Title: Implement login/logout/refresh endpoints
- Owner: BE
- Depends on: S0-WP2-05, S0-WP3-02
- Scope note: Build JWT access token and refresh token lifecycle endpoints.
- Acceptance:
  - Login returns access token and sets refresh cookie.
  - Logout revokes refresh token and clears cookie.
- Evidence:
  - Auth integration test suite.
- Status: planned

### S1-WP1-02
- Title: Implement password reset flow
- Owner: BE
- Depends on: S1-WP1-01
- Scope note: Add forgot-password and reset-password APIs with token handling.
- Acceptance:
  - Reset tokens are validated and expire correctly.
  - Reset invalidates old credentials and sessions where applicable.
- Evidence:
  - Reset flow integration tests.
- Status: planned

### S1-WP1-03
- Title: Add superadmin MFA enforcement path
- Owner: BE
- Depends on: S1-WP1-01
- Scope note: Introduce MFA-ready verification requirement for superadmin logins.
- Acceptance:
  - Superadmin login blocked until MFA verification completes.
  - Non-superadmin flow unchanged.
- Evidence:
  - Role-specific auth tests.
- Status: planned

### S1-WP2-01
- Title: Implement CORS middleware
- Owner: BE
- Depends on: S0-WP2-01
- Scope note: Enforce configured allowed origins and methods.
- Acceptance:
  - Preflight requests handled correctly.
  - Disallowed origins are blocked.
- Evidence:
  - Middleware test cases.
- Status: planned

### S1-WP2-02
- Title: Implement rate limiting middleware
- Owner: BE
- Depends on: S0-WP2-01
- Scope note: Add endpoint-category limits for auth/read/write/upload.
- Acceptance:
  - Limits configurable via env.
  - Exceeded requests return 429 with standard envelope.
- Evidence:
  - Rate-limit integration tests.
- Status: planned

### S1-WP2-03
- Title: Implement auth middleware
- Owner: BE
- Depends on: S1-WP1-01
- Scope note: Validate bearer token and attach authenticated user context.
- Acceptance:
  - Invalid/expired token rejected with 401.
  - Valid token attaches user identity to request.
- Evidence:
  - Middleware auth tests.
- Status: planned

### S1-WP2-04
- Title: Implement tenant middleware
- Owner: BE
- Depends on: S1-WP2-03
- Scope note: Resolve tenant context from authenticated token and enforce availability.
- Acceptance:
  - tenant_id is set for tenant users.
  - Superadmin null-tenant logic is explicit and tested.
- Evidence:
  - Tenant context tests.
- Status: planned

### S1-WP2-05
- Title: Implement RBAC middleware
- Owner: BE
- Depends on: S1-WP2-03
- Scope note: Enforce route role requirements.
- Acceptance:
  - Missing role returns 403.
  - Allowed roles access route successfully.
- Evidence:
  - RBAC matrix test suite.
- Status: planned

### S1-WP3-01
- Title: Implement domain-based registration
- Owner: BE
- Depends on: S1-WP2-01
- Scope note: Register new users for mapped tenant domains with pending approval status.
- Acceptance:
  - Unknown domain returns appropriate validation error.
  - New user enters pending_approval status.
- Evidence:
  - Registration endpoint tests.
- Status: planned

### S1-WP3-02
- Title: Implement invite-based registration
- Owner: BE
- Depends on: S1-WP3-01
- Scope note: Support tokenized invitation acceptance and account creation.
- Acceptance:
  - Invalid/expired invite token rejected.
  - Valid invite binds role/tenant correctly.
- Evidence:
  - Invite registration tests.
- Status: planned

### S1-WP3-03
- Title: Implement approval workflow endpoints
- Owner: BE
- Depends on: S1-WP3-01
- Scope note: Add pending list, approve, reject actions for admins.
- Acceptance:
  - Only authorized admin roles can approve/reject.
  - Approval transitions status to active.
- Evidence:
  - Approval workflow integration tests.
- Status: planned

### S1-WP3-04
- Title: Implement invitation management endpoints
- Owner: BE
- Depends on: S1-WP3-02
- Scope note: Create/list/resend/revoke invitations.
- Acceptance:
  - Invitation lifecycle changes are audit-logged.
  - Revoked invites cannot be used.
- Evidence:
  - Invitation API tests.
- Status: planned

### S1-WP3-05
- Title: Implement tenant domain management endpoints
- Owner: BE
- Depends on: S1-WP3-01
- Scope note: CRUD tenant domains for admin users.
- Acceptance:
  - Domain changes are tenant-scoped.
  - Duplicate/invalid domains are rejected.
- Evidence:
  - Domain CRUD tests.
- Status: planned

### S1-WP4-01
- Title: Build authentication pages
- Owner: FE
- Depends on: S1-WP1-01, S1-WP1-02
- Scope note: Login/register/invite/forgot/reset views with validation and error handling.
- Acceptance:
  - All auth pages submit valid payloads.
  - Error feedback is accessible and clear.
- Evidence:
  - UI test coverage and manual QA checklist.
- Status: planned

### S1-WP4-02
- Title: Implement auth store and API client interceptors
- Owner: FE
- Depends on: S1-WP1-01
- Scope note: Manage session state and automatic token attachment/refresh.
- Acceptance:
  - Access token injected on protected calls.
  - 401 refresh retry behavior works as designed.
- Evidence:
  - Store unit tests and integration checks.
- Status: planned

### S1-WP4-03
- Title: Implement route guards
- Owner: FE
- Depends on: S1-WP4-02
- Scope note: Redirect unauthenticated users and enforce role-based navigation.
- Acceptance:
  - Protected routes inaccessible without session.
  - Role-restricted views hidden/blocked correctly.
- Evidence:
  - Router guard tests.
- Status: planned

### S1-WP4-04
- Title: Build user administration views
- Owner: FE
- Depends on: S1-WP3-03, S1-WP3-04, S1-WP3-05
- Scope note: Pending approvals, invitations, and tenant domains management UI.
- Acceptance:
  - Admin can complete each lifecycle action from UI.
  - UI refreshes state correctly after actions.
- Evidence:
  - UI smoke test script.
- Status: planned

### S1-WP5-01
- Title: Create auth endpoint integration suite
- Owner: QA
- Depends on: S1-WP1-01..S1-WP3-05
- Scope note: Cover happy path, auth, authz, validation, tenant isolation, and not-found cases.
- Acceptance:
  - Required categories exist per endpoint.
  - CI executes suite consistently.
- Evidence:
  - Test report artifact.
- Status: planned

### S1-WP5-02
- Title: Create tenant isolation tests for user flows
- Owner: QA
- Depends on: S1-WP3-01..S1-WP3-05
- Scope note: Verify cross-tenant data access is blocked.
- Acceptance:
  - Tenant A cannot read/update tenant B resources.
  - List endpoints only return caller tenant data.
- Evidence:
  - Isolation suite report.
- Status: planned

### S1-WP5-03
- Title: Add frontend auth store tests
- Owner: QA/FE
- Depends on: S1-WP4-02
- Scope note: Validate session transitions, refresh behavior, and logout cleanup.
- Acceptance:
  - Store actions update state deterministically.
  - Token refresh edge cases are covered.
- Evidence:
  - Vitest report.
- Status: planned

## Sprint 0 and 1 Milestone Checks

### M0 (end Sprint 0)
- Platform can be bootstrapped from scratch.
- Core API infrastructure and schema baseline are in place.
- CI baseline is operational.

### M1 (end Sprint 1)
- Full authentication and user lifecycle is operational.
- Middleware security chain and RBAC enforcement are active.
- Test suites include tenant isolation and endpoint category coverage.
