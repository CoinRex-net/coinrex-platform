# CoinRex API Documentation

## Overview

All API endpoints are located under `/api` and share common bootstrap logic from:

- `api/_bootstrap.php`

Common response format:

- Success:
  ```json
  {"success": true, "...": "..."}
  ```
- Error:
  ```json
  {"success": false, "message": "..."}
  ```

## Bootstrap Behavior (`api/_bootstrap.php`)

- Loads `includes/config.php` and `includes/functions.php`
- Calls `ensureRewardClaimSchema()` (runtime schema prep; should be migrated away)
- Provides helpers:
  - `apiJsonResponse`, `apiSuccessResponse`, `apiErrorResponse`
  - `apiRequireMethod`
  - `apiGetRequestedUserId`
  - `apiGetAuthenticatedUser`
  - `apiResolveAuthorizedUserId`
  - `apiRequireRewardIssuer`

## Authentication Rules

- API calls rely on current session context (user/admin cookies).
- `apiGetAuthenticatedUser()`:
  - accepts admin session (`admin_id`) as privileged actor
  - accepts logged-in user session via app auth helpers
- `apiResolveAuthorizedUserId()` prevents normal users from reading/updating other users' reward data.

## Endpoints

## Reward & Balance

### `GET /api/get_balance.php`

- Query: `user_id` (optional for current user)
- Response: available balance + cached profile balance

### `POST /api/add_reward.php`

- Body: `user_id`, `amount`, `source`, optional `action_type`, `reference_id`
- Access: admin/reward issuer only
- Effect: inserts `reward_ledger` entry

### `GET /api/reward_overview.php`

- Query: `user_id` optional
- Returns consolidated reward dashboard payload:
  - balances
  - claim eligibility
  - open/recent claims
  - recent ledger entries
  - mini task/task stats and security signals

## Mini Tasks / TaskHub

### `GET /api/get_mini_tasks.php`

- Query: `user_id` optional
- Returns active task list for authorized user

### `POST /api/complete_mini_task.php`

- Body: `task_id`, optional `proof`, optional `user_id`
- Completes task or submits for review depending on task config

### `GET /api/get_taskhub_state.php`

- Query: `user_id` optional
- Returns TaskHub mission state for authorized user

### `POST /api/submit_taskhub_task.php`

- Body: `task_key`, optional payload fields (`wallet_address`, `proof`, `x_handle`, `telegram_handle`, `answers_json`, optional `user_id`)
- Returns task result, updated state, and balance

## Claims

### `POST /api/generate_claim.php`

- Body: optional `user_id`
- Locks available rewards and creates claim snapshot

### `GET /api/claim_status.php`

- Query: `snapshot_id` (required)
- Returns snapshot details (scoped to actor unless admin)

## Error Handling

- Most endpoint exceptions map to `422`.
- Special cases:
  - `generate_claim.php` may return `409` for already-prepared state.
  - `claim_status.php` may return `404` when snapshot not found.
  - method mismatch returns `405` via `apiRequireMethod`.

## Security Notes

- Uses prepared statements through shared helpers.
- Authorization checks are present for user scope and issuer scope.
- CSRF enforcement is not consistently explicit on all session-authenticated POST endpoints.
