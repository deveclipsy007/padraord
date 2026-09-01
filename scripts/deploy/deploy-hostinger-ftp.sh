#!/usr/bin/env bash
set -euo pipefail

: "${DEPLOY_HOST:?Set DEPLOY_HOST}"
: "${DEPLOY_USER:?Set DEPLOY_USER}"
: "${DEPLOY_PASSWORD:?Set DEPLOY_PASSWORD}"
: "${DEPLOY_REMOTE_ROOT:?Set DEPLOY_REMOTE_ROOT}"

command -v lftp >/dev/null || { echo "Install lftp for the FTP fallback; SFTP is recommended." >&2; exit 1; }

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist/hostinger"
REMOTE_ROOT="${DEPLOY_REMOTE_ROOT%/}"

case "${REMOTE_ROOT}" in
    /home/*/domains/*) ;;
    *) echo "Refusing unexpected remote root: ${REMOTE_ROOT}" >&2; exit 1 ;;
esac
case "${REMOTE_ROOT}" in
    *..*|*[!a-zA-Z0-9_./-]*) echo "Refusing unsafe remote root: ${REMOTE_ROOT}" >&2; exit 1 ;;
esac

test -f "${DIST_DIR}/release.json"
test ! -e "${DIST_DIR}/public_html/index.html"
test ! -e "${DIST_DIR}/app_core/.env"

lftp -u "${DEPLOY_USER},${DEPLOY_PASSWORD}" "ftp://${DEPLOY_HOST}" <<EOF
set ftp:ssl-force true
set ftp:ssl-protect-data true
set net:timeout 20
set net:max-retries 2
mirror --reverse --verbose --no-perms --exclude-glob .env --exclude-glob storage/ ${DIST_DIR}/app_core ${REMOTE_ROOT}/app_core
mirror --reverse --verbose --no-perms ${DIST_DIR}/public_html/build ${REMOTE_ROOT}/public_html/build
put ${DIST_DIR}/public_html/.htaccess -o ${REMOTE_ROOT}/public_html/.htaccess
put ${DIST_DIR}/public_html/index.php -o ${REMOTE_ROOT}/public_html/index.php
put ${DIST_DIR}/release.json -o ${REMOTE_ROOT}/release.json
quit
EOF

echo "FTP files uploaded. Run migrations and cache through SSH before opening traffic."
