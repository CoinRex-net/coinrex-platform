# CoinRex

CoinRex is a PHP/MySQL platform for crypto project reviews, task-based user progression, reward accounting, and admin moderation.

It is implemented as a **modular monolith** with file-based routes and shared domain helpers.

---

## 🚀 Core Capabilities

- User onboarding with OTP verification
- Review submission with proof flow and moderation
- TaskHub/BoostHub progression for beginner accounts
- Reward ledger + claim snapshot lifecycle
- Referral + level state model (Beginner / Pro / Expert)
- Admin control center for users, projects, reviews, tasks, rewards
- Security management workflow (flag/warn/suspend/module blocks)

---

## 🌍 Why this repository exists

This repository helps present CoinRex as a maintainable product, not just a local PHP project. It is intended to:

- document the platform architecture and roadmap
- make local setup easier for future contributors
- provide a safe public codebase without exposing production secrets
- support future collaboration, issue tracking, and feature planning

---

## 🧱 Tech Stack

- **Backend:** PHP
- **Database:** MySQL (InnoDB)
- **Dependency manager:** Composer
- **Key library:** `phpmailer/phpmailer`
- **Frontend:** server-rendered PHP + CSS/JS

---

## ⚙️ Local Setup

1. Clone into web root (e.g., `c:/xampp/htdocs/coinrex`).
2. Install dependencies:

   ```bash
   composer install
   ```

3. Create database and import schema:
   - `recreate_db.sql` (recommended full schema)
   - `admin_seed.sql` (optional admin seed)
4. Apply latest migrations in `database/migrations/`.
5. Copy `.env.example` to `.env` (or create `.env.local`) and fill in your database + SMTP settings.
6. Keep real credentials out of the repository; local env files are ignored by Git.
7. Ensure writable paths exist:
   - `uploads/`
   - `devhub/logs/`

### Public repository safety notes

If you publish CoinRex on GitHub:

- **Do commit** source code, documentation, migrations, and `.env.example`
- **Do not commit** `.env`, `.env.local`, live credentials, logs, or user-uploaded files
- Uploaded files inside `uploads/` should normally remain local/private unless you intentionally add sanitized demo assets
- Rotate any secret that was ever stored in a tracked file before making the repo public

### SMTP configuration for OTP

OTP email delivery requires these variables to be set in `.env` or `.env.local`:

```env
COINREX_SMTP_HOST=smtp.gmail.com
COINREX_SMTP_PORT=587
COINREX_SMTP_SECURE=tls
COINREX_SMTP_USERNAME=your-email@example.com
COINREX_SMTP_PASSWORD=your-app-password
COINREX_MAIL_FROM=your-email@example.com
```

If you use Gmail, `COINREX_SMTP_PASSWORD` should be an **App Password**, not your normal Gmail login password.

---

## 🗂️ Project Structure

```text
/
├─ auth/                  # user auth and OTP flows
├─ admin/                 # admin panel + RBAC + moderation tools
├─ api/                   # JSON endpoints
├─ devhub/                # developer-side pages/workflows
├─ includes/              # shared config/functions/services
├─ assets/                # CSS/images/static assets
├─ uploads/               # user/project/proof uploads
├─ database/migrations/   # incremental SQL migrations
└─ docs/                  # project documentation
```

---

## 🔐 Security & Trust Highlights

- Password hashing via `password_hash` / `password_verify`
- OTP with expiry + attempt/cooldown controls
- Admin CSRF protections (`adminCsrfToken`, `requireAdminCsrf`)
- Anti-abuse signal logging (`user_security_signals`, `fraud_events`)
- Dedicated Admin **Security Management** page for actions:
  - warning
  - suspension
  - temporary TaskHub/BoostHub/Review module blocks

> Important: rotate any exposed credentials and enforce production-safe config defaults before deployment.

---

## 📚 Documentation Index

- [Architecture](docs/ARCHITECTURE.md)
- [Database](docs/DATABASE.md)
- [Security](docs/SECURITY.md)
- [Auth](docs/AUTH.md)
- [API](docs/API.md)
- [System Health](docs/SYSTEM_HEALTH.md)
- [Roadmap](docs/ROADMAP.md)
- [AI Context](docs/AI_CONTEXT.md)

---

## 🛣️ Roadmap

See [docs/ROADMAP.md](docs/ROADMAP.md):

- Phase 1: security and configuration hardening
- Phase 2: modular refactor and maintainability
- Phase 3: scalability and platform evolution

---

## 🤝 Contributing

At this stage, contributions should follow a security-first approach:

- never commit secrets or real environment files
- prefer database migrations over runtime schema mutation
- document behavior changes in `docs/` when relevant
- keep changes focused and easy to review

If you plan to collaborate publicly, consider adding Issues, a license, and a dedicated `CONTRIBUTING.md` file next.
