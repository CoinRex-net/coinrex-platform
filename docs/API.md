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

## RexLink

RexLink is the wallet-linking and approval companion for CoinRex. These endpoints are the session, pairing, and approval foundation only: no private keys, seed phrases, custody, real signing, or on-chain broadcasts are implemented.

The canonical browser integration is documented in [`REXLINK_SDK.md`](REXLINK_SDK.md). Clean Node routes are available under `/api/v1`; the legacy `.php`-shaped routes remain compatible with the mobile wallet.

### `GET /api/rex-signer/networks.php`

- Returns enabled RexLink networks.
- Current defaults:
  - `polygon-amoy` testnet
  - `plasma-testnet` stub/testnet placeholder

### `GET /api/rex-signer/assets.php`

- Returns enabled RexLink networks plus token catalog, logo metadata, placeholder balances, and price metadata.
- POL price is fetched server-side and cached briefly; the mobile app does not call third-party price APIs directly.
- REX testnet price is returned as `$0.00` with `price_status: testnet_unpriced`.
- Plasma/XPL pricing is fetched from CoinGecko when available; otherwise it falls back to `unavailable`.
- Mobile wallet balances are read client-side from Polygon Amoy RPC for native POL and ERC-20 REX; Plasma balances remain placeholder until RPC details are finalized.

### `POST /api/rex-signer/create_pairing.php`

- Access: logged-in CoinRex user session.
- Body: optional `duration_minutes` from 5 to 60.
- Creates a short-lived manual/QR pairing code.
- Response includes `display_code` and `qr_payload`.

### `POST /api/rex-signer/complete_pairing.php`

- Access: valid pending pairing code.
- Body: `code`, optional `device_name`.
- Completes pairing and returns a one-time visible `session_token`.
- Server stores only `session_token_hash`.

### `GET /api/rex-signer/sessions.php`

- Access: logged-in CoinRex user session or `Bearer <session_token>`.
- Returns active session count and recent RexLink sessions for the user.

### `POST /api/rex-signer/revoke_session.php`

- Access: logged-in CoinRex user session or `Bearer <session_token>`.
- Body: optional `session_id`, optional `reason`.
- Revokes an active signer session owned by the user.

### `POST /api/rex-signer/create_approval_request.php`

- Access: logged-in CoinRex user session.
- Body: required matching `network_slug` and `chain_id`; optional `request_type`, `title`, `summary`, `amount`, `fee_estimate`, `payload`, `expires_minutes`.
- Creates a pending approval request for the paired RexLink queue.

### `GET /api/rex-signer/approval_requests.php`

- Access: logged-in CoinRex user session or `Bearer <session_token>`.
- Query: optional `status` (`pending`, `approved`, `rejected`, `expired`, `cancelled`, `all`).
- Lists approval requests scoped to the user.

### `POST /api/rex-signer/approval_decision.php`

- Access: active `Bearer <session_token>` RexLink session.
- Body: `request_id`, `decision` (`approved` or `rejected`), optional `note`.
- Updates a pending approval request. This records approval state only; it does not sign or broadcast transactions.

## Security Notes

- Uses prepared statements through shared helpers.
- Authorization checks are present for user scope and issuer scope.
- CSRF enforcement is not consistently explicit on all session-authenticated POST endpoints.
