# CoinRex Platform — Project Documentation

## 1. Project Overview

**CoinRex** is a PHP/MySQL platform for crypto project reviews, proof-based trust workflows, rewards, wallet-linked actions, and admin moderation. It helps users review crypto projects with evidence, earn platform rewards, follow structured missions, and interact with Web3 approval flows while giving administrators and project operators tools to verify activity and reduce abuse.

### Key Features

- **Secure authentication** — OTP email verification, bcrypt password hashing, remember-me cookies, and CSRF protection.
- **Proof-backed reviews** — Screenshot and holding evidence with moderation workflows.
- **Reward system** — Ledger-based rewards with claim snapshots and referral bonuses.
- **TaskHub / BoostHub** — Guided participation and earning for beginner accounts.
- **Trust progression** — Beginner, Pro, and Expert levels with weighted trust.
- **On-chain review eligibility** — Wallet nonce, wallet verification, and eligibility checks for token-aware review access.
- **RexLink** — Wallet linking sessions, pairing QR flows, approval requests, claim transaction approvals, realtime auth, and asset APIs.
- **Sponsored and launch flows** — Sponsored token/project management, early airdrop, launch control, and roadmap pages.
- **Admin moderation** — User, project, review, reward, quiz, roadmap, launch, and security management.
- **DevHub** — Developer and project-side workflows, applications, project editing, notifications, and widget integrations.
- **Anti-abuse tooling** — Security signals, fraud detection, rate limiting, and IP tracking.

### Tech Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.1+ |
| Database | MySQL 8.0 (InnoDB) |
| Dependencies | Composer |
| Mail | PHPMailer |
| Frontend | Server-rendered PHP + CSS/JS |
| Architecture | Modular monolith |
| Testing | PHPUnit |
| Static analysis | PHPStan |
| Coding standards | PHPCS / PSR-12 |
| Realtime | Node.js, ws, WebSocket |
| Smart contracts | Hardhat, Solidity 0.8.24, OpenZeppelin |
| Mobile wallet | Expo React Native (rex-wallet/) |

### Environment

- Primary database: `koinrex`
- Production URL: `https://coinrex.xyz`
- License: MIT

---

## 2. Folder Structure

```text
coinrex/
|-- .github/                # CI workflows, issue templates, PR template
|-- admin/                  # Admin panel, RBAC, moderation, operations
|-- api/                    # JSON endpoints and versioned APIs
|-- assets/                 # CSS, JavaScript, images, and public assets
|-- auth/                   # User authentication and OTP flows
|-- contracts/              # Smart-contract sources
|-- database/migrations/    # Schema changes and seed files
|-- deployments/            # Local/generated deployment outputs
|-- devhub/                 # Developer/project-side workflows
|-- docs/                   # Architecture, API, security, roadmap, plans
|-- includes/               # Shared config, helpers, services, components
|-- public/                 # Public-facing route files
|-- realtime/               # WebSocket/event realtime server
|-- rex-wallet/             # Expo React Native RexLink wallet app
|-- rexlink-service/        # Node API for RexLink pairing, sessions, approvals
|-- scripts/                # Contract deployment and maintenance scripts
|-- src/                    # PSR-4 classes under the CoinRex namespace
|-- test/                   # Hardhat contract tests
|-- tests/                  # PHPUnit test suites
|-- uploads/                # Runtime uploads, ignored by Git
|-- index.php               # Root landing entry point
|-- composer.json           # PHP dependencies, autoloading, scripts
|-- package.json            # Node scripts for contracts and realtime checks
`-- README.md               # Project overview and setup guide
```

### Key Directory Details

#### `admin/` — Admin Panel
Contains admin authentication, dashboards, moderation queues, user/project/review management, rewards, referrals, security controls, blog management, TaskHub/BoostHub administration, and admin-specific assets/includes.

#### `api/` — JSON APIs
Contains shared API bootstraps, user/task/reward endpoints, learning endpoints, admin endpoints, review eligibility APIs, RexLink APIs, and the versioned `/api/v1` surface.

#### `auth/` — User Authentication
Contains login, registration, logout, password reset, and email verification flows.

#### `public/` — Public Entry Points
Public-facing route files: dashboard, projects, project-detail, reviews, my-reviews, submit-review, about, blog, contact, cookies, faq, home, privacy, terms, profile, notifications, claims, reward-history, boosthub, taskhub, sponsored-apply, widget.js.

#### `includes/` — Shared Core
- `config.php` — Environment, database, session bootstrap
- `functions.php` — Shared function loader
- `functions/` — Domain-specific helper modules (auth, blog, boosthub, core, email, feature_flags, helpers, leaderboard, levels, metrics, navigation, notifications, rating, realtime, referrals, review_eligibility, reward_ledger, roadmap, security, sponsored, taskhub, user, widget)
- `services/` — Service classes
- `taskhub/` — TaskHub components
- `header.php` / `footer.php` — Shared page header/footer

#### `src/` — PSR-4 Classes
- `Database/Connection.php` — PDO connection wrapper
- `Exception/` — Domain and validation exceptions
- `Http/` — Request and response helpers

#### `rexlink-service/` — Node RexLink API
- `server.js` — Express bootstrap, middleware, service wiring
- `routes/` — Route registration modules
- `services/` — Pairing, approvals, auth-session, assets, monitor logic
- `lib/` — Async route wrapper and response payload mappers
- `auth.js`, `claims.js`, `config.js`, `db.js`, `realtime.js`, `util.js`

#### `realtime/` — WebSocket Server
Node WebSocket/event server used by realtime features (RexLink linking, approvals, etc.).

---

## 3. Database Schema

### 3.1 Core Tables

#### Identity & Access
- **`users`** — Core user profile/auth/reward state. Includes OTP, login metadata, referral, level, wallet fields, security governance columns.
- **`admins`** — Admin credentials and status.
- **`roles`** — RBAC roles (super_admin, admin, moderator).
- **`permissions`** — RBAC permission definitions.
- **`role_permissions`** — Role-permission mapping.
- **`admin_activity_logs`** — Auditable admin action history.
- **`admin_logs`** — Admin security event audit log.

#### Content & Moderation
- **`projects`** — Listed crypto projects, approval and feature status.
- **`reviews`** — User reviews per project with proof, moderation, scoring, eligibility.
- **`review_reactions`** — Review reactions by users.
- **`review_comments`** — Comments on reviews.
- **`review_comment_likes`** — Likes on review comments.
- **`review_insights`** — Review impression/read tracking.
- **`review_priority_slots`** — RexRank priority slots for reviews.
- **`content_flags`** — Abuse/content flag records.

#### Rewards & Claims
- **`reward_ledger`** — Reward event ledger with source/action/status.
- **`claim_snapshots`** — Claim generation snapshots (amount + nonce + status).
- **`rexrank_ledger`** — RexRank balance ledger.
- **`early_airdrop_pool`** — Single-row table tracking remaining airdrop pool.
- **`early_airdrop_claims`** — Tracks who claimed what from the airdrop pool.

#### Task System
- **`mini_tasks`** — Mini task definitions.
- **`user_task_logs`** — Task completion/blocked records.
- **`taskhub_quiz_questions`** — Admin-managed quiz questions.
- **`taskhub_learning_sessions`** — Server-side tracking of active reading sessions.

#### Notifications
- **`notification_templates`** — Notification template definitions.
- **`notifications`** — In-app notification deliveries.

#### Blog
- **`blog_posts`** — Blog post content and metadata.
- **`blog_categories`** — Blog categories.
- **`blog_tags`** — Blog tags.
- **`blog_post_categories`** — Post-category pivot.
- **`blog_post_tags`** — Post-tag pivot.
- **`blog_ads`** — Blog ad placements.

#### Sponsored & Launch
- **`sponsored_tokens`** — One-time-use application links for sponsored projects.
- **`feature_flags`** — MVP launch feature visibility and access controls.
- **`roadmap_settings`** — Roadmap version settings.
- **`roadmap_stages`** — Roadmap stage definitions.
- **`roadmap_stage_entries`** — Roadmap stage items/goals.
- **`navigation_controls`** — Dynamic navigation menu controls.

#### Security & Anti-Abuse
- **`user_security_signals`** — Hashed signal storage for IP/fingerprint/user-agent.
- **`fraud_events`** — Structured security event journal.

#### RexLink / Wallet
- **`rex_signer_networks`** — Supported blockchain networks.
- **`rex_signer_activity_cache`** — Wallet activity history cache.
- **`rex_signer_pairing_codes`** — Pairing code records.
- **`rex_signer_sessions`** — Active signer sessions.
- **`rex_signer_approval_requests`** — Wallet approval requests.
- **`rex_signer_auth_challenges`** — Passwordless auth challenges.

#### Review Eligibility
- **`project_contracts`** — Project token contract metadata.
- **`review_eligibility_checks`** — Wallet eligibility check results.
- **`review_eligibility_monitoring_sessions`** — Ongoing holding verification sessions.
- **`review_eligibility_monitoring_events`** — Blockchain transfer events for monitoring.
- **`review_eligibility_notification_outbox`** — Notification delivery queue.

#### Metrics
- **`user_activity_days`** — Daily user activity tracking.
- **`user_sessions`** — User session tracking.
- **`investor_metric_tokens`** — Investor link access tokens.

#### Support/Comms
- **`messages`** — Admin-side message queue/status entries.

### 3.2 Relationship Summary

```text
users 1---* reviews *---1 projects
users 1---* reward_ledger
users 1---* claim_snapshots
users 1---* user_task_logs *---1 mini_tasks
users 1---* developer_verification
users 1---* content_flags
users 1---* review_reactions *---1 reviews
users 1---* review_comments *---1 reviews
users 1---* rexrank_ledger
users 1---* user_security_signals
users 1---* rex_signer_sessions
users 1---* rex_signer_approval_requests

admins 1---* admin_activity_logs
admins 1---* reviews (reviewed_by/proof_verified_by)
admins 1---* projects (feature_reviewed_by)
admins 1---* blog_posts (author_admin_id)

roles 1---* admins (role_id)
roles 1---* role_permissions *---1 permissions

projects 1---* project_contracts
projects 1---* review_eligibility_checks
projects 1---* review_eligibility_monitoring_sessions
projects 1---* sponsored_tokens
```

### 3.3 Schema Design Notes

- Strong unique constraints (email, username, referral code, tx hash, user+project review uniqueness).
- Reward and claim systems are append/transition friendly.
- Review table captures both moderation and proof verification states.
- All tables use InnoDB with utf8mb4_unicode_ci collation.
- Foreign keys with `ON DELETE CASCADE` or `ON DELETE SET NULL` as appropriate.

---

## 4. Coding Standards

### PHP Standards

- **PSR-12** enforced via PHPCS (`phpcs.xml.dist`).
- Line length limit: 200 characters (absolute 300).
- Mixed PHP/HTML allowed in view files.
- PHP 8.1+ required.

### EditorConfig

- UTF-8 charset, LF line endings.
- PHP/SQL: 4-space indentation.
- HTML/CSS/JS/JSON/YAML: 2-space indentation.
- Final newline required, trailing whitespace trimmed.

### Quality Tooling

| Tool | Command | Purpose |
| --- | --- | --- |
| PHPUnit | `composer test` | Unit tests |
| PHPCS | `composer phpcs` | Coding standards check |
| PHPCBF | `composer phpcbf` | Auto-fix coding standards |
| PHPStan | `composer phpstan` | Static analysis |
| Combined | `composer check` | Run all PHP checks |

### Security Practices

- **Password hashing:** bcrypt with cost factor 12.
- **OTP security:** expiry, cooldown, and attempt limits.
- **CSRF protection:** token-based protection for browser-authenticated state changes.
- **Anti-abuse:** security signals, fraud events, rate limiting, and IP tracking.
- **SQL injection defense:** prepared statements throughout the platform.
- **XSS prevention:** escaped output with `htmlspecialchars` where user data is rendered.

### Naming Conventions

- PHP functions: `snake_case` (e.g., `getDBConnection`, `isLoggedIn`).
- PHP classes: `CamelCase` under `CoinRex\` namespace.
- Database tables: `snake_case` plural (e.g., `reward_ledger`, `mini_tasks`).
- Database columns: `snake_case` (e.g., `wallet_address`, `created_at`).
- JavaScript: `camelCase` for variables/functions.
- SQL migrations: `YYYY_MM_DD_description.sql` format.

---

## 5. Authentication Flow

### 5.1 User Registration

1. User submits registration form (`auth/auth.php`, register tab).
2. Server validates:
   - Required fields
   - Email format and uniqueness
   - Disposable email blocklist
   - Password policy (min 9 chars, uppercase, digit, special char)
   - Optional referral code
   - Terms acceptance
3. `registerUser()` creates user, initializes reward ledger bonus records.
4. User is redirected to login flow.

### 5.2 User Login

1. User submits email + password.
2. `loginUser()` verifies credentials and account status.
3. If email verification is pending, OTP flow is triggered and user is redirected to `verify_email.php`.
4. On success, `establishAuthenticatedSession()` sets user session context.

### 5.3 Email Verification OTP Flow

1. OTP generated with `generateEmailVerificationOtp()`.
2. OTP and expiry stored in `users` (`otp_code`, `otp_expiry`, `otp_attempts`).
3. OTP sent by SMTP via PHPMailer.
4. `verify_email.php` validates OTP (length, expiry, max attempts).
5. On success: `markEmailAsVerified()` updates user verification fields and establishes authenticated session.

### 5.4 Password Reset Flow

1. User enters email/username in `auth/forgot.php`.
2. System resolves account and dispatches OTP.
3. User submits OTP (attempt/cooldown/expiry enforced).
4. On verification, user sets a new password.
5. `resetUserPassword()` updates password hash and clears OTP fields.

### 5.5 Admin Authentication Flow

1. Admin submits email/password to `admin/login.php`.
2. `adminLogin()` loads admin record and verifies `password_hash`.
3. Session keys are set (`admin_id`, `admin_email`, `admin_name`).
4. `requireAdminAuth()` guards protected admin pages.

### 5.6 Session Handling

- Session starts in `includes/config.php` with secure cookie flags:
  - `httponly` enabled
  - `samesite=Lax`
  - `secure` flag based on environment
- User session identity keys: `user_id`, `username`, `email`, `role`, `level`.
- Admin session identity keys: `admin_id`, `admin_email`, `admin_name`.

### 5.7 Remember-Me System

- Constants: `REMEMBER_ME_COOKIE_NAME`, `REMEMBER_ME_LIFETIME_SECONDS`.
- Schema fields in `users`: `remember_token_hash`, `remember_token_expires_at`.
- Issued/cleared during authenticated session establishment.
- Token stored as SHA-256 hash; raw token in cookie.

### 5.8 RexLink Passwordless Auth

- `auth_provider` field supports `email`, `rex_signer`, or `hybrid`.
- Wallet-based auth requires `wallet_address` and `wallet_verified_at`.
- Pairing codes, sessions, and approval requests managed via `api/rex-signer/`.

---

## 6. APIs & Blockchain Details

### 6.1 API Overview

All API endpoints are located under `/api` and share common bootstrap logic from `api/_bootstrap.php`.

**Common response format:**
- Success: `{"success": true, "...": "..."}`
- Error: `{"success": false, "message": "..."}`

**Authentication rules:**
- API calls rely on current session context (user/admin cookies).
- `apiGetAuthenticatedUser()` accepts admin session as privileged actor or logged-in user session.
- `apiResolveAuthorizedUserId()` prevents normal users from accessing other users' data.

### 6.2 API Endpoint Groups

#### Reward & Balance
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/get_balance.php` | GET | Available balance + cached profile balance |
| `/api/add_reward.php` | POST | Insert reward_ledger entry (admin/reward issuer only) |
| `/api/reward_overview.php` | GET | Consolidated reward dashboard payload |

#### Mini Tasks / TaskHub
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/get_mini_tasks.php` | GET | Active task list for authorized user |
| `/api/complete_mini_task.php` | POST | Complete task or submit for review |
| `/api/get_taskhub_state.php` | GET | TaskHub mission state |
| `/api/submit_taskhub_task.php` | POST | Submit task result with payload |

#### Claims
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/generate_claim.php` | POST | Lock available rewards and create claim snapshot |
| `/api/claim_status.php` | GET | Snapshot details (scoped to actor unless admin) |

#### Notifications
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/get_notifications.php` | GET | User notifications |
| `/api/mark_notification_read.php` | POST | Mark single notification read |
| `/api/mark_all_notifications_read.php` | POST | Mark all notifications read |

#### Learning Sessions
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/learning/start_session.php` | POST | Start learning session |
| `/api/learning/heartbeat.php` | POST | Session heartbeat |
| `/api/learning/heartbeat_session.php` | POST | Session heartbeat (alternate) |
| `/api/learning/validate_session.php` | POST | Validate session |
| `/api/learning/verify_session.php` | POST | Verify session completion |
| `/api/learning/report_interruption.php` | POST | Report session interruption |

#### Review Eligibility
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/review-eligibility/check.php` | GET | Check review eligibility |
| `/api/review-eligibility/status.php` | GET | Eligibility status |
| `/api/review-eligibility/instant.php` | POST | Instant verification |
| `/api/review-eligibility/wallet_nonce.php` | GET | Generate wallet nonce |
| `/api/review-eligibility/verify_wallet.php` | POST | Verify wallet ownership |
| `/api/review-eligibility/rexlink_wallet.php` | POST | RexLink wallet verification |
| `/api/review-eligibility/create_rexlink_pairing.php` | POST | Create RexLink pairing |

#### RexLink / RexSigner
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/rex-signer/networks.php` | GET | Enabled RexLink networks |
| `/api/rex-signer/assets.php` | GET | Networks + token catalog + balances |
| `/api/rex-signer/create_pairing.php` | POST | Create pairing code |
| `/api/rex-signer/complete_pairing.php` | POST | Complete pairing |
| `/api/rex-signer/pairing_qr.php` | GET | Pairing QR payload |
| `/api/rex-signer/sessions.php` | GET | Active sessions |
| `/api/rex-signer/revoke_session.php` | POST | Revoke session |
| `/api/rex-signer/create_approval_request.php` | POST | Create approval request |
| `/api/rex-signer/approval_requests.php` | GET | List approval requests |
| `/api/rex-signer/approval_decision.php` | POST | Approve/reject request |
| `/api/rex-signer/create_claim_approval.php` | POST | Create claim approval |
| `/api/rex-signer/complete_claim_tx.php` | POST | Complete claim transaction |
| `/api/rex-signer/realtime_auth.php` | POST | Realtime auth token |
| `/api/rex-signer/external_history.php` | GET | External wallet history |
| `/api/rex-signer/cancel_pairing.php` | POST | Cancel pairing |
| `/api/rex-signer/review_pairing_status.php` | GET | Review pairing status |

#### Versioned API (`/api/v1`)
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/v1/project/{slug}/rating` | GET | Public project rating (CORS-enabled) |
| `/api/v1/project/{slug}/widget` | GET | Widget data (token-authenticated) |

#### Admin APIs
| Endpoint | Method | Description |
| --- | --- | --- |
| `/api/admin/boosthub.php` | POST | BoostHub admin operations |
| `/api/admin/quiz.php` | POST | Quiz admin operations |

### 6.3 Smart Contracts

#### CoinRexToken (`contracts/CoinRexToken.sol`)
- ERC-20 token: **REX**
- Initial supply: **1,000,000,000 REX** (1 billion)
- 18 decimals
- Uses OpenZeppelin ERC20

#### RexClaimDistributor (`contracts/RexClaimDistributor.sol`)
- EIP-712 typed data signing for claims
- Claim signature verification via ECDSA
- Claim fee mechanism (native token)
- ReentrancyGuard protection
- Owner-controlled: claim signer, claim fee, reserve wallet
- Rescue function for stuck tokens

### 6.4 Blockchain Networks

| Network | Chain ID | Environment | RPC |
| --- | --- | --- | --- |
| Polygon | 137 | mainnet | `https://polygon-rpc.com` |
| Base | 8453 | mainnet | `https://mainnet.base.org` |
| Plasma | — | mainnet (stub) | — |
| Polygon Amoy | 80002 | staging/testnet | `https://rpc-amoy.polygon.technology` |

### 6.5 Hardhat Configuration

- Solidity version: **0.8.24**
- Optimizer: enabled, 200 runs
- Networks: hardhat (31337), amoy (80002), polygon (137)
- Scripts:
  - `npm run contracts:compile` — Compile contracts
  - `npm run contracts:test` — Run contract tests
  - `npm run contracts:deploy:amoy` — Deploy REX token to Amoy
  - `npm run contracts:deploy-distributor:amoy` — Deploy claim distributor to Amoy
  - `npm run contracts:fund-distributor:amoy` — Fund claim distributor

### 6.6 Realtime Server

- WebSocket server on port **8081** (`COINREX_REALTIME_WS_PORT`)
- Event server on port **8082** (`COINREX_REALTIME_EVENT_PORT`)
- HMAC-SHA256 signed tokens with expiry
- Room-based subscriptions
- Used for RexLink pairing, approvals, and session events

### 6.7 RexLink Service (Node)

- Express-based Node API service
- Services: pairing, approvals, auth-session, assets, claim-monitor, maintenance, provider-factory
- MySQL access via `db.js`
- Realtime publish/attach via `realtime.js`
- Config via `config.js` (reads `.env`)

### 6.8 Deployment Scripts

| Script | Purpose |
| --- | --- |
| `scripts/deploy-rex-token.js` | Deploy CoinRexToken |
| `scripts/deploy-rex-claim-distributor.js` | Deploy RexClaimDistributor |
| `scripts/fund-rex-claim-distributor.js` | Fund distributor contract |
| `scripts/sign-rex-claim.js` | Sign claim payloads |
| `scripts/verify-evm-message.js` | Verify EVM signed messages |
| `scripts/process-review-eligibility.php` | Process review eligibility checks |
| `scripts/render-qr-svg.js` | Render QR codes as SVG |

---

## 7. Development Commands

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm ci

# Run PHPUnit
composer test

# Check coding standards
composer phpcs

# Run static analysis
composer phpstan

# Run all PHP checks
composer check

# Check realtime server syntax
npm run realtime:check

# Compile smart contracts
npm run contracts:compile

# Run smart-contract tests
npm run contracts:test

# Start realtime server
npm run realtime:start

# Start RexLink service
npm run rexlink:start
```

## 8. Environment Variables

Key environment variables (see `.env.example`):

| Variable | Purpose |
| --- | --- |
| `COINREX_ENV` | Environment mode (development/production) |
| `COINREX_DB_HOST` / `COINREX_DB_NAME` / `COINREX_DB_USER` / `COINREX_DB_PASS` | Database credentials |
| `COINREX_CSRF_KEY` / `COINREX_ENCRYPTION_KEY` | App secrets |
| `COINREX_SMTP_*` | Mail configuration |
| `COINREX_REALTIME_*` | Realtime server configuration |
| `REXLINK_*` | RexLink service configuration |
| `POLYGON_AMOY_RPC_URL` / `POLYGON_MAINNET_RPC_URL` | Blockchain RPC endpoints |
| `POLYGON_AMOY_PRIVATE_KEY` | Deployer private key |
| `REX_CLAIM_SIGNER_PRIVATE_KEY` | Claim signer private key |
| `COINREX_TESTING_MODE` | Testing mode flag (bypasses cooldowns/security) |