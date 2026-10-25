# Operational Guide: Shared Hosting Deployment (FTP/SFTP)

This guide documents the architecture, configuration, and verification process for deploying `php-api-foundation` to shared hosting environments (such as cPanel, Plesk, or standard Linux Apache/Nginx hosts) with near-atomic swap deployments, persistent file protection, and HMAC-authenticated database migration execution.

---

## 1. Overview & Architecture

Traditional shared hosting deployments suffer from two major problems:
1. **Downtime and Inconsistent State**: Uploading files file-by-file directly into the active web root (`public_html` or `httpdocs`) can leave the application in a broken state for minutes while dependencies or vendor classes upload.
2. **Database Migration Impasse**: Shared hosting plans rarely provide interactive SSH access, making `phinx migrate` or `php artisan migrate` difficult or insecure to run.

`php-api-foundation` solves this with a 5-stage automated near-atomic pipeline:

```mermaid
sequenceDiagram
    autonumber
    participant CI as GitHub Actions Runner
    participant Server as Remote Shared Host
    participant App as Active PHP Web Endpoint

    CI->>CI: 1. Build optimized vendor (--no-dev --optimize-autoloader)
    CI->>Server: 2. Upload to timestamped directory: public_html_new_{timestamp}
    CI->>Server: 3. Copy persistent assets (.env, var/data, uploads)
    CI->>Server: 4. Directory swap (rename current -> old, rename new -> current)
    CI->>App: 5. POST /_deploy_migrate.php (X-Deploy-Signature: HMAC-SHA256)
    App->>App: Validate timestamp & HMAC; run Phinx migrations; unlink self
    App-->>CI: 200 OK { "success": true }
    CI->>App: 6. GET /v1/health/ready (Smoke test)
    alt Smoke Test Fails
        CI->>Server: Emergency Rollback (rename old -> current)
    else Smoke Test Passes
        CI->>CI: Mark deployment SUCCESS
    end
```

---

## 2. Directory Structure on Remote Host

```
/home/username/
├── .env                          <-- Master persistent environment file
├── public_html/                  <-- Currently active release (or symlink)
│   ├── index.php
│   ├── .htaccess
│   └── robots.txt
├── api/                          <-- Application root
│   ├── config/
│   ├── db/
│   ├── public/
│   ├── src/
│   ├── var/
│   │   ├── data/                 <-- Persistent SQLite database
│   │   └── logs/                 <-- Application logs
│   └── vendor/
└── api_old_20261025120000/       <-- Previous release for instant rollback
```

---

## 3. GitHub Actions Configuration

Set the following GitHub Actions Secrets in your repository settings:

| Secret Name | Example Value | Description |
|:---|:---|:---|
| `FTP_SERVER` | `ftp.example.com` | Hostname or IP of the FTP/SFTP server |
| `FTP_PORT` | `21` (FTP) or `22` (SFTP) | Port number |
| `FTP_USERNAME` | `deployer@example.com` | FTP account username |
| `FTP_PASSWORD` | `SecureRandomPassword123!` | FTP account password |
| `FTP_REMOTE_ROOT` | `/home/user/api` | Absolute path to application root on host |
| `DEPLOY_APP_URL` | `https://api.example.com` | Public HTTPS URL of the application |
| `DEPLOY_SECRET` | `4f89d3a1e...` | High-entropy shared secret (>= 32 chars) |
| `DEPLOY_PROTOCOL` | `ftp` or `sftp` | Protocol to use in `lftp` |

---

## 4. Persistent Asset Management

The deployment script references `deploy/ftp/persist.txt` to identify files that must be preserved across release swaps:

```
.env
var/data
var/logs
public/uploads
```

---

## 5. HMAC Database Migration Runner

For shared hosts lacking SSH, `deploy/ftp/migrate.php` is uploaded to `public/_deploy_migrate.php` during release staging.

### Security Guarantees:
- **HTTP POST Only**: Rejects GET, HEAD, or other HTTP verbs.
- **Timestamp Window**: Validates `X-Deploy-Timestamp` is within +/- 300 seconds of server time.
- **HMAC-SHA256 Signature**: Requires header `X-Deploy-Signature` matching `hash_hmac('sha256', (string) $timestamp, $DEPLOY_SECRET)`.
- **Self-Destruction**: Immediately calls `unlink(__FILE__)` upon completing migration or catching an exception.
- **Deletion Verification**: The CI deployment script makes a subsequent verification request to ensure the runner returns `404 Not Found`.

---

## 6. Manual Deployment & Rollback Runbook

To test or execute the deployment manually from a local machine:

```bash
# 1. Export deployment credentials
export FTP_SERVER="ftp.example.com"
export FTP_PORT="21"
export FTP_USERNAME="deployer"
export FTP_PASSWORD="password"
export FTP_REMOTE_ROOT="/home/user/api"
export DEPLOY_APP_URL="https://api.example.com"
export DEPLOY_SECRET="your-high-entropy-secret"

# 2. Run the deployment script
bash deploy/ftp/deploy.sh
```

### Manual Emergency Rollback
If a faulty deployment must be rolled back manually via FTP client (FileZilla / WinSCP):
1. Rename `/home/user/api` to `/home/user/api_failed`.
2. Rename `/home/user/api_old_<TIMESTAMP>` to `/home/user/api`.
3. Check `https://api.example.com/v1/health/ready`.
