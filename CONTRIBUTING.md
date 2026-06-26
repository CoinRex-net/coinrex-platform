# Contributing to CoinRex

Thank you for your interest in contributing to CoinRex! This document provides guidelines and instructions for contributing.

## Code of Conduct

By participating in this project, you agree to maintain a respectful and constructive environment.

## How to Contribute

### Reporting Bugs

1. Check the [issue tracker](https://github.com/CoinRex-net/coinrex-platform/issues) for existing reports
2. If not found, create a new issue using the **Bug Report** template
3. Include detailed steps to reproduce, environment details, and screenshots if applicable

### Suggesting Features

1. Open a new issue using the **Feature Request** template
2. Clearly describe the problem and proposed solution
3. Tag with appropriate labels

### Pull Requests

1. Fork the repository
2. Create a feature branch from `develop`:
   ```bash
   git checkout -b feature/your-feature-name
   ```
3. Make your changes following our coding standards
4. Write/update tests for your changes
5. Run the check suite:
   ```bash
   composer check
   ```
6. Push and open a Pull Request against `develop`

## Development Setup

### Prerequisites

- PHP 8.1 or higher
- MySQL 8.0+
- Composer

### Local Setup

```bash
# Clone the repository
git clone https://github.com/CoinRex-net/coinrex-platform.git
cd coinrex-platform

# Install dependencies
composer install

# Copy environment config
cp .env.example .env
# Edit .env with your database credentials

# Import database schema
mysql -u root -p koinrex < database/migrations/recreate_db.sql

# Run any pending migrations
# (Apply migration files from database/migrations/ in order)
```

## Coding Standards

- Follow **PSR-12** coding style
- Use **PSR-4** autoloading for classes in `src/`
- Write PHPDoc blocks for all classes, methods, and functions
- Keep functions focused and single-purpose
- Use prepared statements for all database queries
- Never hardcode secrets or credentials

## Testing

```bash
# Run all tests
composer test

# Run coding standards check
composer phpcs

# Run static analysis
composer phpstan
```

## Security

- Never commit `.env` files or real credentials
- Always use prepared statements for SQL queries
- Add CSRF protection to state-changing endpoints
- Validate and sanitize all user input
- Report security vulnerabilities privately to support@coinrex.com

## Documentation

- Update `docs/` when changing behavior or architecture
- Keep `README.md` up to date with setup changes
- Document new API endpoints in `docs/API.md`
- Update `docs/ARCHITECTURE.md` for structural changes

## Commit Guidelines

- Use clear, descriptive commit messages
- Prefix commits with the area changed:
  - `[auth]` - Authentication changes
  - `[admin]` - Admin panel changes
  - `[api]` - API endpoint changes
  - `[reward]` - Reward system changes
  - `[taskhub]` - TaskHub changes
  - `[security]` - Security fixes
  - `[docs]` - Documentation updates
  - `[refactor]` - Code refactoring
  - `[test]` - Test additions/changes

## Questions?

Open a discussion in the GitHub repository or contact the team.
