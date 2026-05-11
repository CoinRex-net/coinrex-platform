# CoinRex Database Documentation

## 1) Overview

- Primary database: `koinrex`
- Canonical full schema: `recreate_db.sql`
- Incremental updates: `database/migrations/*.sql`
- Engine: InnoDB (FK + index-heavy design)

## 2) Core Tables

### 2.1 Identity & Access

- `users`
  - Core user profile/auth/reward state
  - Includes OTP, login metadata, referral, level, wallet fields
- `admins`
  - Admin credentials and status
- `admin_activity_logs`
  - Auditable admin action history

### 2.2 Content & Moderation

- `projects`
  - Listed crypto projects, approval and feature status
- `reviews`
  - User reviews per project with proof, moderation, scoring
- `review_reactions`
  - Review reactions by users
- `content_flags`
  - Abuse/content flag records

### 2.3 Rewards & Claims

- `reward_ledger`
  - Reward event ledger with source/action/status
- `claim_snapshots`
  - Claim generation snapshots (amount + nonce + status)

### 2.4 Task System

- `mini_tasks`
  - Mini task definitions
- `user_task_logs`
  - Task completion/blocked records

### 2.5 Support/Comms

- `messages`
  - Admin-side message queue/status entries

### 2.6 Security & Anti-Abuse

- `user_security_signals`
  - hashed signal storage for IP/fingerprint/user-agent
  - used for pattern analysis (non-blocking by IP alone)
- `fraud_events`
  - structured security event journal
  - consumed by Admin Security Management page
- `users` security governance columns
  - `security_flagged`, `security_flag_reason`, `security_warning_count`, `security_suspended`
  - `taskhub_blocked_until`, `boosthub_blocked_until`, `review_blocked_until`

## 3) Relationship Summary

```text
users 1---* reviews *---1 projects
users 1---* reward_ledger
users 1---* claim_snapshots
users 1---* user_task_logs *---1 mini_tasks
users 1---* developer_verification
users 1---* content_flags
users 1---* review_reactions *---1 reviews

admins 1---* admin_activity_logs
admins 1---* reviews (reviewed_by/proof_verified_by)
admins 1---* projects (feature_reviewed_by)

users 1---* user_security_signals
users 1---* fraud_events (logical association by user_id/email/ip/fingerprint)
```

## 4) Schema Design Notes

- Strong unique constraints (email, username, referral code, tx hash, user+project review uniqueness).
- Reward and claim systems are append/transition friendly.
- Review table captures both moderation and proof verification states.

## 5) Security Management Migration Reference

Migration file:

- `database/migrations/2026_05_04_user_security_signals.sql`

Adds:

1. `user_security_signals`
2. `fraud_events`
3. security governance columns on `users`

Purpose:

- allow shared network usage while still detecting suspicious combined patterns
- provide auditable event stream for admin action
- enable module-specific enforcement windows

## 6) Runtime Schema Mutation (Risk)

From code analysis, helper functions may execute runtime `ALTER TABLE` logic when requests hit app paths:

- `ensureRememberMeSchema()`
- `ensureRewardClaimSchema()`

This pattern is risky in production and should be replaced by explicit migrations.

## 7) Migration Best-Practice Target

1. Keep schema changes only in `/database/migrations` scripts.
2. Use versioned migrations (`YYYYMMDDHHMM_description.sql`).
3. Run migrations via CI/deploy pipeline, not web requests.
4. Track applied versions in a `schema_migrations` table.

## 8) Recommended Additional DB Controls

- Add explicit check constraints where MySQL version allows.
- Add archival/partition strategy for high-growth tables (`reward_ledger`, `admin_activity_logs`).
- Add transaction boundaries for multi-step moderation/reward operations.

---

## Verification Checklist (Post-Migration)

- [ ] `user_security_signals` exists and is writable
- [ ] `fraud_events` exists and receives events
- [ ] all `users.security_*` columns exist
- [ ] `/admin/security-management.php` loads without SQL errors
- [ ] TaskHub/BoostHub/Review module blocks are enforceable by admin action
