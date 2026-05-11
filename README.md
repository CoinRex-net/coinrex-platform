# CoinRex Platform

![Status](https://img.shields.io/badge/status-active-green)
![Stack](https://img.shields.io/badge/stack-PHP%20%7C%20MySQL%20%7C%20Composer-blue)
![Architecture](https://img.shields.io/badge/architecture-modular%20monolith-purple)
![Security](https://img.shields.io/badge/focus-security%20%26%20trust-orange)

CoinRex is a **PHP/MySQL platform for crypto project reviews, proof-based trust workflows, rewards, and admin moderation**.

It is designed to help users review crypto projects with evidence, earn platform rewards, and progress through a structured trust system while giving administrators and project operators the tools to verify activity and reduce abuse.

---

## ✨ What CoinRex Does

CoinRex combines several product areas in one platform:

- **Public review platform** for discovering and reviewing crypto projects
- **Proof-backed review workflow** with screenshots, holdings, and moderation controls
- **Reward accounting** for user progression and claim tracking
- **TaskHub / BoostHub systems** for guided participation and earning
- **Admin moderation tools** for project, review, user, reward, and security management
- **Developer/project-side flows** through DevHub and widget integrations

---

## 🚀 Core Capabilities

- User onboarding with OTP verification
- Review submission with proof flow and moderation
- TaskHub/BoostHub progression for beginner accounts
- Reward ledger + claim snapshot lifecycle
- Referral + level state model (**Beginner / Pro / Expert**)
- Admin control center for users, projects, reviews, tasks, rewards
- Security management workflow (flag / warn / suspend / module blocks)
- Widget/token support for controlled project embeds

---

## 🧱 Tech Stack

- **Backend:** PHP
- **Database:** MySQL (InnoDB)
- **Dependency manager:** Composer
- **Mail/OTP library:** `phpmailer/phpmailer`
- **Frontend:** server-rendered PHP + CSS/JS
- **Architecture style:** modular monolith with file-based routes and shared domain helpers

---

## 🗂️ Project Areas

```text
/
├─ auth/                  # user auth and OTP flows
├─ admin/                 # admin panel + RBAC + moderation tools
├─ api/                   # JSON endpoints
├─ devhub/                # developer/project-side pages and workflows
├─ includes/              # shared config, helpers, and services
├─ assets/                # CSS, images, and static frontend assets
├─ uploads/               # runtime user/project uploads (ignored in Git)
├─ database/migrations/   # schema changes and seed files
└─ docs/                  # architecture, security, API, roadmap, etc.
```

---

## 🔐 Security Highlights

- Password hashing via `password_hash` / `password_verify`
- OTP expiry, cooldown, and attempt controls
- Admin CSRF protections (`adminCsrfToken`, `requireAdminCsrf`)
- Anti-abuse event logging (`user_security_signals`, `fraud_events`)
- Dedicated **Security Management** admin workflow
- Environment-based configuration using `.env` / `.env.local`

> Before any production deployment, rotate sensitive credentials, enforce production-safe configuration, and review the security checklist in [`docs/SECURITY.md`](docs/SECURITY.md).

---

## ⚙️ Local Setup

1. Clone into your web root, for example:

   ```bash
   c:/xampp/htdocs/coinrex
   ```

2. Install dependencies:

   ```bash
   composer install
   ```

3. Create the database and import schema:
   - `database/migrations/recreate_db.sql` for the main schema
   - `database/migrations/admin_seed.sql` for optional admin seed data

4. Apply newer migrations from `database/migrations/` if needed.

5. Copy `.env.example` to `.env` and configure:
   - database connection
   - app secrets
   - SMTP settings

6. Ensure writable runtime directories exist:
   - `uploads/`
   - `devhub/logs/`

---

## 📧 SMTP / OTP Configuration

Set these values in `.env` or `.env.local`:

```env
COINREX_SMTP_HOST=smtp.gmail.com
COINREX_SMTP_PORT=587
COINREX_SMTP_SECURE=tls
COINREX_SMTP_USERNAME=your-email@example.com
COINREX_SMTP_PASSWORD=your-app-password
COINREX_MAIL_FROM=your-email@example.com
```

If you use Gmail, use an **App Password** instead of your normal account password.

---

## Public Repository Safety

This repository is prepared for GitHub publishing with security in mind.

### Safe to commit

- source code
- documentation
- database migrations
- `composer.json` and `composer.lock`
- `.env.example`
- `.gitignore`

### Do not commit

- `.env` / `.env.local`
- live credentials or API secrets
- logs
- runtime uploads or user-generated files
- local-only archives, dumps, or machine-specific files

If any secret has ever been exposed locally or in a prior repo history, rotate it before treating the repository as production-ready.

---

## 📚 Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Security](docs/SECURITY.md)
- [Auth](docs/AUTH.md)
- [API](docs/API.md)
- [System Health](docs/SYSTEM_HEALTH.md)
- [Widgets](docs/WIDGETS.md)
- [Roadmap](docs/ROADMAP.md)
- [AI Context](docs/AI_CONTEXT.md)

---

## 🛣️ Roadmap Direction

CoinRex is evolving in phases:

- **Phase 1:** security and configuration hardening
- **Phase 2:** modular refactor and maintainability improvements
- **Phase 3:** platform scalability and ecosystem evolution

See the full roadmap in [`docs/ROADMAP.md`](docs/ROADMAP.md).

---

## 🤝 Contributing

At the current stage, contributions should stay **security-first** and **review-friendly**:

- never commit secrets or real environment files
- prefer migrations over runtime schema mutation
- keep commits focused and easy to review
- update `docs/` when behavior or architecture changes

Future improvements may include a dedicated `CONTRIBUTING.md`, issue templates, screenshots, and CI checks.

---

## 🔗 Repository Structure Around CoinRex

- **Main application repo:** [`coinrex-platform`](https://github.com/CoinRex-net/coinrex-platform)
- **Supporting/legacy docs repo:** [`coinrex-docs`](https://github.com/CoinRex-net/coinrex-docs)

The long-term goal is for `coinrex-platform` to remain the primary codebase, while `coinrex-docs` can serve as a lightweight public documentation, concept, or archive repository that points back to the main project.

---

## 👑 Vision

CoinRex aims to become a more trusted environment for evaluating crypto opportunities by combining:

- verified user participation
- evidence-backed reviews
- reward-based progression
- stronger moderation and anti-abuse controls
