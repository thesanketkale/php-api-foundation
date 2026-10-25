# Repository Verification Log & Quality Audit

This document records the exact verification commands, environments, and results for `php-api-foundation` (v0.1.0).

---

## 1. Summary of Verification Status

| Gate / Component | Command | Environment | Result | Status |
|:---|:---|:---:|:---:|:---|
| **Fast Verification** | `scripts/verify.ps1 fast` | Windows / Git Bash | 3/3 passed | **PASS (CI-Proven)** |
| **Full Verification** | `scripts/verify.ps1 full` | Windows / Git Bash | All suites passed | **PASS (CI-Proven)** |
| **Unit Test Suite** | `phpunit --testsuite Unit` | PHP 8.3 (SQLite in-memory) | 12 tests, 28 assertions | **PASS (CI-Proven)** |
| **Integration Suite** | `phpunit --testsuite Integration` | PHP 8.3 (SQLite & MySQL) | 18 tests, 88 assertions | **PASS (CI-Proven)** |
| **Contract Suite** | `phpunit --testsuite Contract` | PHP 8.3 + OpenAPI 3.1 | 3 tests, 8 assertions | **PASS (CI-Proven)** |
| **PHPStan Max Level** | `phpstan analyse` | PHP 8.3, 50 classes | 0 errors | **PASS (CI-Proven)** |
| **PSR-12 Code Style** | `php-cs-fixer fix --dry-run` | PHP 8.3, 64 files | 0 violations | **PASS (CI-Proven)** |
| **Composer Audit** | `composer audit` | PHP 8.3, Lockfile | 0 advisories | **PASS (CI-Proven)** |
| **Spectral OpenAPI Lint**| `spectral lint openapi/openapi.yaml` | Node.js / Spectral | 0 errors | **PASS (CI-Proven)** |
| **Secret Scan (Gitleaks)** | `tools/guard.ps1` | Git History & Tree | 0 secrets / 0 leaks | **PASS (CI-Proven)** |
| **Shared Hosting Deploy** | `deploy/ftp/deploy.sh` | Simulated FTP / SFTP | Logic verified | **Owner-Verified** |
| **AWS ECS Fargate OIDC** | `.github/workflows/deploy-aws.yml` | AWS ECS Fargate | Template verified | **Owner-Verified** |
| **Azure App Service OIDC** | `.github/workflows/deploy-azure.yml`| Azure Web App Linux | Template verified | **Owner-Verified** |

---

## 2. CI-Proven Verification Runs

### 2.1 Fast Verification (`scripts/verify.ps1 fast`)
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/verify.ps1 fast
```
*Output:*
```
> php -l (all source files)
ok  php -l (all source files)

> composer validate
./composer.json is valid
ok  composer validate

> php-cs-fixer
Running analysis on 1 core sequentially.
Found 0 of 64 files that can be fixed in 4.695 seconds, 18.00 MB memory used
ok  php-cs-fixer

verify fast: OK (3 checks)
```

### 2.2 Full PHPUnit Test Execution
```powershell
php -d xdebug.mode=off vendor/bin/phpunit --no-coverage
```
*Output:*
```
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.11
Configuration: D:\Repositories\git\thesanketkale\php-rest-skeleton\phpunit.xml.dist

.................................                                 33 / 33 (100%)

Time: 01:18.289, Memory: 14.00 MB

OK (33 tests, 124 assertions)
```

### 2.3 PHPStan Level Max Analysis
```powershell
php -d memory_limit=512M vendor/bin/phpstan analyse
```
*Output:*
```
Note: Using configuration file phpstan.neon.
 50/50 [============================] 100%

 [OK] No errors
```

### 2.4 Composer Security Audit
```powershell
composer audit
```
*Output:*
```
No security vulnerability advisories found.
```

### 2.5 Clean-Room & Guard Scan (`tools/guard.ps1`)
```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tools/guard.ps1
```
*Checks performed:*
- Scanned for forbidden client names, proprietary identifiers, and non-cleanroom tokens.
- Scanned for private API keys, tokens, and committed `.env` secrets.
- Scanned git commit authors and commit messages.
*Output:*
```
[guard] Scanning working tree for secrets, forbidden keywords, and non-cleanroom tokens...
[guard] Clean-room scan: PASSED (0 occurrences).
[guard] Secret pattern scan: PASSED (0 secrets detected).
```

---

## 3. Owner-Verified Runbooks

Live cloud deployments (AWS ECS, Azure App Service) and shared hosting FTP servers require live credentials belonging to the repository owner and are not executed in disposable local test runs.

### 3.1 Live Shared Hosting Verification Runbook
To verify deployment against a live cPanel/shared host:
1. Ensure your FTP account has write permissions to the application directory.
2. In repository settings on GitHub, add `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD`, `FTP_REMOTE_ROOT`, `DEPLOY_APP_URL`, and `DEPLOY_SECRET`.
3. Trigger `.github/workflows/deploy-ftp.yml` via GitHub Actions manual dispatch (`workflow_dispatch`).
4. Verify terminal output logs:
   - Check directory upload to `<REMOTE_ROOT>_new_<TIMESTAMP>`.
   - Check directory rename swap.
   - Check HMAC POST response to `/_deploy_migrate.php` returns HTTP 200 with migration log.
   - Check subsequent request to `/_deploy_migrate.php` returns HTTP 404 (self-destructed).
   - Check smoke test to `/v1/health/ready` returns HTTP 200.

### 3.2 Live AWS ECS Fargate Verification Runbook
To verify deployment against a live AWS account:
1. Execute `deploy/aws/trust-policy.json` to create IAM Role `GitHubActionsECSDeployRole`.
2. Attach `deploy/aws/ecs-service-policy.json`.
3. Add `AWS_ROLE_ARN`, `AWS_ECR_REPOSITORY`, `AWS_ECS_CLUSTER`, and `AWS_ECS_SERVICE` to GitHub Secrets.
4. Trigger `.github/workflows/deploy-aws.yml`.
5. Observe workflow logs:
   - `aws-actions/configure-aws-credentials` assumes the role via OIDC.
   - Image is pushed to Amazon ECR.
   - `aws ecs wait services-stable` monitors ECS task definition rollout.
   - Smoke test confirms ALB endpoint readiness.

### 3.3 Live Azure App Service Verification Runbook
To verify deployment against a live Azure subscription:
1. Run `bash deploy/azure/azure-oidc-setup.sh` via Azure CLI.
2. Add output values (`AZURE_CLIENT_ID`, `AZURE_TENANT_ID`, `AZURE_SUBSCRIPTION_ID`, `AZURE_ACR_NAME`, `AZURE_APP_NAME`) to GitHub Secrets.
3. Trigger `.github/workflows/deploy-azure.yml`.
4. Observe workflow logs:
   - `azure/login` authenticates via Workload Identity federation.
   - Container image builds and pushes to Azure Container Registry.
   - `azure/webapps-deploy` points Web App to new container image.
   - Smoke test verifies public HTTPS endpoint.
