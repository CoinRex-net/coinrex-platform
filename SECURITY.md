# Security Policy

## Supported Versions

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

We take the security of CoinRex seriously. If you believe you have found a security vulnerability, please report it to us as described below.

**Please do not report security vulnerabilities through public GitHub issues.**

Instead, please report them via email to **support@coinrex.xyz**.

You should receive a response within 48 hours. If for some reason you do not, please follow up via email to ensure we received your original message.

Please include the following information in your report:

- Type of issue (e.g., SQL injection, XSS, CSRF, authentication bypass)
- Full paths of source file(s) related to the issue
- The location of the affected source code (tag/branch/commit or direct URL)
- Any special configuration required to reproduce the issue
- Step-by-step instructions to reproduce the issue
- Proof-of-concept or exploit code (if possible)
- Impact of the issue, including how an attacker might exploit it

## Preferred Languages

We prefer all communications to be in English.

## Policy

- We will acknowledge receipt of your vulnerability report within 48 hours
- We will send a more detailed response within 72 hours indicating next steps
- We will keep you informed of the progress towards a fix
- We will notify you when the issue is resolved

## Security Best Practices

### For Production Deployments

1. **Environment Configuration**
   - Set `COINREX_ENV=production`
   - Set `COINREX_TESTING_MODE=false`
   - Generate strong random values for `COINREX_CSRF_KEY` and `COINREX_ENCRYPTION_KEY`
   - Use environment variables, never hardcode credentials

2. **Database**
   - Use a dedicated database user with minimal required privileges
   - Enable SSL for database connections in production
   - Regularly backup the database

3. **File System**
   - Restrict write permissions on runtime directories
   - Keep `vendor/` outside the web root if possible
   - Disable directory listing on upload directories

4. **PHP Configuration**
   - Disable `display_errors` in production
   - Set appropriate `open_basedir` restrictions
   - Enable `session.cookie_httponly` and `session.cookie_secure`

5. **Regular Maintenance**
   - Keep PHP and Composer dependencies updated
   - Review security signals and fraud events regularly
   - Rotate secrets periodically
   - Monitor access logs for suspicious activity

## Known Security Features

- bcrypt password hashing (cost factor 12)
- OTP-based email verification with expiry and cooldown
- CSRF token protection for admin endpoints
- Prepared statements for all database queries
- Input sanitization and output escaping
- Rate limiting and anti-farming controls
- Security signal monitoring
- Session security configuration
- Remember-me token rotation
