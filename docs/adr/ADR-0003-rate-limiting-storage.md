# ADR-0003: Rate Limiting Storage Strategy (APCu & Atomic File-Lock vs. Redis)

## Status
Accepted

## Date
2026-10-25

## Context
Rate limiting is a non-negotiable security requirement for REST APIs to protect authentication endpoints (`/v1/auth/login`, `/v1/auth/register`, `/v1/auth/refresh`) against credential stuffing, brute-force attacks, and denial of service (DoS).

Traditional enterprise architectures rely on a distributed in-memory cache such as **Redis** or **Memcached** to store sliding window counters or token buckets. However, `php-api-foundation` must satisfy two contrasting operational environments:
1. **Low-cost shared hosting environments (cPanel/DirectAdmin)**: Shared hosting providers rarely offer dedicated Redis or Memcached instances to standard accounts. Requiring an external cache service prevents the foundation from being a true turnkey solution for shared hosting users.
2. **Containerized and single-node cloud environments**: Containers require high-speed, local rate-limit evaluation without mandating an additional network hop or introducing an external single point of failure for basic deployments.

## Decision
We implement a dual-driver rate limiting architecture in `src/Infrastructure/RateLimit/` coordinated by `RateLimiterFactory`:
1. **Primary Driver: APCu (`ApcuRateLimiter`)**:
   - Uses PHP's native Alternative PHP Cache User Cache (`apcu_inc`, `apcu_fetch`, `apcu_store`).
   - Counter increments and time-to-live (TTL) expiration occur directly in shared process memory with atomic memory operations.
   - Latency is sub-microsecond (`< 0.05ms`) with zero network overhead.
   - APCu is pre-installed or easily enabled via cPanel PHP extensions on virtually all modern shared hosting platforms and PHP-FPM container images.
2. **Fallback Driver: Atomic File Locking (`FileRateLimiter`)**:
   - Automatically selected when APCu extension is disabled or running under CLI without `apc.enable_cli=1`.
   - Stores rate-limiting bucket metadata in isolated JSON-encoded files in the local storage directory (`storage/cache/rate_limit/`).
   - Uses non-blocking or blocking atomic advisory file locks (`flock($fp, LOCK_EX)`) to guarantee thread-safe read-modify-write cycles and prevent race conditions across concurrent PHP workers.
   - Self-prunes expired counter files to prevent inode exhaustion.
3. **Pluggable Architecture for Redis**:
   - Both drivers implement `RateLimiterInterface`.
   - Teams wishing to deploy multi-node container clusters behind a round-robin load balancer can implement a `RedisRateLimiter` conforming to the same interface without modifying the HTTP middleware (`RateLimitMiddleware`).

## Consequences

### Positive
- **Zero External Infrastructure Dependency**: The API runs securely and throttles abuse out-of-the-box on $3/month shared hosting without configuring Redis, Memcached, or external database tables.
- **Extreme Performance**: APCu in-memory rate checking adds virtually zero overhead to the HTTP request lifecycle.
- **Fail-Safe Fallback**: If APCu is unavailable or cleared, the file-lock mechanism guarantees rate enforcement without crashing the API.
- **Uniform HTTP Semantics**: Both drivers emit standard rate-limiting headers:
  - `X-RateLimit-Limit`
  - `X-RateLimit-Remaining`
  - `X-RateLimit-Reset`
  - `Retry-After` (when returning HTTP `429 Too Many Requests`).

### Negative
- **Single-Node / Per-Worker Scope**:
  - In a horizontally scaled multi-container cloud environment without sticky sessions, each container maintains its own local APCu cache. An attacker distributing requests across $N$ instances could theoretically send $N \times \text{limit}$ requests before throttling occurs.
  - Mitigation: For multi-node cloud deployments requiring globally synchronized limits, teams configure AWS WAF rate-based rules at the ALB level or swap in a Redis driver.
- **Disk I/O under File Fallback**:
  - When falling back to `FileRateLimiter` under heavy concurrent load, filesystem lock contention can increase disk I/O wait times.
  - Mitigation: APCu is enabled by default in our Docker image and is widely available on shared hosting.

### Neutral
- The storage driver is auto-detected at runtime based on `extension_loaded('apcu')` and `ini_get('apc.enabled')`, with an explicit override available via `.env` (`RATE_LIMIT_DRIVER=apcu|file`).
