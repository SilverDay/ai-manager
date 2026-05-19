# New Migration

Creates a new database migration file.

## Usage

Invoke when asked to add or modify database tables.

## Steps

1. **Determine the next migration number**: list files in `backend/migrations/`, take the highest number, increment by 1.

2. **Create the file**: `backend/migrations/{NNN}_{descriptive_name}.sql`

3. **Write the SQL** following these rules:
   - `CREATE TABLE IF NOT EXISTS` for new tables.
   - `ALTER TABLE` for modifications to existing tables.
   - Include `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` on CREATE TABLE.
   - Include `tenant_id INT UNSIGNED` with FK to `tenants(id)` if tenant-scoped.
   - Include `created_at` and `updated_at` (except append-only tables).
   - Index all foreign key columns.
   - Add `UNIQUE KEY` constraints per spec.

4. **For destructive changes** (DROP COLUMN, DROP TABLE):
   - Add `-- DESTRUCTIVE MIGRATION` as the first line.
   - Only drop after confirming the code no longer references the column/table.
   - Consider adding the column/table back in a rollback comment block.

5. **Verify** by running `php scripts/migrate.php` against the dev database.

6. **Update `docs/database.md`** if the schema reference doc exists.

## Template

```sql
-- Migration: {NNN}_{name}
-- Description: {what this migration does}
-- Date: {YYYY-MM-DD}

CREATE TABLE IF NOT EXISTS {table_name} (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT UNSIGNED NOT NULL,
    -- columns here
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
