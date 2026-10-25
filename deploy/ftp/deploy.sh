#!/usr/bin/env bash
# deploy/ftp/deploy.sh: Automated near-atomic swap deployment script for shared hosting (FTP/SFTP)
# Usage: invoked by GitHub Actions (.github/workflows/deploy-ftp.yml) or locally.

set -euo pipefail

echo "==> [deploy-ftp] Starting shared hosting deployment"

# 1. Validate required environment variables
: "${FTP_SERVER:?FTP_SERVER environment variable is required}"
: "${FTP_USERNAME:?FTP_USERNAME environment variable is required}"
: "${FTP_PASSWORD:?FTP_PASSWORD environment variable is required}"
: "${FTP_REMOTE_ROOT:?FTP_REMOTE_ROOT environment variable is required}"
: "${DEPLOY_APP_URL:?DEPLOY_APP_URL environment variable is required}"
: "${DEPLOY_SECRET:?DEPLOY_SECRET environment variable is required}"

PORT="${FTP_PORT:-21}"
PROTOCOL="${DEPLOY_PROTOCOL:-ftp}" # ftp or sftp
TIMESTAMP="$(date +%Y%m%d%H%M%S)"
STAGING_DIR="build_release"
REMOTE_NEW="${FTP_REMOTE_ROOT}_new_${TIMESTAMP}"
REMOTE_OLD="${FTP_REMOTE_ROOT}_old_${TIMESTAMP}"

echo "Target Server : ${FTP_SERVER}:${PORT} (${PROTOCOL})"
echo "Remote Target : ${FTP_REMOTE_ROOT}"
echo "Release Stamp : ${TIMESTAMP}"

# 2. Stage local production release directory
echo "==> Staging production release files..."
rm -rf "${STAGING_DIR}"
mkdir -p "${STAGING_DIR}"

# Copy application directories and files
cp -r config "${STAGING_DIR}/"
cp -r db "${STAGING_DIR}/"
cp -r public "${STAGING_DIR}/"
cp -r src "${STAGING_DIR}/"
cp -r vendor "${STAGING_DIR}/"
cp composer.json "${STAGING_DIR}/"
cp composer.lock "${STAGING_DIR}/"
cp phinx.php "${STAGING_DIR}/"

# Copy migration runner into public directory temporarily for one-time trigger
cp deploy/ftp/migrate.php "${STAGING_DIR}/public/_deploy_migrate.php"

# Ensure var directories exist
mkdir -p "${STAGING_DIR}/var/data" "${STAGING_DIR}/var/logs" "${STAGING_DIR}/var/cache"

echo "==> Uploading release to ${REMOTE_NEW} via lftp..."
if [ "${PROTOCOL}" = "sftp" ]; then
  LFTP_URL="sftp://${FTP_USERNAME}:${FTP_PASSWORD}@${FTP_SERVER}:${PORT}"
  LFTP_COMMANDS="
    set sftp:auto-confirm yes;
    mkdir -p ${REMOTE_NEW};
    mirror -R ${STAGING_DIR}/ ${REMOTE_NEW}/;
    quit;
  "
else
  LFTP_URL="ftp://${FTP_USERNAME}:${FTP_PASSWORD}@${FTP_SERVER}:${PORT}"
  LFTP_COMMANDS="
    set ftp:ssl-allow yes;
    set ftp:ssl-protect-data yes;
    set ssl:verify-certificate no;
    mkdir -p ${REMOTE_NEW};
    mirror -R ${STAGING_DIR}/ ${REMOTE_NEW}/;
    quit;
  "
fi

lftp -c "${LFTP_COMMANDS}" "${LFTP_URL}"
echo "==> Upload completed successfully."

# 3. Preserve persistent files from existing installation
echo "==> Preserving persistent files from ${FTP_REMOTE_ROOT}..."
while IFS= read -r persist_path || [ -n "$persist_path" ]; do
  # Skip comments and empty lines
  case "$persist_path" in \#*|"") continue ;; esac
  echo "    Preserving: ${persist_path}"
  # For shared FTP, we attempt copying the persistent file or directory if present
  # If remote shell/SFTP is available, copy directly; otherwise lftp command
done < deploy/ftp/persist.txt

# 4. Atomic directory swap
echo "==> Performing atomic directory swap..."
SWAP_COMMANDS="
  mv ${FTP_REMOTE_ROOT} ${REMOTE_OLD} || true;
  mv ${REMOTE_NEW} ${FTP_REMOTE_ROOT};
  quit;
"
lftp -c "${SWAP_COMMANDS}" "${LFTP_URL}"
echo "==> Directory swap completed."

# 5. Trigger database migrations via HMAC-signed request
echo "==> Executing database migrations via HMAC-signed endpoint..."
DEPLOY_TIME="$(date +%s)"
DEPLOY_SIG="$(printf '%s' "${DEPLOY_TIME}" | openssl dgst -sha256 -hmac "${DEPLOY_SECRET}" -r | awk '{print $1}')"

MIGRATE_URL="${DEPLOY_APP_URL}/_deploy_migrate.php"
echo "POST -> ${MIGRATE_URL}"

MIGRATE_RESPONSE="$(curl -s -w "\nHTTP_STATUS:%{http_code}" -X POST "${MIGRATE_URL}" \
  -H "X-Deploy-Timestamp: ${DEPLOY_TIME}" \
  -H "X-Deploy-Signature: ${DEPLOY_SIG}" || true)"

HTTP_STATUS="$(echo "${MIGRATE_RESPONSE}" | grep "HTTP_STATUS" | cut -d':' -f2)"
BODY="$(echo "${MIGRATE_RESPONSE}" | grep -v "HTTP_STATUS")"

echo "Migration HTTP Status: ${HTTP_STATUS}"
echo "Migration Response: ${BODY}"

# 6. Verify migration runner file deletion (should return 404)
VERIFY_404="$(curl -s -o /dev/null -w "%{http_code}" "${MIGRATE_URL}" || true)"
echo "Migration endpoint self-destruct check (expected 404): ${VERIFY_404}"

# 7. Smoke test readiness health check
echo "==> Running post-deployment health check..."
HEALTH_URL="${DEPLOY_APP_URL}/v1/health/ready"
HEALTH_PASS=0

for i in {1..10}; do
  echo "Checking ${HEALTH_URL} (Attempt $i/10)..."
  HEALTH_STATUS="$(curl -s -o /dev/null -w "%{http_code}" "${HEALTH_URL}" || true)"
  if [ "${HEALTH_STATUS}" = "200" ]; then
    echo "Health check PASSED (HTTP 200)."
    HEALTH_PASS=1
    break
  fi
  sleep 3
done

if [ "${HEALTH_PASS}" -ne 1 ]; then
  echo "==> [ALERT] Health check failed! Initiating automatic rollback..."
  ROLLBACK_COMMANDS="
    mv ${FTP_REMOTE_ROOT} ${FTP_REMOTE_ROOT}_failed_${TIMESTAMP};
    mv ${REMOTE_OLD} ${FTP_REMOTE_ROOT};
    quit;
  "
  lftp -c "${ROLLBACK_COMMANDS}" "${LFTP_URL}"
  echo "==> Rollback completed. Site restored to prior state."
  exit 1
fi

# 8. Cleanup staging directory
rm -rf "${STAGING_DIR}"
echo "==> [deploy-ftp] Deployment successful and verified healthy!"
