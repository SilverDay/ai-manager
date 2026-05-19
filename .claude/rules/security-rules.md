# Security Rules

Applied across the entire project. Non-negotiable.

## Authentication

- JWT access tokens: 15 min TTL, signed with HS256 using secret from `.env`.
- Refresh tokens: 7 days, stored as HttpOnly Secure SameSite=Strict cookie. Hashed in DB (SHA-256).
- On login: return access token in JSON body + set refresh token cookie.
- On refresh: validate refresh token hash against DB, check not revoked, issue new access token.
- On logout: revoke refresh token in DB, clear cookie.
- On user deactivation: bulk-revoke all user's refresh tokens.
- On tenant deactivation: bulk-revoke all tenant's refresh tokens.

## Input Validation

- Whitelist approach: only allow expected fields, reject anything else.
- Validate types, lengths, formats, enums at the Validator layer before reaching the Service.
- File uploads: check MIME type against whitelist (PDF, DOCX, XLSX, PNG, JPG, ZIP), check file size against configured max (default 50MB), check magic bytes (not just extension).
- Never trust `Content-Type` header alone for file type validation.

## SQL Injection

- Every SQL query uses PDO prepared statements with `?` placeholders.
- Never concatenate variables into SQL strings. No exceptions.
- Never use user input in `ORDER BY` or `LIMIT` without a whitelist check.

## XSS

- API responses are JSON only. No HTML rendering from the backend.
- Frontend: Vue's template system auto-escapes by default. Never use `v-html` with user-provided content.
- Set `Content-Type: application/json` on all API responses.

## Security Headers

Set these on every response (in middleware or Apache config):

```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: strict-origin-when-cross-origin
Strict-Transport-Security: max-age=63072000; includeSubDomains
```

## Secrets

- All secrets in `.env` file, never in code.
- `.env` is in `.gitignore`.
- `.env.example` has placeholder values only.
- JWT secret must be at least 256 bits (32 bytes).
- Database passwords must be unique per environment.

## File Storage

- Files stored outside web root: `/var/app/storage/documents/{tenant_id}/{entity_type}/{entity_id}/`
- Files served only through the API with RBAC check (`GET /api/v1/documents/{id}/download`).
- Files encrypted at rest (AES-256 via filesystem encryption or application wrapper).
- Original filenames stored in DB; on-disk filenames are UUIDs to prevent path traversal.

## Rate Limiting

- Auth endpoints (`/api/v1/auth/*`): 10 requests per minute per IP.
- API read endpoints: 120 requests per minute per user.
- API write endpoints: 60 requests per minute per user.
- File upload: 10 requests per minute per user.
- Configurable in `.env`.
