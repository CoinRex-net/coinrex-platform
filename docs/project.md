# Project: Review Submission Verification — Handoff Notes

This document summarizes the current, deployed review submission verification engine, highlights the recent applied changes, and provides a concise handoff checklist for operators and engineers.

Purpose
- Capture the new verification flows, DB changes, admin/reward interactions, and testing/migration steps so the next engineer can pick up work quickly.

Summary of applied changes
- Review eligibility monitoring: added forward (holding) monitoring to allow users to prove token holdings and become eligible for one review submission.
- New DB schema: migration `database/migrations/2026_08_01_review_eligibility_monitoring.sql` creates `review_eligibility_monitoring_sessions`, `review_eligibility_monitoring_events`, `review_eligibility_notification_outbox`, and augments `project_contracts` and `reviews` with new columns.
- Worker script: `scripts/process-review-eligibility.php` is the CLI worker that processes monitoring sessions and delivers notification outbox items.
- Admin moderation: `admin/reviews.php` was extended with scoring functions, eligibility validation (`validateApproveEligibility`), monitoring session joins, and reward flow integration (ledger entries, reversals, counters sync).
- Reward flows: reward and reversal logic are coordinated via existing ledger helpers (see `admin/includes/reward_admin.php` and core reward helpers like `addRewardLedgerEntry()` and `syncUserReviewCounters()`).
- Notification templates: templates for `review.eligibility.*` were added to populate in-app/email notifications when monitoring starts, completes, or is delayed.

Key files to review
- Admin moderation UI and logic: [admin/reviews.php](admin/reviews.php#L1-L200)
- Worker entrypoint: [scripts/process-review-eligibility.php](scripts/process-review-eligibility.php#L1-L200)
- DB migration: [database/migrations/2026_08_01_review_eligibility_monitoring.sql](database/migrations/2026_08_01_review_eligibility_monitoring.sql#L1-L200)
- Documentation for monitoring rules: [docs/review-eligibility-monitoring.md](docs/review-eligibility-monitoring.md)

Environment and configuration
- Required environment variables:
  - `ETHERSCAN_API_KEY` or `EXPLORER_API_KEY`
  - Optional `ETHERSCAN_API_BASE_URL` for custom explorer endpoints
  - SMTP settings for notification delivery
- Ensure `project_contracts` rows include `eligibility_min_amount` and `eligibility_holding_minutes` for projects that enable eligibility monitoring.

Operational checklist (quick start)
1. Apply DB migration:

```bash
mysql -u <dbuser> -p < koinrex < database/migrations/2026_08_01_review_eligibility_monitoring.sql
```

2. Configure environment variables (set explorer API key and SMTP vars).

3. Start the worker in cron (every minute recommended):

```cron
* * * * * /usr/bin/php /path/to/coinrex/scripts/process-review-eligibility.php >/dev/null 2>&1
```

4. Smoke test the monitoring flow:
  - Create or simulate a monitoring session (via UI or DB insert) for a sample project contract.
  - Ensure `review_eligibility_monitoring_sessions` row transitions to `eligible` when conditions are met and that a `review.eligibility.completed` notification is enqueued/delivered.
  - Run the worker manually for quick feedback:

```bash
/usr/bin/php scripts/process-review-eligibility.php
```

5. Validate the admin moderation path:
  - Open the admin review queue (`admin/reviews.php`) and confirm score breakdowns, eligibility fields, and moderation actions (`approve`, `reject`, `flag`) operate and create ledger entries correctly.

Testing and validation
- Run unit tests: `vendor/bin/phpunit` (or use the project's phpunit runner).
- Check logs for worker errors: application logs and `.tmp/*` service logs for any `rexlink-service` relevant output.

Handoff notes for engineers
- The eligibility system uses raw on-chain integers (no USD conversion) and compares using token decimals stored in `project_contracts`.
- Monitoring sessions are consumable: a successful session becomes eligible for 24 hours and is consumed by a review submission.
- Admin moderation enforces eligibility checks via `validateApproveEligibility()` which ensures TX uniqueness, wallet reuse thresholds, screenshot proof, and non-empty review text.
- Reward accounting is performed using the existing `reward_ledger` helpers — review approvals credit ledger entries and reversals remove them.

Where to make future improvements (suggestions)
- Add an API endpoint to create test monitoring sessions for QA.
- Add more granular integration tests covering the full monitoring → eligible → submit-review → admin-approve flow.
- Consider adding observability metrics for worker throughput and notification delivery failures.

Contact and ownership
- Last applied changes live in `admin/reviews.php` and migrations; reach out to the original author/team for clarifications during handoff.

---
Generated: automated summary for handoff.
