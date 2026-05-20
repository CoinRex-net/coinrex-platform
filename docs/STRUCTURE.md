# CoinRex Repository Structure

> Professional directory layout for the CoinRex platform.

## Top-Level Layout

```text
/
├─ admin/          # Admin panel — moderation, settings, user/project/review management
├─ api/            # JSON API endpoints (consumed by JS frontend)
├─ assets/         # Static frontend assets (CSS, images, JS)
├─ auth/           # User authentication, registration, OTP verification
├─ database/       # Schema migrations and seed files
├─ devhub/         # Developer/project-owner hub — applications, widgets, reviews
├─ docs/           # Architecture, security, API, roadmap, structure, and plans documentation
├─ includes/       # Shared PHP bootstrap, config, helpers, services, and components
├─ tools/          # Developer utilities, maintenance scripts, and refactoring tools
├─ uploads/        # Runtime user/project uploads (gitignored)
│
├─ index.php       # Public landing page
├─ about.php       # About CoinRex
├─ blog.php        # Blog listing
├─ blog-category.php  # Blog category filter
├─ blog-post.php   # Single blog post
├─ blog-tag.php    # Blog tag filter
├─ contact.php     # Contact/support form
├─ cookies.php     # Cookie policy page
├─ dashboard.php   # User dashboard (RexHub)
├─ faq.php         # Frequently asked questions
├─ home.php        # Alternative home page
├─ privacy.php     # Privacy policy
├─ terms.php       # Terms of service
├─ profile.php     # User profile page
├─ projects.php    # Project listing
├─ project-detail.php  # Single project detail page
├─ reviews.php     # Public reviews listing
├─ my-reviews.php  # User's own reviews
├─ submit-review.php   # Review submission form
├─ notifications.php   # User notifications
├─ claims.php      # Claim center
├─ reward-history.php  # Reward ledger history
├─ boosthub.php    # BoostHub — task/boost system
├─ taskhub.php     # TaskHub — micro-mission system
├─ widget.js       # Embeddable widget script
│
├─ composer.json   # PHP dependency manifest
├─ composer.lock   # Locked dependency versions
├─ .env.example    # Environment configuration template
├─ .gitignore      # Git exclusion rules
└─ README.md       # Project overview and setup guide
```

## Domain Breakdown

### `admin/` — Admin Panel
```
admin/
├─ index.php              # Admin login
├─ login.php              # Admin authentication
├─ logout.php             # Admin logout
├─ dashboard.php          # Admin dashboard
├─ users.php              # User management
├─ projects.php           # Project moderation
├─ reviews.php            # Review moderation
├─ messages.php           # Contact messages
├─ rewards.php            # Reward configuration
├─ reward-ledger.php      # Reward transaction log
├─ reward-users.php       # User reward management
├─ referrals.php          # Referral tracking
├─ blog*.php              # Blog CRUD
├─ settings.php           # Platform settings
├─ security-management.php # Security/abuse controls
├─ task-management.php    # Task configuration
├─ taskhub-review.php     # TaskHub review queue
├─ quiz-manager.php       # Quiz management
├─ boosthub.php           # BoostHub admin
├─ developers.php         # Developer management
├─ admins.php             # Admin account management
├─ create_admin.php       # Create admin
├─ edit_admin.php         # Edit admin
├─ delete_admin.php       # Delete admin
├─ list_admins.php        # List admins
├─ assets/                # Admin-specific CSS/JS/images
└─ includes/              # Admin-specific config and auth helpers
```

### `api/` — JSON API Endpoints
```
api/
├─ _bootstrap.php         # Shared API bootstrap (auth, CORS, response helpers)
├─ add_reward.php         # Add reward entry
├─ claim_mystery_box.php  # Mystery box claim
├─ claim_status.php       # Claim eligibility check
├─ complete_mini_task.php # Complete a mini task
├─ generate_claim.php     # Generate claim snapshot
├─ get_balance.php        # Get user balance
├─ get_mini_tasks.php     # List mini tasks
├─ get_notifications.php  # Get user notifications
├─ get_taskhub_state.php  # Get TaskHub state
├─ mark_all_notifications_read.php
├─ mark_notification_read.php
├─ mark_taskhub_learning.php
├─ reward_overview.php    # Reward summary
├─ submit_taskhub_task.php
├─ learning/              # Learning module API
└─ v1/                    # Version 1 API (widget/rating)
```

### `auth/` — User Authentication
```
auth/
├─ auth.php               # Login/register
├─ forgot.php             # Password reset
├─ logout.php             # Logout
└─ verify_email.php       # OTP verification
```

### `devhub/` — Developer Hub
```
devhub/
├─ index.php              # DevHub landing
├─ apply.php              # Developer application
├─ reviews.php            # Developer reviews
├─ notifications.php      # Developer notifications
├─ terms.php              # DevHub terms
├─ widget-api.php         # Widget API documentation
├─ assets/                # DevHub-specific CSS/JS
├─ includes/              # DevHub-specific helpers
├─ logs/                  # DevHub runtime logs (gitignored)
├─ pages/                 # Sub-pages
└─ projects/              # Project management pages
```

### `includes/` — Shared Core
```
includes/
├─ config.php             # Environment, DB, session bootstrap
├─ functions.php          # Core domain helpers (large, being modularized)
├─ functions_legacy_backup.php  # Legacy backup (kept for reference)
├─ header.php             # Shared HTML header + navigation
├─ footer.php             # Shared HTML footer
├─ functions/             # Modular function files
│   └─ taskhub.php        # TaskHub-specific helpers
├─ services/              # Service classes
└─ taskhub/               # TaskHub component templates
```

### `assets/` — Static Frontend
```
assets/
├─ css/                   # Stylesheets
├─ images/                # Images and icons
├─ js/                    # JavaScript files
└─ uploads/               # Uploaded media (gitignored)
```

### `database/` — Schema & Migrations
```
database/
└─ migrations/            # SQL migration files
```

### `docs/` — Documentation
```
docs/
├─ ARCHITECTURE.md        # System architecture
├─ STRUCTURE.md           # This file — repo structure guide
├─ API.md                 # API documentation
├─ AUTH.md                # Auth system docs
├─ DATABASE.md            # Database schema docs
├─ SECURITY.md            # Security model
├─ ROADMAP.md             # Development roadmap
├─ WIDGETS.md             # Widget integration docs
├─ AI_CONTEXT.md          # AI assistant context
├─ SYSTEM_HEALTH.md       # Health check guide
├─ TESTING_MODE_RESTORE.md
├─ theme.md               # Theme documentation
├─ purpose-and-use.md     # Platform purpose & use guide
├─ taskhub.md             # TaskHub system documentation
├─ coinrex-docs-readme-template.md  # Docs repo template
└─ plans/                 # UI/theme upgrade plans and design documents
    ├─ theme-upgrade-visual.md
    └─ ui-theme-upgrade-plan.md
```

### `tools/` — Developer Utilities
```
tools/
├─ retheme_coinrex.ps1          # PowerShell theme refactoring script
├─ split_functions.php          # PHP function splitter utility
├─ split_functions.py           # Python function splitter utility
├─ check_db.php                 # Database schema checker
├─ clear_opcache.php            # PHP opcache reset utility
├─ composer-setup.php           # Composer installer (one-time use)
├─ generate-widget-token.php    # Widget token generator (dev/debug)
├─ indexV2.php                  # Alternate landing page version
├─ test_taskhub.php             # Test version of TaskHub
├─ tmp_list_project_slugs.php   # Temporary debug script
└─ widget-test.php              # Widget integration test page
```

## File Classification Guide

### Production files (keep in root)
These are live entry points accessed by users:
`index.php`, `about.php`, `blog*.php`, `contact.php`, `cookies.php`, `dashboard.php`, `faq.php`, `home.php`, `privacy.php`, `terms.php`, `profile.php`, `projects.php`, `project-detail.php`, `reviews.php`, `my-reviews.php`, `submit-review.php`, `notifications.php`, `claims.php`, `reward-history.php`, `boosthub.php`, `taskhub.php`, `widget.js`

### Configuration files (keep in root)
`composer.json`, `composer.lock`, `.env.example`, `.gitignore`, `README.md`

### Utility/dev files (moved to `tools/`)
Files that are helpful for development/maintenance but not part of the production app:
- `check_db.php` → database schema checker
- `clear_opcache.php` → opcache reset utility
- `tmp_list_project_slugs.php` → temporary debug script
- `test_taskhub.php` → test version of TaskHub
- `widget-test.php` → widget integration test page
- `composer-setup.php` → Composer installer (one-time use)
- `indexV2.php` → alternate landing page version

### Legacy/backup files (kept for reference)
- `includes/functions_legacy_backup.php` — preserved for migration safety

## Cleanup History

| Date | Change | Status |
|------|--------|--------|
| 2026-05-20 | Created STRUCTURE.md | ✅ |
| 2026-05-20 | Moved utility files to tools/ | ✅ |
| 2026-05-20 | Deleted dead files (taskhub-features.css, index.css, newupdate.png) | ✅ |
| 2026-05-20 | Deleted coinrex-docs-local/ (unreferenced local copy) | ✅ |
| 2026-05-20 | Moved plans/ to docs/plans/ | ✅ |
