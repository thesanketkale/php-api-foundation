# Security Architecture & Threat Model

This document outlines the security architecture, cryptography standards, and defense-in-depth controls implemented across `php-api-foundation`.

---

## 1. Authentication & Token Security

### 1.1 Stateless JWT Access Tokens
- **Algorithm**: HMAC-SHA256 (`HS256`).
- **Signature Key**: Minimum 256-bit cryptographically secure random secret loaded from `JWT_SECRET`.
- **Claims**:
  - `sub`: User UUIDv4.
  - `iss`: Configurable application issuer string (`JWT_ISSUER`).
  - `iat`: Issued-at timestamp.
  - `exp`: Expiration timestamp (default 900 seconds / 15 minutes).
  - `jti`: Unique token identifier preventing collision.
- **Clock Skew Leeway**: 60-second tolerance for distributed server clock drift.

### 1.2 Refresh Token Rotation & Token Family Revocation
- **Storage**: Only SHA-256 hashes of raw refresh tokens are stored in the database (`hash('sha256', $rawToken)`). Raw tokens are never stored plaintext.
- **Single-Use Rotation**: Every successful call to `/v1/auth/refresh` immediately invalidates the presented refresh token and issues a new token pair.
- **Replay Attack Detection (Token Reuse)**:
  - If a client attempts to refresh using a token whose database record is already marked `revoked = 1`, the system detects an unauthorized replay attack (indicating the token was intercepted).
  - The `TokenFamilyService` immediately executes `revokeFamily($familyId)`, invalidating all active refresh tokens associated with that session family.
  - The client receives an RFC 9457 Problem Details response (`401 Unauthorized`) requiring interactive re-authentication.

---

## 2. Password Hashing

- **Algorithm**: Argon2id (`PASSWORD_ARGON2ID`).
- **Default Parameters**:
  - Memory cost: 65,536 KiB (64 MiB).
  - Time cost: 4 iterations.
  - Threads: 1 (single-threaded per request to prevent thread pool exhaustion).
- **Protection**: Provides maximum resistance against GPU/ASIC brute-force and side-channel attacks.

---

## 3. Database Security & Injection Prevention

- **Exclusive PDO Prepared Statements**: All SQL queries execute through parameterized prepared statements. `PDO::ATTR_EMULATE_PREPARES` is strictly disabled (`false`), ensuring MySQL and SQLite execute real server-side prepares.
- **Strict Error Mode**: `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` ensures SQL errors halt execution and trigger the centralized RFC 9457 error handler rather than returning partial results or leaking database structure.
- **UUID Identifiers**: All database tables use UUIDv4 (`CHAR(36)`) primary keys, eliminating sequential ID enumeration and IDOR scraping vulnerabilities.

---

## 4. HTTP & Transport Security

- **Security Headers Middleware**: Every HTTP response includes hardened security headers:
  ```http
  X-Frame-Options: DENY
  X-Content-Type-Options: nosniff
  Referrer-Policy: strict-origin-when-cross-origin
  Content-Security-Policy: default-src 'none'; frame-ancestors 'none';
  Strict-Transport-Security: max-age=31536000; includeSubDomains (production only)
  ```
- **CORS Middleware**: Explicit origin, method, and header whitelisting with pre-flight OPTIONS response caching.
- **RFC 9457 Problem Details**: Errors return standardized `application/problem+json` envelopes. Internal stack traces, raw SQL queries, and file paths are strictly redacted in non-debug mode.

---

## 5. Rate Limiting & Denial-of-Service Defense

- **Sliding Window Token Bucket**: Protects public endpoints (`/v1/auth/*`) from brute-force password guessing and token scanning.
- **Backends**:
  - `file`: Default driver utilizing local filesystem `flock()` atomic locking for zero-dependency operation.
  - `apcu`: In-memory atomic rate limiter for high-performance single-server instances.
- **Response Headers**: Conforms to standard IETF rate-limit headers:
  ```http
  X-RateLimit-Limit: 60
  X-RateLimit-Remaining: 58
  X-RateLimit-Reset: 1730000000
  ```

---

## 6. Infrastructure & Deployment Security

- **Zero Stored Cloud Credentials**: AWS ECS and Azure App Service deployments use GitHub Actions OpenID Connect (OIDC) identity federation with repository-scoped subject claims. No IAM access keys or service principal secrets are stored in GitHub Secrets.
- **Least-Privilege Workflows**: All GitHub Actions workflows declare top-level `permissions: {}` and minimal per-job permissions (`contents: read`, `id-token: write`).
- **Dependency & Secret Scanning**: Automated CI runs `composer audit` and `gitleaks` on every push and pull request.
