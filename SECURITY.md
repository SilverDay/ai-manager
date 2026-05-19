# Security Guidelines

This document outlines security practices and tools for the AI Governance Platform.

## Security Tools

### 1. Automated Security Auditing

#### Comprehensive Security Audit
```bash
# Run full security audit (includes dependency scan, secret detection, file permissions)
php scripts/security-audit.php

# Quiet mode (only show issues)
php scripts/security-audit.php --quiet
```

#### Dependency Security Scanning

**PHP/Backend:**
```bash
cd backend/

# Check for known vulnerabilities
composer audit

# Check dependencies and format output
composer security:audit-format

# Production dependencies only
composer security:check
```

**Node.js/Frontend:**
```bash
cd frontend/

# Check for vulnerabilities (moderate level and above)
npm run security:audit

# Production dependencies only
npm run security:check

# Fix automatically fixable vulnerabilities
npm run security:audit-fix
```

### 2. CI/CD Security Gates

Security checks are automatically run on every push and pull request:

- **Dependency vulnerability scanning** (PHP & Node.js)
- **Secret pattern detection**
- **File permission validation**
- **Configuration security checks**

#### Manual Security Workflows

Run security scans manually:

```bash
# Trigger security scan via GitHub Actions
gh workflow run security.yml

# Run specific scan type
gh workflow run security.yml -f scan_type=dependencies-only
gh workflow run security.yml -f scan_type=secrets-only
```

### 3. Development Security Practices

#### Pre-commit Checks
```bash
cd frontend/
npm run precommit  # Runs linting + security checks

cd backend/
composer check     # Runs validation + security + code quality
```

#### Dependency Management

**Keep Dependencies Updated:**
```bash
# Check for outdated packages
cd frontend/ && npm run deps:outdated
cd backend/ && composer deps:outdated

# Check for unused dependencies
cd frontend/ && npm run deps:check
```

**Validate Dependencies:**
```bash
cd backend/ && composer deps:validate
```

## Security Best Practices

### 1. Environment Configuration

✅ **DO:**
- Store all secrets in `.env` file (gitignored)
- Use strong, unique passwords (minimum 12 characters)
- Set appropriate file permissions (644 for files, 755 for directories)
- Use HTTPS in production
- Enable proper security headers

❌ **DON'T:**
- Commit `.env` files or secrets to version control
- Use default/weak passwords
- Make files world-writable
- Store secrets in code comments or configuration files
- Use production credentials in development

### 2. Code Security

✅ **DO:**
- Use prepared statements for all database queries
- Validate and sanitize all user input
- Implement proper authentication and authorization
- Log security events (failed logins, permission violations)
- Use established cryptographic libraries

❌ **DON'T:**
- Construct SQL queries with string concatenation
- Trust user input without validation
- Roll your own authentication/crypto
- Expose stack traces in API responses
- Use deprecated cryptographic algorithms

### 3. Dependency Security

✅ **DO:**
- Regularly update dependencies
- Use dependency lock files (`package-lock.json`, `composer.lock`)
- Audit dependencies for known vulnerabilities
- Remove unused dependencies
- Pin dependency versions in production

❌ **DON'T:**
- Use dependencies with known high-severity vulnerabilities
- Install packages globally when they should be project-specific
- Ignore dependency audit warnings
- Use wildcards in production dependency versions

## Security Incident Response

### 1. If Security Audit Fails

1. **Review the audit report** - identify specific issues
2. **Assess severity** - prioritize critical and high-severity issues
3. **Fix vulnerabilities** - update dependencies, patch code, fix configuration
4. **Re-run audit** - verify fixes resolved the issues
5. **Document changes** - update security notes if needed

### 2. If Dependency Vulnerabilities Found

```bash
# For Node.js
cd frontend/
npm audit fix              # Auto-fix if possible
npm audit --audit-level=high  # Review remaining issues
npm update                 # Update to latest versions

# For PHP
cd backend/
composer update           # Update to latest versions
composer audit            # Re-check after updates
```

### 3. If Secrets Detected

1. **Immediately revoke/rotate** any exposed credentials
2. **Remove secrets** from code and commit history if needed
3. **Update `.gitignore`** to prevent future exposure
4. **Audit access logs** for any unauthorized access
5. **Implement proper secret management** going forward

## Security Configuration Files

### Required Security Files

- `.gitignore` - Excludes sensitive files from version control
- `.env.example` - Template for environment configuration
- `SECURITY.md` - This security documentation (you're reading it)

### Security Headers

The application should set these HTTP security headers:

```
Content-Security-Policy: default-src 'self'
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Referrer-Policy: strict-origin-when-cross-origin
```

## Regular Security Maintenance

### Weekly
- [ ] Review dependency audit results
- [ ] Check for security updates

### Monthly
- [ ] Run comprehensive security audit
- [ ] Review and rotate non-critical credentials
- [ ] Update dependencies to latest stable versions

### Quarterly
- [ ] Security architecture review
- [ ] Penetration testing (if applicable)
- [ ] Security training updates
- [ ] Incident response plan review

## Contact

For security issues or questions, contact the development team or create an issue in the project repository.

**Remember:** When in doubt about security, ask! It's always better to be cautious.