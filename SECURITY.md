# 🔐 Security Policy

## Supported Versions

We actively support the latest `main` branch and the most recent tagged release.

| Version | Supported |
|---------|------------|
| main | ✅ Yes |
| Latest Release | ✅ Yes |
| Older Versions | ❌ No |

---

## Reporting a Vulnerability

If you discover a security vulnerability, **please do not open a public issue**.

Instead, report it privately:

- Email: chase+layup+security@crumbls.com  
- Or open a **private security advisory** via GitHub

Please include:

- A description of the issue
- Steps to reproduce
- Potential impact
- Suggested mitigation (if known)

You will receive an acknowledgment within **48 hours**.

We aim to resolve confirmed vulnerabilities within **24 hours** whenever possible.

---

## Security Practices

### 1. Dependency Management

- Composer dependencies are kept up to date.
- `composer audit` is run regularly.
- Dependabot alerts are enabled.
- NPM packages are monitored via `npm audit`.

---

### 2. Secrets & Environment Security

- `.env` files are **never committed**
- Production secrets are stored in:
  - Server environment variables
  - Secure CI/CD secret storage
- `APP_DEBUG=false` in production
- `APP_KEY` is rotated if compromise is suspected

---

### 3. Authentication & Authorization

- Sensitive routes use middleware protection
- CSRF protection is enabled (Laravel default)
- Passwords are hashed using Laravel’s hashing (bcrypt / argon2)
- Authorization policies are enforced for resource updates

---

### 4. Input Validation & Output Escaping

- All inputs validated via Laravel Form Requests
- Blade templates use escaped output by default (`{{ }}`)
- No raw `{!! !!}` output without proper sanitization
- File uploads validated for:
  - MIME type
  - Size limits
  - Secure storage location

---

### 5. Page Builder Hardening

Because this project renders dynamic content:

- User-generated HTML is sanitized
- No arbitrary PHP execution
- Components/blocks are whitelist-based
- Stored templates are escaped unless explicitly trusted
- Dynamic rendering is sandboxed where applicable

---

### 6. Server-Level Protections

- HTTPS enforced
- Security headers enabled:
  - Content-Security-Policy
  - X-Frame-Options
  - X-Content-Type-Options
- Rate limiting enabled on:
  - Login
  - API routes
  - Password reset endpoints

---

### 7. GitHub Security Controls

- Branch protection enabled on `main`
- Pull request review required before merge
- CI tests must pass before merge
- No direct pushes to protected branches

---

### 8. Deployment Security

- Production builds performed via CI/CD
- No local development keys in production
- Debug tools (Telescope, Debugbar) disabled in production
- Logs monitored for suspicious activity

---

## Security Update Process

When a vulnerability is confirmed:

1. A patch is created in a private branch
2. A security advisory is prepared
3. A patched release is tagged
4. Public disclosure occurs after the patch is available

---

## Additional Recommendations

For production or SaaS deployments, consider:

- Implementing CSP nonce strategies
- Disabling inline scripts in production
- Using signed URLs for sensitive builder actions
- Adding activity logging for builder edits
- Maintaining an audit trail for template changes

---

Thank you for helping keep this project secure.
