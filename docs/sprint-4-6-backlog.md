# Sprint 4 to Sprint 6 Backlog (Execution Tickets)

Date: 2026-05-19
Source: docs/implementation-plan.md

Status values: planned | in-progress | done | blocked

## Sprint 4 (Weeks 9-10)

### S4-WP1-01
- Title: Deliver assessment schema migrations 016-021
- Owner: BE
- Depends on: Sprint 3 complete
- Scope note: Add tables for classifications, risk assessments, risk answers, controls, FRIA, and regulatory rule versions.
- Acceptance:
  - Migrations execute cleanly on fresh database.
  - FK and index definitions support tenant-scoped list/detail and version queries.
- Evidence:
  - Migration run output and schema verification notes.
- Status: planned

### S4-WP1-02
- Title: Validate versioning query patterns
- Owner: BE
- Depends on: S4-WP1-01
- Scope note: Confirm current/historical/version-at-date retrieval patterns for versioned entities.
- Acceptance:
  - Current version query returns exactly one active record.
  - Historical version retrieval is deterministic.
- Evidence:
  - Repository/model-level query tests.
- Status: planned

### S4-WP2-01
- Title: Implement classification evaluation service
- Owner: BE
- Depends on: S4-WP1-01
- Scope note: Evaluate answers against rule versions and produce category/obligation output.
- Acceptance:
  - Classification output includes rule version reference.
  - Deterministic input set yields deterministic category output.
- Evidence:
  - Classification service unit tests.
- Status: planned

### S4-WP2-02
- Title: Implement classification record versioning
- Owner: BE
- Depends on: S4-WP2-01
- Scope note: Insert new classification versions while preserving historical records.
- Acceptance:
  - New classification marks previous current row non-current.
  - Existing historical content is not overwritten.
- Evidence:
  - Versioning integration tests.
- Status: planned

### S4-WP2-03
- Title: Build classification wizard UI
- Owner: FE
- Depends on: S4-WP2-01
- Scope note: Create guided wizard flow from prohibited checks to obligations output.
- Acceptance:
  - Step progression and validation behavior are consistent.
  - Result screen clearly displays category and required actions.
- Evidence:
  - Wizard component tests and UX checklist.
- Status: planned

### S4-WP3-01
- Title: Implement risk scoring service
- Owner: BE
- Depends on: S4-WP1-01
- Scope note: Implement likelihood x impact scoring across defined risk categories.
- Acceptance:
  - Scoring formula is documented and test-covered.
  - Category totals and aggregates are reproducible.
- Evidence:
  - Risk scoring unit tests.
- Status: planned

### S4-WP3-02
- Title: Implement residual risk and control effectiveness logic
- Owner: BE
- Depends on: S4-WP3-01
- Scope note: Calculate post-control residual risk levels and status indicators.
- Acceptance:
  - Residual risk calculations match expected scenarios.
  - Control effectiveness influences final risk output.
- Evidence:
  - Service-level test matrix.
- Status: planned

### S4-WP3-03
- Title: Build risk assessment UI and matrix visualization
- Owner: FE
- Depends on: S4-WP3-01
- Scope note: Add assessment forms, scoring display, and risk matrix visualization.
- Acceptance:
  - Form supports all required categories and fields.
  - Matrix view reflects backend scoring data accurately.
- Evidence:
  - UI integration tests.
- Status: planned

### S4-WP4-01
- Title: Implement basic FRIA backend flow
- Owner: BE
- Depends on: S4-WP3-01
- Scope note: Integrate basic FRIA with affected-groups checklist and mitigation capture.
- Acceptance:
  - FRIA data persists as versioned records.
  - FRIA remains integrated within risk flow (Phase 1 scope).
- Evidence:
  - FRIA integration tests.
- Status: planned

### S4-WP4-02
- Title: Build FRIA UI integration in risk workflow
- Owner: FE
- Depends on: S4-WP4-01
- Scope note: Add FRIA sections to assessment flow and output views.
- Acceptance:
  - Users can capture FRIA inputs without leaving workflow context.
  - Output reflects rights-related risk and mitigations.
- Evidence:
  - UI flow test checklist.
- Status: planned

### S4-WP4-03
- Title: Implement controls checklist backend and UI
- Owner: BE/FE
- Depends on: S4-WP1-01
- Scope note: Add controls CRUD and checklist status tracking with evidence linkage.
- Acceptance:
  - Controls can be linked to AI systems and risk items.
  - Checklist state updates are tenant-scoped and audited.
- Evidence:
  - API tests and UI smoke tests.
- Status: planned

### S4-WP5-01
- Title: Add classification versioning tests
- Owner: QA
- Depends on: S4-WP2-02
- Scope note: Verify new versions do not overwrite historical classifications.
- Acceptance:
  - Historical version payloads remain unchanged after new version creation.
  - is_current semantics are correct.
- Evidence:
  - Test suite output.
- Status: planned

### S4-WP5-02
- Title: Add risk calculation verification tests
- Owner: QA
- Depends on: S4-WP3-02
- Scope note: Validate scoring and residual risk outputs for fixed inputs.
- Acceptance:
  - Calculation outputs match expected test vectors.
  - Invalid values return validation errors.
- Evidence:
  - Automated test reports.
- Status: planned

### S4-WP5-03
- Title: Add regulatory rule linkage tests
- Owner: QA
- Depends on: S4-WP2-01
- Scope note: Ensure classification records store and expose rule version metadata.
- Acceptance:
  - Rule version references appear in persisted and returned data.
  - Reclassification against updated rules creates new versions.
- Evidence:
  - Integration test output.
- Status: planned

### S4-WP5-04
- Title: Add FRIA basic workflow tests
- Owner: QA
- Depends on: S4-WP4-01
- Scope note: Verify capture, persistence, and retrieval of Phase 1 FRIA data.
- Acceptance:
  - FRIA records are tenant-isolated and versioned.
  - Unauthorized access is blocked.
- Evidence:
  - FRIA test report.
- Status: planned

## Sprint 4 Milestone Check

### M4 (end Sprint 4)
- Classification, risk, controls, and basic FRIA workflows are complete.
- Versioning behavior is validated for all versioned assessment entities.
- Rule-version traceability is available for audit use.

## Sprint 5 (Weeks 11-12)

### S5-WP1-01
- Title: Deliver workflow engine schema migrations 022-025
- Owner: BE
- Depends on: Sprint 4 complete
- Scope note: Add workflow definition/steps and approvals/approval steps tables.
- Acceptance:
  - Migrations run cleanly and preserve tenant indexing requirements.
  - Workflow records link correctly to governed entities.
- Evidence:
  - Migration and schema checks.
- Status: planned

### S5-WP1-02
- Title: Implement approval workflow initiation and progression
- Owner: BE
- Depends on: S5-WP1-01
- Scope note: Start workflows, advance steps, record decisions, and finalize status.
- Acceptance:
  - Sequential step enforcement is guaranteed.
  - Decision history is persisted and auditable.
- Evidence:
  - Approval engine integration tests.
- Status: planned

### S5-WP1-03
- Title: Implement rejection loop and return-to-initiator behavior
- Owner: BE
- Depends on: S5-WP1-02
- Scope note: Handle rejected steps and route back to initiator for rework.
- Acceptance:
  - Rejection path transitions are valid and recoverable.
  - Re-submission path resumes workflow correctly.
- Evidence:
  - Rejection loop test scenarios.
- Status: planned

### S5-WP1-04
- Title: Implement conditional step skipping and escalation timeout
- Owner: BE
- Depends on: S5-WP1-02
- Scope note: Skip steps based on AI category and escalate on timeout via scheduled worker.
- Acceptance:
  - Conditional skip logic is deterministic and test-covered.
  - Escalation triggers notifications and state updates.
- Evidence:
  - Conditional/timeout workflow tests.
- Status: planned

### S5-WP1-05
- Title: Build approval queue and timeline UI
- Owner: FE
- Depends on: S5-WP1-02
- Scope note: Add actionable approval queue and status timeline views.
- Acceptance:
  - Approvers can review and decide from queue.
  - Timeline shows step outcomes in chronological order.
- Evidence:
  - UI integration tests.
- Status: planned

### S5-WP2-01
- Title: Implement notification API and data model behavior
- Owner: BE
- Depends on: S5-WP1-02
- Scope note: Create/list/mark-read notifications with channel metadata.
- Acceptance:
  - Required event types generate notifications.
  - Tenant and user scoping enforced for reads/updates.
- Evidence:
  - Notification API tests.
- Status: planned

### S5-WP2-02
- Title: Implement email queue worker
- Owner: BE/DO
- Depends on: S5-WP2-01
- Scope note: Process unsent email notifications via cron schedule.
- Acceptance:
  - Queue retries/failure handling are logged.
  - Processed notifications are marked and not duplicated.
- Evidence:
  - Worker execution tests and logs.
- Status: planned

### S5-WP2-03
- Title: Build notification bell and list UI
- Owner: FE
- Depends on: S5-WP2-01
- Scope note: Add unread badge, notification list, and mark-read interaction.
- Acceptance:
  - Unread count updates correctly.
  - Mark-read changes persist after refresh.
- Evidence:
  - UI test runs.
- Status: planned

### S5-WP3-01
- Title: Implement dashboard widget APIs (Phase 1 set)
- Owner: BE
- Depends on: Sprint 4 complete
- Scope note: Expose KPI data for systems, high-risk, approvals, overdue reviews, open risks, missing evidence.
- Acceptance:
  - Widget queries respect tenant boundaries and pagination/filter constraints where applicable.
  - Response times align with targets or produce actionable profiling outputs.
- Evidence:
  - API performance and correctness tests.
- Status: planned

### S5-WP3-02
- Title: Build dashboard widget UI
- Owner: FE
- Depends on: S5-WP3-01
- Scope note: Render Phase 1 dashboard cards and link to detail views.
- Acceptance:
  - Widgets display correct counts/states.
  - Loading/error states are consistently handled.
- Evidence:
  - UI smoke tests.
- Status: planned

### S5-WP3-03
- Title: Implement reporting endpoints and export generation
- Owner: BE
- Depends on: S5-WP3-01
- Scope note: Add report data endpoints and CSV/XLSX/PDF export output.
- Acceptance:
  - Exported data matches report filters.
  - File generation path enforces authorization and audit logging.
- Evidence:
  - Reporting/export tests.
- Status: planned

### S5-WP3-04
- Title: Build reporting and export UI
- Owner: FE
- Depends on: S5-WP3-03
- Scope note: Add report views, filters, and export actions.
- Acceptance:
  - Users can generate and download each report type.
  - Export failures return clear recoverable feedback.
- Evidence:
  - UI integration tests.
- Status: planned

### S5-WP4-01
- Title: Implement global search API
- Owner: BE
- Depends on: S3/S4 domain APIs complete
- Scope note: Search across AI systems, vendors, risks, documents, users, and audit logs.
- Acceptance:
  - Search respects tenant scoping and role visibility.
  - Relevance and latency are acceptable for MVP target.
- Evidence:
  - Search endpoint test and benchmark report.
- Status: planned

### S5-WP4-02
- Title: Implement advanced filtering on list endpoints
- Owner: BE
- Depends on: S5-WP4-01
- Scope note: Add risk class, department, owner, date, vendor, and status filters.
- Acceptance:
  - Filters combine correctly without cross-tenant leakage.
  - Invalid filter values return validation errors.
- Evidence:
  - Filter matrix tests.
- Status: planned

### S5-WP4-03
- Title: Build search and filter UI patterns
- Owner: FE
- Depends on: S5-WP4-01, S5-WP4-02
- Scope note: Add global header search and list-page filter panels.
- Acceptance:
  - Query/filter state is preserved in navigation context.
  - Result sets update predictably.
- Evidence:
  - UI behavior tests.
- Status: planned

### S5-WP5-01
- Title: Implement CSV/XLSX import parsing and validation API
- Owner: BE
- Depends on: S3 core modules complete
- Scope note: Validate schema mapping, required fields, and row-level consistency for AI systems and vendors.
- Acceptance:
  - Row-level valid/warning/error classification is returned.
  - Invalid rows are excluded from commit stage.
- Evidence:
  - Import parser tests.
- Status: planned

### S5-WP5-02
- Title: Implement import preview and commit workflow
- Owner: BE
- Depends on: S5-WP5-01
- Scope note: Provide preview review and confirmed create behavior with summary output.
- Acceptance:
  - Created records enter allowed initial statuses only.
  - Import action is fully audit-logged.
- Evidence:
  - Import workflow integration tests.
- Status: planned

### S5-WP5-03
- Title: Provide import templates and admin import UI
- Owner: FE
- Depends on: S5-WP5-01
- Scope note: Add downloadable templates and upload/preview/confirm UI flow.
- Acceptance:
  - Templates include required columns and examples.
  - UI shows row-level import feedback clearly.
- Evidence:
  - UI smoke test with sample files.
- Status: planned

### S5-WP6-01
- Title: Implement legal acceptance enforcement on auth flow
- Owner: BE
- Depends on: Sprint 1 auth complete
- Scope note: Gate login continuation when required ToS/Privacy versions are not accepted.
- Acceptance:
  - Users with missing acceptance are prompted before app access.
  - Accepted versions are persisted with timestamp/user metadata.
- Evidence:
  - Auth + legal acceptance integration tests.
- Status: planned

### S5-WP6-02
- Title: Build legal acceptance modal and state handling
- Owner: FE
- Depends on: S5-WP6-01
- Scope note: Present policy version prompts and submission flow in UI.
- Acceptance:
  - Users can review and accept required documents.
  - Acceptance state updates unblock app navigation.
- Evidence:
  - UI acceptance flow tests.
- Status: planned

### S5-WP7-01
- Title: Add approval engine end-to-end tests
- Owner: QA
- Depends on: S5-WP1-02..S5-WP1-04
- Scope note: Validate full workflow including rejection and escalation paths.
- Acceptance:
  - All major workflow branches are test-covered.
  - Role authorization checks verified per step.
- Evidence:
  - E2E/integration test reports.
- Status: planned

### S5-WP7-02
- Title: Add notification and worker tests
- Owner: QA
- Depends on: S5-WP2-01, S5-WP2-02
- Scope note: Verify event emission, queue processing, and read state behavior.
- Acceptance:
  - Event-to-notification mapping is complete for required events.
  - Worker idempotency and retry behavior are validated.
- Evidence:
  - Notification test suite output.
- Status: planned

### S5-WP7-03
- Title: Add reporting and export correctness tests
- Owner: QA
- Depends on: S5-WP3-03
- Scope note: Verify report totals and export payload equivalence.
- Acceptance:
  - Report and export totals match source queries.
  - Export authorization constraints enforced.
- Evidence:
  - Report validation test output.
- Status: planned

### S5-WP7-04
- Title: Add import and legal acceptance tests
- Owner: QA
- Depends on: S5-WP5-02, S5-WP6-01
- Scope note: Verify import preview/commit behavior and legal acceptance gate enforcement.
- Acceptance:
  - Invalid import rows are rejected without partial corruption.
  - Login gating behavior matches policy version requirements.
- Evidence:
  - Integration test reports.
- Status: planned

## Sprint 5 Milestone Check

### M5 (end Sprint 5)
- Approval workflow, notifications, dashboard, reporting, search, import, and legal acceptance are complete.
- Full governance lifecycle is operational from submission to final approval.
- Test coverage includes rejection and escalation paths.

## Sprint 6 (Weeks 13-14)

### S6-WP1-01
- Title: Run full security hardening checklist
- Owner: SEC/BE/FE
- Depends on: Sprint 5 complete
- Scope note: Validate authz/authn, headers, upload controls, rate limiting, and injection/XSS protections.
- Acceptance:
  - High/Critical findings are fixed or formally accepted with owner and rationale.
  - Security header baseline confirmed on all API responses.
- Evidence:
  - Security checklist report.
- Status: planned

### S6-WP1-02
- Title: Execute dependency vulnerability remediation cycle
- Owner: DO/BE/FE
- Depends on: S6-WP1-01
- Scope note: Run composer/npm audits, patch vulnerable dependencies, and verify regressions.
- Acceptance:
  - No unresolved High/Critical dependency findings remain without explicit risk acceptance.
  - CI security stages pass.
- Evidence:
  - Audit outputs and dependency update changelog.
- Status: planned

### S6-WP2-01
- Title: Run API and workflow performance benchmarks
- Owner: BE/DO
- Depends on: Sprint 5 complete
- Scope note: Measure p95 latency for read/write/search/dashboard/classification flows.
- Acceptance:
  - Results compared against Section 19 targets.
  - Gaps produce concrete optimization actions.
- Evidence:
  - Benchmark report and metrics snapshots.
- Status: planned

### S6-WP2-02
- Title: Optimize database queries and indexes from profiling
- Owner: BE
- Depends on: S6-WP2-01
- Scope note: Tune slow endpoints and dashboard/search queries.
- Acceptance:
  - Identified slow queries improved to target or documented exception.
  - Query plans recorded for tuned statements.
- Evidence:
  - Before/after profiling artifacts.
- Status: planned

### S6-WP2-03
- Title: Validate frontend performance budgets
- Owner: FE
- Depends on: Sprint 5 complete
- Scope note: Ensure route transition and bundle-size targets are met.
- Acceptance:
  - No single JS bundle exceeds target budget.
  - Initial interactive load stays within target in baseline environment.
- Evidence:
  - Build and performance audit output.
- Status: planned

### S6-WP3-01
- Title: Implement and verify backup jobs
- Owner: DO
- Depends on: Sprint 5 complete
- Scope note: Configure DB and document backups with encryption and retention.
- Acceptance:
  - Scheduled jobs run and produce expected artifacts.
  - Backup encryption and key separation policy is enforced.
- Evidence:
  - Backup run logs and storage inventory.
- Status: planned

### S6-WP3-02
- Title: Execute full restore rehearsal
- Owner: DO/QA
- Depends on: S6-WP3-01
- Scope note: Restore database and documents to test environment and validate app integrity.
- Acceptance:
  - Restored environment is operational.
  - Tenant isolation and key workflows validated post-restore.
- Evidence:
  - Restore test report.
- Status: planned

### S6-WP3-03
- Title: Prepare deployment, rollback, and incident runbooks
- Owner: DO/PM
- Depends on: S6-WP3-02
- Scope note: Document procedures for release, rollback, and major incident response.
- Acceptance:
  - Runbooks are reviewed and executable by on-call team.
  - Rollback dry run is completed.
- Evidence:
  - Runbook review checklist.
- Status: planned

### S6-WP4-01
- Title: Finalize API and database documentation
- Owner: BE/PM
- Depends on: Sprint 5 complete
- Scope note: Align docs/api and docs/database with implemented behavior.
- Acceptance:
  - Endpoint and schema docs match released behavior.
  - Versioning and deprecation notes are present.
- Evidence:
  - Documentation review log.
- Status: planned

### S6-WP4-02
- Title: Run UAT across critical user journeys
- Owner: QA/PM
- Depends on: S6-WP1-01, S6-WP2-01, S6-WP3-02
- Scope note: Validate end-to-end workflows for all primary roles using realistic data.
- Acceptance:
  - UAT scenarios pass or issues are triaged with release decision impact.
  - Cross-role and tenant-isolation behavior confirmed.
- Evidence:
  - UAT execution report and signoff list.
- Status: planned

### S6-WP4-03
- Title: Complete release readiness review
- Owner: PM/SEC/DO
- Depends on: S6-WP4-02
- Scope note: Consolidate security, performance, QA, and operations evidence for go/no-go.
- Acceptance:
  - Gate G4 checklist is complete.
  - Release decision recorded with accountable approvers.
- Evidence:
  - Release readiness document.
- Status: planned

## Sprint 6 Milestone Check

### M6 (end Sprint 6)
- Security, performance, and operational readiness baselines are verified.
- Documentation and UAT are complete.
- MVP release decision can be made with auditable evidence.
