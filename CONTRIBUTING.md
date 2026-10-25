# Contributing to php-api-foundation

Thank you for your interest in contributing to `php-api-foundation`! This repository provides a dependency-light, production-grade PHP 8.3 REST API foundation engineered for shared hosting (cPanel/FTP) and modern cloud container runtimes (AWS ECS Fargate, Azure App Service).

We welcome issues, feedback, and pull requests that adhere to our architectural standards and design principles.

---

## Code of Conduct

All contributors and maintainers are expected to adhere to our [Code of Conduct](CODE_OF_CONDUCT.md). Please treat everyone with respect, kindness, and empathy.

---

## Architectural Principles & Constraints

Before proposing changes, please review `.pipeline/DESIGN.md` and `docs/architecture.md`. Key non-negotiables:

1. **Minimal Dependencies**: We intentionally avoid monolithic frameworks (Laravel, Symfony fullstack). Core routing uses `nikic/fast-route`, HTTP uses `nyholm/psr7` (PSR-7/PSR-17), pipeline uses `relay/relay` (PSR-15), and DI uses `php-di/php-di` (PSR-11). Do not add dependencies without prior discussion.
2. **Strict Typing & PSR-12**: Every PHP file MUST start with `<?php declare(strict_types=1);`. All properties, arguments, and return types must be explicitly typed.
3. **Database Access**: Exclusively via PDO prepared statements (`PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION`, `PDO::ATTR_EMULATE_PREPARES => false`, `utf8mb4`). String concatenation or interpolated SQL is forbidden.
4. **Standard Envelopes**:
   - Success responses: `{ "data": ..., "meta": ... }` (`application/json; charset=utf-8`).
   - Error responses: RFC 9457 Problem Details (`application/problem+json; charset=utf-8`).
5. **Zero Secrets in Code**: All configuration is parsed via `src/Support/Config.php` from `.env`. Never commit secrets or hardcoded credentials.

---

## Development Setup

### Prerequisites
- PHP 8.3+ CLI with extensions: `pdo`, `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `json`, `curl`, `fileinfo`
- Composer 2.7+
- Docker & Docker Compose (optional, for running MySQL 8.0 locally)

### Setup Steps
```bash
# 1. Clone repository
git clone https://github.com/thesanketkale/php-api-foundation.git
cd php-api-foundation

# 2. Copy environment template
cp .env.example .env

# 3. Install dependencies
composer install

# 4. Start local development server
php -S 0.0.0.0:8080 -t public
# API is accessible at http://localhost:8080
```

---

## Git Workflow

1. Create a branch from `main`:
   - Feature: `feat/<feature-slug>`
   - Bug fix: `fix/<bug-slug>`
   - Maintenance: `chore/<slug>`
2. Follow Conventional Commits: `<type>(<scope>): <short description>`.
3. Never push directly to `main`.
4. Ensure your branch is rebased on `main` before opening a pull request.

---

## Verification & Quality Gates

Run the verification script before opening a pull request. Both fast and full suites must be green:

```bash
# Fast verification (< 5s: php -l syntax check, composer validate, php-cs-fixer)
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify.ps1 fast
# or on Linux/macOS:
bash scripts/verify.sh fast

# Full verification (PHPStan max level, PHPUnit tests, composer audit)
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify.ps1 full
# or on Linux/macOS:
bash scripts/verify.sh full
```

### Formatting
Format code to PSR-12 using PHP-CS-Fixer:
```bash
vendor/bin/php-cs-fixer fix
```

### Static Analysis
Run PHPStan at maximum level:
```bash
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
```

---

## Pull Request Process

1. Ensure all verification checks pass locally.
2. Fill out the [Pull Request Template](.github/PULL_REQUEST_TEMPLATE.md) completely, referencing related issues.
3. If changing API endpoints, update `openapi/openapi.yaml` in the same commit.
4. Keep pull requests focused on a single change or feature.
5. All CI checks must pass before a maintainer reviews the PR.
