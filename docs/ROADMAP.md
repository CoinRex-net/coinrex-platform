# CoinRex Modernization Roadmap

## Phase 1 — Critical Fixes & Security Hardening

## Objectives

- Eliminate high-risk security exposures
- Stabilize production configuration behavior
- Remove dangerous runtime operational patterns

## Work Items

1. **Secrets & credential safety**
   - Remove hardcoded SMTP and sensitive defaults from repo.
   - Move to environment variables / `.env` loader.
   - Rotate exposed credentials (SMTP/admin where required).

2. **Production-safe configuration profile**
   - Introduce environment modes (`dev`, `staging`, `prod`).
   - Disable `display_errors` in production.
   - Force secure session cookie flags for HTTPS deployments.

3. **CSRF consistency pass**
   - Enforce CSRF on all browser-authenticated state-changing routes.
   - Standardize token check helper integration.

4. **Remove runtime schema mutation**
   - Decommission request-path `ALTER TABLE` logic.
   - Move all schema evolution to migration scripts.

5. **Baseline observability**
   - Add centralized app/security error logging.
   - Ensure sensitive errors do not leak to client responses.

---

## Phase 2 — Refactor & Maintainability

## Objectives

- Reduce technical debt
- Improve testability and ownership boundaries

## Work Items

1. **Modularize `includes/functions.php`**
   - Extract to domain modules/services:
     - AuthService
     - UserService
     - RewardService
     - TaskHubService
     - ReviewModerationService
     - SecurityService

2. **Adopt PSR-4 autoload structure**
   - Introduce namespaced classes under `src/`.
   - Keep backward-compatible wrapper functions during transition.

3. **Request validation layer**
   - Centralize and reuse input validation/sanitization.
   - Reduce duplicated inline `$_POST/$_GET` checks.

4. **Standardized error/response contracts**
   - API exception mapper
   - reusable response DTO pattern

5. **Migration discipline**
   - Formalize migration runner and `schema_migrations` table.

---

## Phase 3 — Scalability & Platform Evolution

## Objectives

- Prepare for growth, reliability, and future architecture flexibility

## Work Items

1. **Performance optimization**
   - Query profiling and index tuning for high-volume tables.
   - Introduce caching for expensive aggregate dashboard queries.

2. **API evolution**
   - Versioned API namespace (`/api/v1`).
   - Optional token/JWT mode for external client integrations.

3. **Asynchronous workloads**
   - Queue-based processing for emails, moderation side effects, and analytics.

4. **Automated test coverage**
   - Priority suites: auth, OTP, reward ledger transitions, claims, task progression.

5. **Optional framework migration strategy**
   - Evaluate incremental migration path to Laravel/Symfony while preserving DB and business logic.
   - Use strangler-pattern migration (module by module).

---

## Promotion Model Principles

### Featured = Earned
- Featured badge remains a trust asset.
- It cannot be purchased directly.
- It requires quality thresholds, stable review metrics, and admin approval.

### Priority Review = Paid speed layer
- Priority Review can be purchased to accelerate handling of an already eligible project.
- It affects queue speed only.
- It must not guarantee Featured approval.

### Sponsored = Paid visibility layer
- Sponsored placement is a visibility product.
- It should always be labeled clearly on public surfaces.
- Sponsored status must never imply trust-earned quality on its own.

### Trust-over-money rule
- Visibility can be monetized.
- Trust signals must remain evidence-based and review-driven.
