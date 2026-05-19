# Security Review

Performs a security review of code changes against the project's security rules.

## Usage

Invoke when asked to review code for security issues, or before committing sensitive code (auth, file uploads, user input handling, SQL queries).

## Checklist

### Authentication & Session
- [ ] JWT tokens validated on every protected endpoint (AuthMiddleware in chain)
- [ ] Refresh token is HttpOnly, Secure, SameSite=Strict cookie
- [ ] Refresh tokens hashed (SHA-256) before storage
- [ ] Token revocation on logout, password change, account deactivation
- [ ] Superadmin endpoints require `is_superadmin` check, not just role
- [ ] MFA enforced for Superadmin accounts

### Authorisation
- [ ] RBAC middleware checks role against route requirements
- [ ] Tenant isolation: every query includes `WHERE tenant_id = ?`
- [ ] No direct object references without ownership check
- [ ] Superadmin impersonation logged with `impersonated_by`

### Input Validation
- [ ] All input validated in Validator class (whitelist approach)
- [ ] No user input in SQL without prepared statement binding
- [ ] No user input in `ORDER BY` / `LIMIT` without whitelist
- [ ] File uploads: MIME whitelist, size limit, magic byte check
- [ ] No `v-html` with user content on frontend

### Data Protection
- [ ] Passwords hashed with bcrypt (cost ≥ 12)
- [ ] No secrets in code, logs, or error responses
- [ ] No stack traces in API responses
- [ ] Sensitive fields (password_hash, mfa_secret) never returned in API responses
- [ ] Audit log entries include previous and new values (for change tracking)
- [ ] User deletion anonymises PII, preserves audit trail

### Headers & Transport
- [ ] HTTPS enforced
- [ ] Security headers set (CSP, X-Content-Type-Options, X-Frame-Options, HSTS, Referrer-Policy)
- [ ] CORS restricted to allowed origins
- [ ] API responses set `Content-Type: application/json`

### Files
- [ ] Upload directory outside web root
- [ ] On-disk filenames are UUIDs (no user-controlled paths)
- [ ] File downloads require authentication + RBAC check
- [ ] Files encrypted at rest

### Rate Limiting
- [ ] Auth endpoints rate-limited (10/min/IP)
- [ ] API endpoints rate-limited (120 read, 60 write per min per user)
- [ ] File upload rate-limited (10/min/user)

## Output

Report findings as:
- **CRITICAL**: Must fix before merge (SQL injection, auth bypass, data leak)
- **HIGH**: Should fix before merge (missing validation, weak rate limit)
- **MEDIUM**: Fix soon (missing security header, verbose error)
- **LOW**: Improve when convenient (code style, defensive coding)
