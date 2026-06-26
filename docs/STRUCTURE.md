# CoinRex Repository Structure

This document describes the main CoinRex platform repository. The `rex-wallet/` directory is intentionally ignored here because the wallet app is planned for a separate repository.

## Top-Level Layout

```text
/
|-- .github/                # CI workflows, issue templates, PR template
|-- admin/                  # Admin panel, moderation, settings, operations
|-- api/                    # JSON APIs, API bootstraps, versioned endpoints
|-- assets/                 # Shared CSS, JavaScript, images, and public media
|-- auth/                   # Login, registration, logout, password reset, OTP
|-- contracts/              # Smart-contract sources and related tests
|-- database/               # SQL migrations and schema recreation scripts
|-- deployments/            # Local/generated deployment outputs, ignored where needed
|-- devhub/                 # Developer/project-owner hub
|-- docs/                   # Architecture, API, security, roadmap, and plans
|-- includes/               # Shared config, helpers, services, and components
|-- public/                 # Public-facing route files
|-- realtime/               # Node realtime/WebSocket server
|-- scripts/                # Deployment and maintenance scripts
|-- src/                    # PSR-4 classes under the CoinRex namespace
|-- test/                   # Smart-contract test workspace
|-- tests/                  # PHPUnit test suites
|-- tools/                  # Local developer and maintenance utilities
|-- uploads/                # Runtime uploads, ignored by Git
|-- index.php               # Root landing entry point
|-- composer.json           # PHP dependencies, autoloading, and scripts
|-- package.json            # Node scripts for contracts and realtime tooling
`-- README.md               # Project overview and setup guide
```

## Public Entry Points

Public-facing route files live under `public/` where possible, with `index.php` kept at the root for the landing page and clean URL compatibility.

```text
public/
|-- dashboard.php
|-- projects.php
|-- project-detail.php
|-- reviews.php
|-- my-reviews.php
|-- submit-review.php
|-- about.php
|-- blog.php
|-- blog-category.php
|-- blog-post.php
|-- blog-tag.php
|-- contact.php
|-- cookies.php
|-- faq.php
|-- home.php
|-- privacy.php
|-- terms.php
|-- profile.php
|-- notifications.php
|-- claims.php
|-- reward-history.php
|-- boosthub.php
|-- taskhub.php
|-- sponsored-apply.php
`-- widget.js
```

## Main Domains

### `src/` - PSR-4 Classes

```text
src/
|-- Database/               # PDO connection wrapper
|-- Exception/              # Domain and validation exceptions
`-- Http/                   # Request and response helpers
```

### `admin/` - Admin Panel

Contains admin authentication, dashboards, moderation queues, user/project/review management, rewards, referrals, security controls, blog management, TaskHub/BoostHub administration, and admin-specific assets/includes.

### `api/` - JSON APIs

Contains shared API bootstraps, user/task/reward endpoints, learning endpoints, admin endpoints, review eligibility APIs, Rex Signer APIs, and the versioned `/api/v1` surface.

### `auth/` - User Authentication

Contains login, registration, logout, password reset, and email verification flows.

### `devhub/` - Developer Hub

Contains developer/project-owner application flows, project management, reviews, notifications, widget documentation, DevHub assets, and DevHub-specific includes.

### `includes/` - Shared Core

```text
includes/
|-- config.php              # Environment, database, session bootstrap
|-- functions.php           # Shared function loader
|-- functions/              # Domain-specific helper modules
|-- services/               # Service classes
|-- taskhub/                # TaskHub components
|-- header.php              # Shared page header
`-- footer.php              # Shared page footer
```

### `assets/` - Static Frontend Assets

```text
assets/
|-- css/
|-- images/
|-- js/
`-- uploads/                # Runtime media, ignored by Git
```

### `database/` - Schema and Migrations

```text
database/
`-- migrations/             # SQL migration files and schema recreation scripts
```

### `realtime/` and `contracts/`

- `realtime/` contains the Node WebSocket/event server used by realtime features.
- `contracts/` contains smart-contract sources, with root `package.json` scripts for Hardhat compile, tests, deployment, and realtime syntax checks.

## Configuration and Standards

| File | Purpose |
| --- | --- |
| `.editorconfig` | Editor formatting consistency |
| `.gitattributes` | Git line ending normalization |
| `.gitignore` | Git exclusion rules, including `rex-wallet/` |
| `.env.example` | Environment variable template |
| `composer.json` | PHP dependencies, autoloading, and quality scripts |
| `package.json` | Node scripts for contracts and realtime tooling |
| `phpunit.xml.dist` | PHPUnit configuration |
| `phpstan.neon.dist` | PHPStan static analysis configuration |
| `phpcs.xml.dist` | PHPCS coding standards configuration |

## GitHub Integration

| File | Purpose |
| --- | --- |
| `.github/workflows/ci.yml` | CI pipeline for PHPCS, PHPStan, PHPUnit, realtime syntax, and security audit |
| `.github/ISSUE_TEMPLATE/bug_report.md` | Bug report template |
| `.github/ISSUE_TEMPLATE/feature_request.md` | Feature request template |
| `.github/PULL_REQUEST_TEMPLATE.md` | Pull request template |

## File Classification Guide

- Root-level PHP should be limited to stable entry points such as `index.php`.
- Public route files should live under `public/` where clean URL compatibility allows.
- Local maintenance utilities belong in `tools/`.
- Runtime uploads, generated artifacts, dependency folders, local secrets, and `rex-wallet/` must stay out of Git.
- Legacy compatibility files may remain when they protect an ongoing migration, but new logic should prefer modular helpers or PSR-4 classes.

## Cleanup History

| Date | Change | Status |
| --- | --- | --- |
| 2026-05-20 | Created repository structure documentation | Done |
| 2026-05-20 | Moved utility files to `tools/` | Done |
| 2026-05-20 | Moved plans to `docs/plans/` | Done |
| 2026-05-28 | Added `src/`, `tests/`, GitHub templates, and quality configs | Done |
| 2026-05-28 | Added CI workflow and contribution/security docs | Done |
| 2026-05-28 | Made testing mode environment-driven | Done |
| 2026-05-28 | Moved public pages to `public/` | Done |
| 2026-06-27 | Ignored `rex-wallet/` for future extraction to a separate repo | Done |
