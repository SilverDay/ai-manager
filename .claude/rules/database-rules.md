# Database Rules

Applied when working with migrations, models, or any SQL.

## Multi-Tenancy

- Every tenant-scoped table has a `tenant_id INT UNSIGNED` column with a foreign key to `tenants(id)`.
- Global tables (no tenant_id): `tenants`, `roles`, `settings` (scope='global'), `regulatory_rule_versions`, `freemail_blocklist`, `legal_acceptances`, `refresh_tokens`.
- The `users` table has a NULLABLE `tenant_id` — NULL means Superadmin.
- The `audit_log` table has a NULLABLE `tenant_id` — NULL means platform-level action.

## Tenant-Scoped Tables (complete list)

`users`, `departments`, `ai_systems`, `ai_requests`, `ai_classifications`, `risk_assessments`, `risk_answers`, `controls`, `vendors`, `vendor_assessments`, `fria_assessments`, `documents`, `workflow_definitions`, `workflow_steps`, `approvals`, `approval_steps`, `audit_log`, `training_courses`, `training_records`, `notifications`, `tenant_domains`, `user_invitations`, `glossary_entries`.

## Migration Naming

Sequential numbered prefix, descriptive name, plain SQL:

```
001_create_tenants.sql
002_create_users_and_roles.sql
003_create_departments.sql
004_create_ai_systems.sql
005_create_ai_requests.sql
006_create_ai_classifications.sql
007_create_risk_assessments.sql
...
```

## Migration Rules

- Always include `IF NOT EXISTS` on CREATE TABLE.
- Always specify `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`.
- Always add `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- Add `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` on tables that get UPDATEd.
- Do NOT add `updated_at` on append-only tables (`audit_log`, `legal_acceptances`).
- Foreign keys: `FOREIGN KEY (column) REFERENCES parent_table(id)`. Use `ON DELETE CASCADE` only for join tables and child records that are meaningless without the parent (e.g., `risk_answers` → `risk_assessments`). Use `ON DELETE RESTRICT` (default) for everything else.
- Index all foreign key columns. MariaDB auto-indexes FK source columns, but add explicit indexes for query patterns: `INDEX idx_tenant_entity (tenant_id, entity_type, entity_id)`.

## Versioned Records Pattern

Used for: `ai_classifications`, `risk_assessments`, `fria_assessments`.

```sql
-- Creating a new version (always in a transaction):
START TRANSACTION;

UPDATE ai_classifications
SET is_current = 0
WHERE ai_system_id = ? AND tenant_id = ? AND is_current = 1;

INSERT INTO ai_classifications (
    tenant_id, ai_system_id, version, is_current, ...
) VALUES (
    ?, ?, (SELECT COALESCE(MAX(version), 0) + 1 FROM ai_classifications WHERE ai_system_id = ? AND tenant_id = ?), 1, ...
);

COMMIT;
```

Never UPDATE the content of a previous version. Only flip `is_current`.

## Query Patterns

```sql
-- Get current version of a record
SELECT * FROM ai_classifications
WHERE ai_system_id = ? AND tenant_id = ? AND is_current = 1;

-- Get specific historical version
SELECT * FROM ai_classifications
WHERE ai_system_id = ? AND tenant_id = ? AND version = ?;

-- Get version valid at a specific date
SELECT * FROM ai_classifications
WHERE ai_system_id = ? AND tenant_id = ? AND classified_at <= ?
ORDER BY version DESC LIMIT 1;
```

## Audit Log Insert Pattern

```sql
INSERT INTO audit_log (
    tenant_id, user_id, impersonated_by, action, entity_type, entity_id,
    previous_value, new_value, ip_address, user_agent
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?);
```

`previous_value` and `new_value` are JSON columns. Store the full relevant record snapshot, not a diff. This makes reconstruction trivial.
