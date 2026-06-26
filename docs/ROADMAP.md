# CoinRex Modernization Roadmap

## Phase 1 - Critical Fixes and Security Hardening

### Objectives

- Eliminate high-risk security exposures.
- Stabilize production configuration behavior.
- Remove dangerous runtime operational patterns.

### Work Items

1. **Secrets and credential safety**
   - Remove hardcoded SMTP and sensitive defaults from application code.
   - Move runtime secrets to environment variables.
   - Rotate exposed credentials where required.

2. **Production-safe configuration profile**
   - Use explicit environment modes such as `development`, `staging`, and `production`.
   - Disable `display_errors` in production.
   - Force secure session cookie flags for HTTPS deployments.

3. **CSRF consistency pass**
   - Enforce CSRF checks on browser-authenticated state-changing routes.
   - Standardize token generation and validation helpers.

4. **Remove runtime schema mutation**
   - Decommission request-path `ALTER TABLE` logic.
   - Move all schema evolution to migration scripts.

5. **Baseline observability**
   - Add centralized app and security error logging.
   - Ensure sensitive errors do not leak to client responses.

## Phase 2 - Refactor and Maintainability

### Objectives

- Reduce technical debt.
- Improve testability and ownership boundaries.

### Work Items

1. **Modularize shared helpers**
   - Continue extracting large helper files into domain modules and services.
   - Preserve wrapper functions during the transition where needed.

2. **Adopt PSR-4 classes for new logic**
   - Put new domain classes under `src/`.
   - Keep legacy entry points stable while internals are modernized.

3. **Request validation layer**
   - Centralize and reuse input validation and sanitization.
   - Reduce duplicated inline `$_POST` and `$_GET` checks.

4. **Standardized API responses**
   - Use reusable response helpers for JSON APIs.
   - Map validation and domain exceptions to consistent error payloads.

5. **Migration discipline**
   - Formalize migration ordering and execution.
   - Track applied migrations with a `schema_migrations` table.

## Phase 3 - Scalability and Platform Evolution

### Objectives

- Prepare for growth, reliability, and future architecture flexibility.

### Work Items

1. **Performance optimization**
   - Profile queries and add indexes for high-volume tables.
   - Cache expensive dashboard and aggregate queries.

2. **API evolution**
   - Expand the versioned API namespace under `/api/v1`.
   - Add token-based external integration options where needed.

3. **Asynchronous workloads**
   - Move email, moderation side effects, and analytics into queue-friendly flows.

4. **Automated test coverage**
   - Prioritize auth, OTP, reward ledger transitions, claims, and task progression.

5. **Optional framework migration strategy**
   - Evaluate an incremental migration path to Laravel or Symfony.
   - Preserve the database and business rules during any framework transition.

## Promotion Model Principles

### Featured = Earned

- Featured status remains a trust asset.
- It cannot be purchased directly.
- It requires quality thresholds, stable review metrics, and admin approval.

### Priority Review = Paid Speed Layer

- Priority Review can accelerate handling of an already eligible project.
- It affects queue speed only.
- It must not guarantee Featured approval.

### Sponsored = Paid Visibility Layer

- Sponsored placement is a visibility product.
- It must always be labeled clearly on public surfaces.
- Sponsored status must never imply trust-earned quality on its own.

### Trust-Over-Money Rule

- Visibility can be monetized.
- Trust signals must remain evidence-based and review-driven.
