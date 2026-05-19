# AI Governance & EU AI Act Compliance Platform

Multi-tenant SaaS platform for managing AI systems under the EU AI Act. Vue 3 frontend + PHP 8.3 REST API + MariaDB.

## Working Principles

1. Don't assume. Don't hide confusion. Surface tradeoffs.
2. Minimum code that solves the problem. Nothing speculative.
3. Touch only what you must. Clean up only your own mess.
4. Define success criteria. Loop until verified.

## Spec

The full software specification is in `docs/specification.md` (~2100 lines). Read it before making architectural decisions. Key sections: Section 8 (database model + multi-tenancy), Section 9 (API design + JWT auth), Section 7 (all functional modules).

## Tech Stack

- **Frontend:** Vue 3, Vite, Pinia, Vue Router, Tailwind CSS, Axios, vue-i18n
- **Backend:** PHP 8.3+, Composer, Apache 2.4, REST API (no framework — custom router + middleware stack)
- **Database:** MariaDB 10.11+, InnoDB, UTF-8mb4
- **Infrastructure:** Ubuntu LTS, HTTPS mandatory

## Project Structure

```
aigov-platform/
├── frontend/                # Vue 3 + Vite app
│   ├── src/
│   │   ├── components/      # Reusable Vue components
│   │   ├── views/           # Page-level components (route targets)
│   │   ├── composables/     # Vue composables (shared logic)
│   │   ├── stores/          # Pinia stores
│   │   ├── router/          # Vue Router config
│   │   ├── api/             # Axios API client + endpoint modules
│   │   ├── i18n/            # vue-i18n locale files
│   │   ├── utils/           # Helper functions
│   │   └── types/           # TypeScript-style JSDoc type definitions
│   ├── public/
│   ├── index.html
│   ├── vite.config.js
│   ├── tailwind.config.js
│   └── package.json
├── backend/
│   ├── public/              # Apache document root (index.php entry point only)
│   ├── src/
│   │   ├── Controllers/     # Request handlers, one per resource
│   │   ├── Middleware/       # Auth, RBAC, tenant injection, rate limiting, CORS
│   │   ├── Models/          # Data access layer (one per table, no ORM)
│   │   ├── Services/        # Business logic layer
│   │   ├── Validators/      # Request validation (whitelist approach)
│   │   ├── Helpers/         # Utility functions
│   │   └── Routes/          # Route definitions
│   ├── config/              # Environment-specific config loader
│   ├── migrations/          # Numbered SQL migration files (001_create_tenants.sql, etc.)
│   ├── seeds/               # Seed data (roles, freemail blocklist, initial regulatory rules)
│   ├── tests/               # PHPUnit tests
│   ├── composer.json
│   └── .env.example
├── docs/
│   ├── specification.md     # Full software specification
│   ├── api.md               # API endpoint reference (generated from spec Section 9.3)
│   └── database.md          # Schema reference (generated from spec Section 8.2)
├── scripts/
│   ├── migrate.php          # Run pending migrations
│   ├── seed.php             # Run seed data
│   └── provision-superadmin.php  # CLI: create initial Superadmin
├── .env.example
├── docker-compose.yml       # Local dev environment (optional)
└── CLAUDE.md
```

## Key Architectural Decisions

- **No PHP framework.** Custom lightweight router + middleware stack. Keeps it simple, auditable, no magic. Every request flows: `index.php → Router → Middleware chain → Controller → Service → Model → Response`.
- **No ORM.** Models use PDO with prepared statements directly. Query builders are fine, but no Eloquent/Doctrine. We need full SQL control for tenant isolation and audit logging.
- **Row-level tenant isolation.** Every tenant-scoped table has `tenant_id`. The `TenantMiddleware` extracts tenant from JWT and injects it. ALL queries must include `WHERE tenant_id = ?`. Never skip this.
- **JWT auth with refresh tokens.** Access tokens 15min, refresh tokens 7 days (HttpOnly cookie). See spec Section 9.1.
- **Versioned assessments.** Classifications, risk assessments, FRIAs create new rows with incremented `version` + `is_current` flag. Never UPDATE old versions.
- **Append-only audit log.** The DB user for the app has INSERT + SELECT only on `audit_log`. No UPDATE/DELETE ever.

## Commands

```bash
# Frontend
cd frontend && npm install
npm run dev                    # Vite dev server (port 5173)
npm run build                  # Production build
npm run lint                   # ESLint + Prettier check

# Backend
cd backend && composer install
php -S localhost:8080 -t public  # Local PHP dev server
./vendor/bin/phpunit             # Run tests

# Database
php scripts/migrate.php          # Run pending migrations
php scripts/seed.php             # Seed roles, blocklist, rules
php scripts/provision-superadmin.php --email admin@example.com  # First-time setup

# Full local stack (Docker)
docker-compose up -d             # MariaDB + Apache + PHP + Node
```

## Code Style

### PHP
- PSR-12 coding standard.
- Strict types: every PHP file starts with `declare(strict_types=1);`
- Type hints on all function parameters and return types. No `mixed` unless truly necessary.
- Classes are final by default. Open for extension only when documented.
- Namespace: `App\Controllers`, `App\Models`, `App\Services`, `App\Middleware`, `App\Validators`.
- Error responses use the standard envelope: `{"success": false, "data": null, "errors": [...], "meta": {...}}`.

### Vue / JavaScript
- Composition API only (no Options API).
- `<script setup>` syntax for all components.
- Pinia stores use setup syntax (`defineStore('name', () => { ... })`).
- Component naming: PascalCase files (`AiSystemList.vue`), kebab-case in templates (`<ai-system-list />`).
- All API calls go through `frontend/src/api/` modules — components never call Axios directly.

### SQL
- Migration files are plain SQL, numbered sequentially: `001_create_tenants.sql`, `002_create_users.sql`.
- Table names: snake_case, plural (`ai_systems`, `risk_assessments`).
- Column names: snake_case (`tenant_id`, `created_at`).
- Every table gets `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`.
- Foreign keys named: `fk_{table}_{column}` (e.g., `fk_users_tenant_id`).

## Critical Rules

- **NEVER write a query without `tenant_id` filtering** on tenant-scoped tables. If you're unsure whether a table is tenant-scoped, check spec Section 8.1.
- **NEVER update or delete rows in `audit_log`.** It's append-only.
- **NEVER store secrets in code.** All secrets go in `.env`. The `.env` file is gitignored.
- **NEVER return PHP stack traces in API responses.** Log internally, return generic error to client.
- **NEVER construct SQL dynamically.** Always use prepared statements with parameter binding.
- **NEVER skip input validation.** Every controller action validates input via a Validator class before touching the Service layer.
- **Assessment versioning:** when creating a new classification/risk assessment/FRIA, always INSERT a new row with `version = max(version) + 1` and set `is_current = 1` on the new row + `is_current = 0` on previous, in a single transaction.

## Testing

- Backend: PHPUnit. One test class per Controller, one per Service. Test tenant isolation explicitly (verify that tenant A cannot see tenant B's data).
- Frontend: Vitest for unit tests, Cypress for E2E (Phase 2).
- Every API endpoint must have at least: happy path test, auth failure test, validation failure test, tenant isolation test.

## Gotchas

- The `users.tenant_id` is nullable — NULL means Superadmin (platform-level). Don't assume it's always set.
- The `audit_log.tenant_id` is also nullable — NULL means platform-level action.
- Refresh token is an HttpOnly cookie, not in the JSON response body. The access token IS in the JSON body.
- `account_status` enum on users: `pending_approval`, `active`, `rejected`, `deactivated`, `deleted`. Only `active` users can log in.
- File uploads go to a non-web-accessible directory. Never serve files directly — always through an API endpoint with RBAC check.
