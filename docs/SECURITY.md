# CoinRex Security Review

> **📌 Quick Reference:** For vulnerability reporting and security policy, see [SECURITY.md](../SECURITY.md) in the project root.

## Current Security Mechanisms

## 1) Password Security

- User and admin authentication uses PHP native hashing:
  - `password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])`
  - `password_verify(...)`
- This is a solid baseline for password storage.

## 2) OTP Security Controls

- Email verification and password reset OTP flows exist.
- Controls include:
  - OTP expiry
  - resend cooldown
  - maximum attempt counters
  - attempt increments on failure

## 3) Session Handling

- Session flags configured in `includes/config.php`:
  - `session.cookie_httponly = 1`
  - `session.use_only_cookies = 1`
- Session ID regeneration is done on successful user login (`session_regenerate_id(true)`).

## 4) CSRF Protection

- Admin forms include robust CSRF token generation and verification (`adminCsrfToken`, `requireAdminCsrf`).
- General app has CSRF helpers (`appCsrfToken`, `requireAppCsrf`) but usage appears partial/inconsistent across all state-changing routes.

## 5) Input and Data Handling

- Extensive use of prepared statements reduces SQL injection risk.
- Basic sanitization helper exists (`sanitize`) for output/input contexts.
- API helper enforces request method in several endpoints (`apiRequireMethod`).

## 6) Anti-Abuse Signals & Security Management (NEW)

- Security signal capture is active through:
  - `user_security_signals` (IP hash, fingerprint hash, user-agent hash)
  - `fraud_events` (auditable security events)
- Registration policy is now **risk-based**:
  - shared Wi-Fi / same IP is allowed,
  - account creation is not hard-blocked only by IP,
  - when a combined pattern is detected (same IP + same fingerprint), user is auto-flagged and event is logged.
- New admin workflow page:
  - `/admin/security-management.php`
  - admins can issue Warning, Suspend, and temporary module blocks.
- New user-level security controls:
  - `security_flagged`, `security_flag_reason`, `security_warning_count`, `security_suspended`
  - `taskhub_blocked_until`, `boosthub_blocked_until`, `review_blocked_until`
- Module-level enforcement is centralized via `enforceUserModuleAccess(...)` and applied to:
  - TaskHub task submission/completion
  - BoostHub task completion
  - Review submission access gate

---

## Key Security Risks Identified

## Critical

1. **Exposed credentials in local environment files or developer machines**
   - The repository now relies on environment variables, but any real `.env` or local mail credentials remain highly sensitive.
   - **Impact:** credential theft, account compromise, outbound email abuse.
   - **Action:** never publish real env files, and rotate any credential that may already have been exposed locally or historically.

2. **Development-mode settings enabled globally**
   - `display_errors=1`, `ENVIRONMENT='development'` as defaults.
   - **Impact:** sensitive error leakage in production.
   - **Action:** environment-specific config with safe production defaults.

3. **Insecure session cookie secure flag default**
   - `session.cookie_secure=0` in config.
   - **Impact:** cookie transmission over non-HTTPS in misconfigured deployments.
   - **Action:** set secure cookies true in production HTTPS.

## High

4. **Runtime schema mutation from web requests**
   - Functions like `ensureRememberMeSchema()` and `ensureRewardClaimSchema()` execute DDL (`ALTER TABLE`) in runtime paths.
   - **Impact:** privilege escalation risk, race conditions, deployment instability.
   - **Action:** move all schema changes to controlled migrations.

5. **Inconsistent CSRF enforcement on user/API state changes**
   - Some POST routes rely on session auth but do not consistently require CSRF tokens.
   - **Impact:** CSRF attack surface where browser cookies authenticate requests.
   - **Action:** enforce CSRF on all session-based state-changing endpoints.

## Medium

6. **Large centralized helper file increases audit complexity**
   - `includes/functions.php` is very large, making security review and change impact harder.
   - **Action:** split into domain services to improve secure maintenance.

7. **Operational controls improved but still maturing**
   - Structured security event logging now exists (`fraud_events`) with admin visibility.
   - **Action:** add threshold-based alerting/notifications and periodic review automation.

---

## Production Hardening Checklist

- [x] Remove default admin credentials from repository seed files.
- [ ] Remove hardcoded secrets from repository.
- [ ] Rotate SMTP/admin credentials already exposed.
- [ ] Introduce `.env` + strict config loader.
- [ ] Set `display_errors=0` and enable safe server logging.
- [ ] Force HTTPS and `session.cookie_secure=1` in prod.
- [ ] Configure `session.cookie_samesite` policy.
- [ ] Enforce CSRF tokens for all browser-authenticated POST/PUT/DELETE actions.
- [ ] Remove runtime DDL from request lifecycle.
- [ ] Add rate limiting to auth + OTP + API endpoints.
- [ ] Add audit logs for sensitive user/admin actions.
- [x] Add structured anti-abuse event logging (`fraud_events`) and admin review UI.
- [x] Add admin security actions (warning/suspend/temp module block).

---

## Recommended Security Improvements (Prioritized)

## Phase 1 (Immediate)

1. Secrets cleanup + credential rotation
2. Environment-safe production defaults
3. CSRF coverage audit and enforcement
4. Disable runtime schema auto-alter behavior

## Phase 2

1. Security middleware abstraction (authz/csrf/input checks)
2. Standardized exception handling without sensitive output
3. Add anti-automation and abuse throttles
4. Add automatic alerts/escalation rules on repeated combined patterns

---

## Migration + Rollout Notes (Security Management)

Run:

- `database/migrations/2026_05_04_user_security_signals.sql`

This migration creates/updates:

- `user_security_signals`
- `fraud_events`
- security control columns in `users`

Admin access points:

- New: `Admin -> Security Management`
- Dashboard security alert widget intentionally removed; security operations moved to dedicated page.

## Phase 3

1. Add automated security tests (auth/session/csrf)
2. Add dependency/security scanning in CI
3. Consider tokenized API auth for external clients
