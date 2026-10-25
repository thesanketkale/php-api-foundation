# Changelog

All notable changes to `php-api-foundation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- Domain entity models and repository interfaces.
- OpenAPI 3.1 specification for Authentication, Notes, and Health.
- Phinx database migrations with rollback support for MySQL and SQLite.
- PSR-15 middleware pipeline (Request ID, Security Headers, CORS, Rate Limiting, JSON Body Parser, JWT Auth, Access Logging).
- PHPUnit test suite covering unit, integration, and OpenAPI contract validation.
- Deployment automation for FTP/SFTP near-atomic swaps, AWS ECS Fargate OIDC, and Azure App Service OIDC.

---

## [0.1.0] - 2026-10-25

### Added
- Initial project foundation and directory architecture.
- MIT License, Contributing Guide, Contributor Covenant 2.1 Code of Conduct, and Security Policy.
- Tooling configuration: Composer dependencies, PHPStan at `max` level, PHP-CS-Fixer for PSR-12, Spectral OpenAPI linter, and verification scripts.
- Architecture Decision Records (ADR-0001 through ADR-0004).
- Immutable, typed configuration loader (`src/Support/Config.php`) with fail-fast validation for mandatory environment variables.
- Dependency injection container definitions (`config/dependencies.php`) utilizing PHP-DI.
- Multi-stage Dockerfile and Docker Compose environment with MySQL 8.0 health checks.
- Public front controller (`public/index.php`), Apache rewrite rules (`public/.htaccess`), and robot crawling prevention (`public/robots.txt`).
