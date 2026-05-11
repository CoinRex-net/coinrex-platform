# CoinRex Authentication Documentation

## User Authentication Flow

Primary files:

- `auth/auth.php`
- `auth/verify_email.php`
- `auth/forgot.php`
- `auth/logout.php`

Core helper dependency: `includes/functions.php`

## Signup Flow

1. User submits registration form (`auth/auth.php`, register tab).
2. Server validates:
   - required fields
   - email format and uniqueness
   - disposable email blocklist
   - password policy
   - optional referral code
3. `registerUser()` creates user, initializes reward ledger bonus records.
4. User is redirected to login flow.

## Login Flow

1. User submits email + password.
2. `loginUser()` verifies credentials and account status.
3. If email verification is pending, OTP flow is triggered and user is redirected to `verify_email.php`.
4. On success, `establishAuthenticatedSession()` sets user session context.

## Email Verification OTP Flow

1. OTP generated with `generateEmailVerificationOtp()`.
2. OTP and expiry stored in `users` (`otp_code`, `otp_expiry`, `otp_attempts`).
3. OTP sent by SMTP via PHPMailer.
4. `verify_email.php` validates OTP:
   - length
   - expiry
   - max attempts
5. On success:
   - `markEmailAsVerified()` updates user verification fields
   - authenticated session is established

## Password Reset Flow

1. User enters email/username in `auth/forgot.php`.
2. System resolves account and dispatches OTP.
3. User submits OTP (attempt/cooldown/expiry enforced).
4. On verification, user sets a new password.
5. `resetUserPassword()` updates password hash and clears OTP fields.

## Admin Authentication Flow

Primary files:

- `admin/login.php`
- `admin/logout.php`
- `admin/includes/auth.php`
- `admin/includes/config.php`

Flow:

1. Admin submits email/password to `admin/login.php`.
2. `adminLogin()` loads admin record and verifies `password_hash`.
3. Session keys are set (`admin_id`, `admin_email`, `admin_name`).
4. `requireAdminAuth()` guards protected admin pages.

## Session Handling

Global setup in `includes/config.php`:

- session starts when not disabled by constant
- cookie flags:
  - `httponly` enabled
  - only cookies enabled
  - secure flag currently not forced by environment

User session identity keys include:

- `user_id`, `username`, `email`, `role`, `level`

Admin session identity keys include:

- `admin_id`, `admin_email`, `admin_name`

## Remember-Me System

- Constants:
  - `REMEMBER_ME_COOKIE_NAME`
  - `REMEMBER_ME_LIFETIME_SECONDS`
- Schema fields in `users`:
  - `remember_token_hash`
  - `remember_token_expires_at`
- Issued/cleared during authenticated session establishment.

## CSRF in Auth System

- Admin auth flow: explicit CSRF token enforced.
- User auth pages: CSRF helper functions exist globally, but end-to-end use is not uniformly enforced for every state-changing action.

## Known Weaknesses / Risks

1. Inconsistent CSRF enforcement in user-side forms/API calls.
2. Sensitive environment settings and credentials in shared config.
3. Runtime schema auto-alter functions coupled to auth/session flow through shared bootstrap.

## Recommended Auth Improvements

- Enforce CSRF token for all non-idempotent user auth/profile actions.
- Move secrets and auth config to environment file.
- Add explicit account lockout/rate-limiting policy with logging.
- Separate auth services from monolithic helper file for easier review/testing.
