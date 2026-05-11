# CoinRex Architecture

> GitHub documentation reference for system structure, boundaries, and runtime flows.

## 1) Architecture Style

CoinRex is a **modular monolith** in PHP:

- file-routed entry points (`*.php`)
- shared bootstrap (`includes/config.php`)
- domain helper/services in `includes/functions/*` and `includes/services/*`
- bounded surfaces for `auth`, `admin`, `api`, `devhub`, and public pages

## 2) High-Level Component Map

```text
Browser/User
   │
   ├─ Public pages (index.php, projects.php, dashboard.php, ...)
   ├─ Auth pages (/auth/*)
   ├─ Admin pages (/admin/*)
   └─ JS clients calling /api/*
        │
        ▼
PHP Entry Files
        │
        ├─ includes/config.php (env/session/constants/db bootstrap)
        ├─ includes/functions.php (core domain + helper logic)
        └─ admin/includes/* (admin auth/session/csrf wrapper)
        │
        ▼
MySQL (koinrex)
```

## 3) Request Flow (Text Diagram)

### User Authenticated Flow

```text
User -> auth/auth.php (register/login)
     -> functions: registerUser/loginUser
     -> users table + otp fields
     -> auth/verify_email.php (OTP verify)
     -> establishAuthenticatedSession
     -> dashboard.php
     -> task/review actions
     -> reward_ledger updates
```

### API Reward/Task Flow

```text
Client JS -> /api/*.php
         -> api/_bootstrap.php
         -> auth resolution (session/admin)
         -> functions.php domain calls
         -> JSON response envelope
```

### Admin Moderation Flow

```text
Admin -> /admin/login.php
      -> admin/includes/auth.php (adminLogin)
      -> /admin/dashboard.php and moderation pages
      -> project/review/user/reward DB updates
      -> admin_activity_logs
```

## 4) Module Breakdown

### 4.1 Auth System

- Files: `auth/auth.php`, `auth/verify_email.php`, `auth/forgot.php`, `auth/logout.php`
- Core helpers: user lookup, password hash/verify, OTP generation/storage/validation, session establishment.
- Supports:
  - registration
  - login with verification gate
  - OTP resend cooldown and max attempts
  - password reset OTP flow
  - remember-me token fields

### 4.2 Dashboard / User App

- Files: `dashboard.php`, `profile.php`, `projects.php`, `project-detail.php`, `submit-review.php`, etc.
- Responsibilities:
  - render user state and reward indicators
  - allow review submission and proof uploads
  - expose referral + level progression data

### 4.3 Task/Reward System

- DB-centric entities: `reward_ledger`, `claim_snapshots`, `mini_tasks`, `user_task_logs`.
- Business operations (in shared helpers):
  - add reward entries
  - compute balances by status (available/locked/pending/claimed)
  - complete mini tasks / TaskHub steps
  - generate claim snapshots and enforce eligibility

### 4.4 Admin Panel

- Files: `/admin/*`
- Shared admin bootstrap: `admin/includes/config.php` + `admin/includes/auth.php`
- Includes:
  - admin authentication and CSRF token checks
  - moderation for users/projects/reviews
  - reward, task, and settings administration
  - security management operations (`/admin/security-management.php`)

### 4.5 API Layer

- Entry files in `/api`
- Bootstrap: `api/_bootstrap.php`
- Standardized helper functions for:
  - method enforcement
  - auth actor resolution (admin/user)
  - input parsing/sanitization
  - unified JSON success/error responses

## 5) Dependency Flow

```text
Entry pages (/ , /auth, /admin, /api)
    -> includes/config.php
    -> includes/functions.php
    -> getDBConnection()
    -> MySQL tables
```

Additional admin dependency:

```text
/admin/* -> admin/includes/config.php -> admin/includes/auth.php
```

## 6) Data Flow (User → Auth → Dashboard → Tasks → Rewards)

1. User registers in `auth/auth.php`.
2. Account is persisted in `users`; OTP generated and stored.
3. User verifies OTP in `auth/verify_email.php`.
4. Session is established; user enters dashboard.
5. User completes mini tasks / TaskHub / review actions.
6. Reward entries are inserted into `reward_ledger`.
7. Balances and claim eligibility are computed for UI/API.
8. Claim generation creates `claim_snapshots` and transitions statuses.

## 7) Security Management Domain Flow (New)

```text
Signup/Login Activity
   -> user_security_signals (ip/fingerprint hashes)
   -> fraud_events (structured events)
   -> users.security_* state
   -> Admin Security Management page
   -> Action applied (warn/suspend/temp module block)
   -> Enforced in TaskHub/BoostHub/Review gates
```

Core enforcement helper:

- `enforceUserModuleAccess($user, $module)`

Admin action helper:

- `applySecurityActionToUser($user_id, $action, ...)`

## 8) Architectural Strengths

- Clear module directories.
- Reusable API bootstrap pattern.
- Strong DB relational foundation (FK constraints in schema SQL).
- Feature-rich reward and trust domain logic already present.

## 9) Architectural Constraints / Debt

- `includes/functions.php` is monolithic and very large.
- Page controllers and presentation are tightly coupled in many files.
- Runtime schema mutations inside request lifecycle increase operational risk.

---

## Related Docs

- [README.md](../README.md)
- [DATABASE.md](DATABASE.md)
- [SECURITY.md](SECURITY.md)
- [API.md](API.md)
