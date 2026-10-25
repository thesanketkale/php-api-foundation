# Changelog

All notable changes to `php-api-foundation` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Planned
- WebAuthn / FIDO2 Passkey authentication support.
- Redis rate limiter driver for distributed deployments.

---

## [0.1.0] - 2026-10-25

### Added
- **Core Architecture & Standards**:
  - PHP 8.3+ with `declare(strict_types=1);` across all classes, PSR-12 code style, PSR-4 autoloading, and PHPStan analysis at maximum level.
  - PSR-11 dependency injection container powered by PHP-DI (`config/dependencies.php`).
  - Immutable configuration loader (`src/Support/Config.php`) with fail-fast type casting and environment validation.
  - Centralized RFC 9457 Problem Details error handler emitting `application/problem+json` envelopes.
- **Authentication & Security**:
  - Stateless HS256 JWT access tokens with configurable expiration (default 15 minutes).
  - Cryptographically secure rotating refresh tokens with SHA-256 database storage.
  - Token family revocation mechanism immediately detecting token replay attacks and invalidating compromised families.
  - Password hashing utilizing Argon2id with OWASP baseline memory and iteration costs.
  - PSR-15 middleware pipeline: Request ID, Security Headers (CSP, HSTS, X-Frame-Options), CORS, Rate Limiting, JSON Body Parser, JWT Authentication, and Monolog Access Logging.
- **Persistence & Schema Migrations**:
  - PDO prepared statement database layer supporting SQLite and MySQL 8.0 with real prepares and exception error mode.
  - Reversible database migrations using Phinx (`db/migrations/`) covering users, refresh tokens, and notes tables.
- **Domain & HTTP Actions**:
  - User domain entity, password value object, and user repository.
  - Note domain entity, pagination value object, and note repository with search capability.
  - REST actions for registration, login, token refresh, logout, profile retrieval (`/v1/me`), and notes CRUD.
  - Health check endpoints for container liveness (`/v1/health`) and database readiness (`/v1/health/ready`).
- **Contract & Testing**:
  - OpenAPI 3.1 contract specification (`openapi/openapi.yaml`) linted with Spectral.
  - Comprehensive PHPUnit 11 test suite comprising unit, integration, and contract tests (33 tests, 124 assertions).
  - Hurl declarative HTTP integration test script (`tests/Hurl/api.hurl`).
- **Deployment Automation & Cloud Pipelines**:
  - Shared hosting FTP/SFTP deployment automation (`deploy/ftp/deploy.sh`) with timestamped staging, near-atomic directory swap, HMAC-authenticated one-time migration execution, and automatic rollback on smoke test failure.
  - AWS ECS Fargate deployment workflow (`.github/workflows/deploy-aws.yml`) and IAM policies using OpenID Connect (OIDC).
  - Azure App Service deployment workflow (`.github/workflows/deploy-azure.yml`) and provisioning script using Microsoft Entra ID workload identity federation.
  - GitHub Actions CI workflow matrix across PHP 8.2, 8.3, and 8.4 with MySQL service container and Gitleaks secret scanning.
  - Release automation workflow via Release Please.
- **Documentation**:
  - 11-point README with Mermaid system architecture, token rotation, and deployment diagrams.
  - Architecture Decision Records (ADR-0001 through ADR-0004).
  - Operational runbooks for shared hosting, AWS ECS OIDC, and Azure App Service.
  - Quality and verification audit log (`docs/verification-log.md`).
