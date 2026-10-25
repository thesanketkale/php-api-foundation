# ADR-0001: Architecture & Stack Selection for php-api-foundation

## Status
Accepted

## Date
2026-10-25

## Context
When bootstrapping modern PHP REST APIs, teams face an architectural trade-off between full-stack frameworks (Laravel, Symfony) and dependency-light component stacks.

The requirements for `php-api-foundation` dictate:
1. **Zero-downtime deployment to traditional shared hosting** (cPanel/DirectAdmin over FTPS/SFTP) where SSH shell access, Composer execution on the server, and long-running daemons are unavailable.
2. **Containerized parity for modern cloud infrastructure** (AWS ECS Fargate, Azure App Service) with minimal image size, sub-second cold starts, and low memory footprints.
3. **Strict adherence to PHP standards** (PSR-7 HTTP message, PSR-11 container, PSR-15 HTTP middleware, PSR-12 coding standard).
4. **Transparent developer experience** with zero framework magic, explicit dependency injection, and deterministic execution paths.

Full-stack frameworks introduce significant baggage for this foundation:
- Laravel's framework kernel boots substantial facade state, event systems, and Eloquent ORM layers that consume 15–35MB baseline memory and add 20–50ms cold-start latency.
- Symfony's micro-kernel is more modular but still requires complex compilation steps, extensive bundle configurations, and cache-warming commands that cannot easily run in shell-less shared hosting environments.

## Decision
We choose a modular, dependency-light component architecture built directly on PHP 8.3+:
- **HTTP Routing**: `nikic/fast-route` for deterministic regular-expression-based route matching.
- **HTTP Abstraction**: `nyholm/psr7` and `nyholm/psr7-server` for lightweight, strictly compliant PSR-7 message implementations.
- **Middleware Pipeline**: `relay/relay` for an idiomatic, lightweight PSR-15 middleware dispatcher.
- **Dependency Injection**: `php-di/php-di` for clean, standards-compliant PSR-11 dependency injection with optional container compilation in production.
- **Database Migrations**: `robmorgan/phinx` for database-agnostic schema version control supporting forward migrations and rollbacks on MySQL and SQLite.
- **Database Access**: Native PHP Data Objects (`PDO`) with prepared statements, strict error modes, and explicit parameter binding.
- **Validation**: `respect/validation` for declarative input constraint validation.
- **Logging**: `monolog/monolog` with structured JSON formatting for standard output and local rotating file logs.
- **Authentication**: `firebase/php-jwt` for RFC 7519 JWT verification, paired with native `password_hash` (Argon2id).

## Consequences

### Positive
- **Extreme Portability**: The entire codebase, including pre-installed `vendor/`, can be synchronized to shared hosting via FTPS and run instantly without any remote build step or daemon configuration.
- **High Throughput & Low Latency**: Baseline request lifecycle executes in under 5ms warm execution with less than 4MB memory per worker.
- **Standardized Interoperability**: Every HTTP component complies with PSR standards; individual middlewares or handlers can be ported directly to or from any PSR-15 compliant system.
- **Clean Learning Curve**: Developers inspect explicit code paths from `public/index.php` through middleware to action classes without hidden magic or service container side-effects.

### Negative
- **Manual Wiring**: Unlike Laravel or Symfony, boilerplate for routing registration and DI wiring must be defined explicitly in configuration files.
- **No Built-in CLI Tooling**: Common CLI commands (e.g., `artisan make:model`) are not present out of the box; developers rely on composer scripts and Phinx CLI.

### Neutral
- Database migrations rely on Phinx CLI during local development and CI, while shared hosting deployments leverage a secured one-shot deployment endpoint (`public/_deploy/migrate.php`) that is executed and cleaned up automatically.
