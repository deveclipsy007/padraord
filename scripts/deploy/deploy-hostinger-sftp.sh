#!/usr/bin/env bash
set -euo pipefail

: "${DEPLOY_HOST:?Set DEPLOY_HOST}"
: "${DEPLOY_USER:?Set DEPLOY_USER}"
: "${DEPLOY_REMOTE_ROOT:?Set DEPLOY_REMOTE_ROOT, e.g. /home/u123/domains/app.padraord.com.br}"
: "${DEPLOY_SSH_KEY:?Set DEPLOY_SSH_KEY for SFTP/SSH}"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist/hostinger"
REMOTE_ROOT="${DEPLOY_REMOTE_ROOT%/}"
PHP_BINARY="${DEPLOY_PHP_BINARY:-/opt/alt/php84/usr/bin/php}"
SSH_PORT="${DEPLOY_PORT:-${DEPLOY_SSH_PORT:-22}}"

case "${REMOTE_ROOT}" in
    /home/*/domains/*) ;;
    *) echo "Refusing unexpected remote root: ${REMOTE_ROOT}" >&2; exit 1 ;;
esac
case "${REMOTE_ROOT}" in
    *..*|*[!a-zA-Z0-9_./-]*) echo "Refusing unsafe remote root: ${REMOTE_ROOT}" >&2; exit 1 ;;
esac
test -f "${DEPLOY_SSH_KEY}"

test -f "${DIST_DIR}/release.json"
test -f "${DIST_DIR}/public_html/index.php"
test ! -e "${DIST_DIR}/public_html/index.html"
test ! -e "${DIST_DIR}/app_core/.env"

SSH=(ssh -i "${DEPLOY_SSH_KEY}" -p "${SSH_PORT}" -o BatchMode=yes "${DEPLOY_USER}@${DEPLOY_HOST}")
SFTP=(sftp -i "${DEPLOY_SSH_KEY}" -P "${SSH_PORT}" -o BatchMode=yes)

"${SSH[@]}" "cd '${REMOTE_ROOT}/app_core' && '${PHP_BINARY}' artisan down --render='errors::503' --retry=60 || true"

"${SFTP[@]}" "${DEPLOY_USER}@${DEPLOY_HOST}" <<EOF
cd ${REMOTE_ROOT}
put -r ${DIST_DIR}/app_core app_core
put -r ${DIST_DIR}/public_html/build public_html
put ${DIST_DIR}/public_html/.htaccess public_html/.htaccess
put ${DIST_DIR}/public_html/index.php public_html/index.php
put ${DIST_DIR}/release.json release.json
EOF

"${SSH[@]}" "cd '${REMOTE_ROOT}/app_core' && '${PHP_BINARY}' artisan migrate --force && '${PHP_BINARY}' artisan optimize && '${PHP_BINARY}' artisan up"

echo "Deployed ${DIST_DIR}/release.json to ${DEPLOY_HOST}:${REMOTE_ROOT}"
