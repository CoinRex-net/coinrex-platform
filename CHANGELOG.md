# Changelog

All notable changes to CoinRex Platform will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- PSR-4 autoloading with `CoinRex\` namespace under `src/`
- `.editorconfig` for consistent coding styles across editors
- `.gitattributes` for normalized line endings
- PHPUnit configuration (`phpunit.xml.dist`)
- PHPStan static analysis configuration (`phpstan.neon.dist`)
- PHPCS coding standards configuration (`phpcs.xml.dist`)
- GitHub CI workflow with PHPCS, PHPStan, PHPUnit, and security audit
- GitHub issue templates (bug report, feature request)
- GitHub pull request template
- `CONTRIBUTING.md` with contribution guidelines
- `CHANGELOG.md` for release tracking
- `tests/` directory for PHPUnit test suites

### Changed
- `TESTING_MODE` now reads from environment variable `COINREX_TESTING_MODE` (defaults to `false`)
- `composer.json` upgraded with PSR-4 autoload, dev dependencies, and scripts
- `.env.example` updated with `COINREX_TESTING_MODE` variable

### Security
- `TESTING_MODE` no longer hardcoded to `true` — production safety improved
