# AI Context for CoinRex

## Project Summary (AI-Optimized)

- **Name:** CoinRex
- **Type:** PHP modular monolith (file-routed)
- **Domain:** Crypto project review + reward ecosystem
- **Core flows:** auth → verify OTP → dashboard → tasks/reviews → reward ledger → claim snapshots
- **Persistence:** MySQL (`koinrex`)
- **Critical shared files:**
  - `includes/config.php`
  - `includes/functions.php`
  - `api/_bootstrap.php`

## Major Subsystems

1. **User App (root + /auth)**
2. **Admin Panel (/admin)**
3. **API Layer (/api)**
4. **DevHub (/devhub)**
5. **Database schema (`recreate_db.sql`)**

## Non-Negotiable Safety Rules

1. **Do not hardcode secrets** in code/config/commits.
2. **Do not disable auth/authorization checks** for convenience.
3. **Do not add runtime schema ALTER logic** in request paths.
4. **Do not bypass CSRF validation** on browser-authenticated state changes.
5. **Do not expose sensitive errors** to end users in production.

## Modification Constraints

- Preserve current behavior unless explicitly requested.
- Prefer additive/refactor-safe changes over destructive rewrites.
- Keep admin and user auth boundaries isolated.
- Maintain compatibility with existing DB schema and data.
- When changing reward logic, verify ledger balance and claim-state invariants.

## Architecture Constraints

- Existing pages are PHP entry points; avoid sudden routing paradigm changes.
- `includes/functions.php` is heavily shared: small edits can have cross-module impact.
- API endpoints depend on `api/_bootstrap.php` conventions and JSON envelope contracts.

## Security Rules for AI Agents

- Enforce prepared statements and strict input validation.
- Ensure session/cookie flags remain secure by environment.
- Add CSRF checks to new state-changing user/admin actions.
- Validate actor scope (`user_id`) for all API mutations.
- Avoid introducing side-effectful GET endpoints.

## Development Guidelines

1. Read related files before editing shared helpers.
2. Prefer isolated service extraction when touching large `functions.php` sections.
3. Keep commits atomic:
   - config/security
   - feature logic
   - docs/tests
4. Update docs when behavior/contracts change.

## How to Safely Extend the System

### Add a new API endpoint

1. Create endpoint under `/api`.
2. Require `_bootstrap.php`.
3. Enforce method and actor scope.
4. Validate and sanitize all input.
5. Return `apiSuccessResponse` / `apiErrorResponse` format.
6. Document endpoint in `docs/API.md`.

### Add a new task/reward rule

1. Identify affected helper functions and tables.
2. Keep ledger as source-of-truth for balances.
3. Wrap multi-step writes in DB transactions.
4. Add admin visibility and audit hooks when relevant.
5. Update `docs/DATABASE.md`, `docs/ARCHITECTURE.md`, and `docs/ROADMAP.md` if behavior changes.

### Add a new admin action

1. Require `requireAdminAuth()`.
2. Enforce admin CSRF validation on POST.
3. Log action in `admin_activity_logs` when sensitive.

## Known Hotspots

- `includes/functions.php` (size and coupling)
- `includes/config.php` (security defaults and secrets)
- runtime schema helper functions (`ensure*Schema`)

## Preferred Refactor Direction

- Extract service classes under `src/` with PSR-4 autoload.
- Keep adapter wrappers for backward compatibility.
- Introduce migration runner and remove runtime schema mutations.
