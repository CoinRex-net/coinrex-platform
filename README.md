# CoinRex Platform

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql)
![CI Status](https://img.shields.io/badge/CI-ready-success)
![License](https://img.shields.io/badge/license-MIT-blue)
![Security](https://img.shields.io/badge/security-focused-orange)
![Architecture](https://img.shields.io/badge/architecture-modular%20monolith-purple)

**CoinRex** is a PHP/MySQL platform for crypto project reviews, proof-based trust workflows, rewards, wallet-linked actions, and admin moderation. It helps users review crypto projects with evidence, earn platform rewards, follow structured missions, and interact with Web3 approval flows while giving administrators and project operators tools to verify activity and reduce abuse.

> Note: `rex-wallet/` is intentionally excluded from this repository. The wallet app is planned for a separate repository.

## Features

- **Secure authentication** - OTP email verification, bcrypt password hashing, remember-me cookies, and CSRF protection.
- **Proof-backed reviews** - Screenshot and holding evidence with moderation workflows.
- **Reward system** - Ledger-based rewards with claim snapshots and referral bonuses.
- **TaskHub / BoostHub** - Guided participation and earning for beginner accounts.
- **Trust progression** - Beginner, Pro, and Expert levels with weighted trust.
- **On-chain review eligibility** - Wallet nonce, wallet verification, and eligibility checks for token-aware review access.
- **RexLink** - Wallet linking sessions, pairing QR flows, approval requests, claim transaction approvals, realtime auth, and asset APIs.
- **Sponsored and launch flows** - Sponsored token/project management, early airdrop, launch control, and roadmap pages.
- **Admin moderation** - User, project, review, reward, quiz, roadmap, launch, and security management.
- **DevHub** - Developer and project-side workflows, applications, project editing, notifications, and widget integrations.
- **Anti-abuse tooling** - Security signals, fraud detection, rate limiting, and IP tracking.

## Tech Stack

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
| Realtime and contracts | Node.js, ws, Hardhat, Solidity |

## Project Structure

```text
coinrex/
|-- admin/                  # Admin panel, RBAC, moderation, operations
|-- api/                    # JSON endpoints and versioned APIs
|-- assets/                 # CSS, JavaScript, images, and public assets
|-- auth/                   # User authentication and OTP flows
|-- contracts/              # Smart-contract sources
|-- database/migrations/    # Schema changes and seed files
|-- devhub/                 # Developer/project-side workflows
|-- docs/                   # Architecture, API, security, roadmap, plans
|-- includes/               # Shared config, helpers, services, components
|-- public/                 # Public-facing route files
|-- realtime/               # WebSocket/event realtime server
|-- scripts/                # Contract deployment and maintenance scripts
|-- src/                    # PSR-4 classes under the CoinRex namespace
|-- test/                   # Hardhat contract tests
|-- tests/                  # PHPUnit test suites
|-- uploads/                # Runtime uploads, ignored by Git
|-- index.php               # Root landing entry point
|-- composer.json           # PHP dependencies, autoloading, scripts
|-- package.json            # Node scripts for contracts and realtime checks
`-- README.md
```

## Quick Start

### Prerequisites

- PHP 8.1 or higher
- MySQL 8.0+
- Composer
- Node.js 20+ for realtime and smart-contract tooling

### Installation

```bash
# 1. Clone the repository
git clone https://github.com/CoinRex-net/coinrex-platform.git
cd coinrex-platform

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies for realtime/contracts
npm ci

# 4. Configure environment
cp .env.example .env
# Edit .env with local database credentials and generated secrets

# 5. Create database and import schema
mysql -u root -p koinrex < database/migrations/recreate_db.sql

# 6. Apply pending migrations in database/migrations/ in filename order

# 7. Ensure writable runtime directories exist
mkdir -p uploads devhub/logs
```

## Main Product Areas

| Area | What it contains |
| --- | --- |
| Public platform | Project listings, project detail pages, reviews, profile, dashboard, claims, notifications, litepaper, roadmap, TaskHub, and BoostHub |
| Admin | Users, projects, reviews, rewards, referrals, quizzes, sponsored tokens, launch control, roadmap, developers, TaskHub/BoostHub, and security management |
| DevHub | Developer onboarding, project submission/editing, notifications, review visibility, and widget integration docs |
| APIs | TaskHub, rewards, notifications, learning sessions, review eligibility, admin quiz/BoostHub, RexLink, and `/api/v1` endpoints |
| Web3 | CoinRex token, claim distributor contracts, Amoy deployment scripts, claim signing, and RexLink approval/session flows |
| Realtime | WebSocket/event server used by RexLink linking, RexLink approvals, and other realtime features |

## RexLink, WebSockets, and On-Chain Eligibility

CoinRex includes a wallet-linked trust layer for actions that need wallet ownership, realtime approval, or on-chain context.

| Capability | Implementation |
| --- | --- |
| RexLink wallet linking | Pairing sessions, QR payloads, session completion, revocation, and login-from-session APIs under `api/rex-signer/` |
| WebSocket approval flow | `realtime/server.js` plus RexLink realtime auth endpoints for live pairing and approval updates |
| Claim approvals | Claim approval request creation, wallet-side approval decisions, signed claim transaction completion, and distributor funding/deployment scripts |
| On-chain review eligibility | Wallet nonce generation, wallet verification, and eligibility checks under `api/review-eligibility/` |
| Smart contracts | `contracts/CoinRexToken.sol`, `contracts/RexClaimDistributor.sol`, Hardhat tests, and Amoy deployment scripts |

Key API groups:

- `api/rex-signer/create_pairing.php`, `complete_pairing.php`, `pairing_qr.php`, `sessions.php`, and `revoke_session.php` manage RexLink pairing and sessions.
- `api/rex-signer/create_approval_request.php`, `approval_requests.php`, `approval_decision.php`, and `realtime_auth.php` support realtime wallet approvals over WebSockets.
- `api/rex-signer/create_claim_approval.php` and `complete_claim_tx.php` support wallet-approved reward claim transactions.
- `api/review-eligibility/wallet_nonce.php`, `verify_wallet.php`, and `check.php` support on-chain/wallet-aware review eligibility.

## Development Checks

```bash
# Run PHPUnit
composer test

# Check coding standards
composer phpcs

# Run static analysis
composer phpstan

# Run PHP checks together
composer check

# Check realtime server syntax
npm run realtime:check

# Compile smart contracts
npm run contracts:compile

# Run smart-contract tests
npm run contracts:test
```

## Security

- **Password hashing:** bcrypt with cost factor 12.
- **OTP security:** expiry, cooldown, and attempt limits.
- **CSRF protection:** token-based protection for browser-authenticated state changes.
- **Anti-abuse:** security signals, fraud events, rate limiting, and IP tracking.
- **SQL injection defense:** prepared statements throughout the platform.
- **XSS prevention:** escaped output with `htmlspecialchars` where user data is rendered.

Before production deployment, rotate all secrets, disable testing mode, and review [`docs/SECURITY.md`](docs/SECURITY.md).

## Documentation

| Document | Description |
| --- | --- |
| [Architecture](docs/ARCHITECTURE.md) | System architecture and design decisions |
| [Database](docs/DATABASE.md) | Schema overview and relationships |
| [Security](docs/SECURITY.md) | Security model and checklist |
| [Auth](docs/AUTH.md) | Authentication and authorization flows |
| [API](docs/API.md) | API endpoint documentation |
| [System Health](docs/SYSTEM_HEALTH.md) | Monitoring and health checks |
| [Widgets](docs/WIDGETS.md) | Widget integration guide |
| [Roadmap](docs/ROADMAP.md) | Development roadmap |
| [Structure](docs/STRUCTURE.md) | Repository layout guide |
| [AI Context](docs/AI_CONTEXT.md) | Context for AI-assisted development |

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for contribution guidelines, coding standards, and pull request expectations.

## Roadmap

- **Phase 1:** Security hardening, configuration cleanup, and GitHub readiness.
- **Phase 2:** Modular refactor, API consistency, test coverage, and maintainability improvements.
- **Phase 3:** Realtime/Web3 scalability, sponsored launch tooling, and platform ecosystem evolution.

See the full roadmap in [`docs/ROADMAP.md`](docs/ROADMAP.md).

## License

This project is licensed under the MIT License.
