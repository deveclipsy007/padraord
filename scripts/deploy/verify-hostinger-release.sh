#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
RELEASE_DIR="${1:-${ROOT_DIR}/dist/hostinger}"

if [[ "${RELEASE_DIR}" != /* ]]; then
    RELEASE_DIR="${ROOT_DIR}/${RELEASE_DIR#./}"
fi

case "${RELEASE_DIR}" in
    "${ROOT_DIR}/dist/hostinger") ;;
    *) echo "Refusing unexpected release directory: ${RELEASE_DIR}" >&2; exit 1 ;;
esac

APP_DIR="${RELEASE_DIR}/app_core"
PUBLIC_DIR="${RELEASE_DIR}/public_html"

test -d "${APP_DIR}"
test -d "${PUBLIC_DIR}"
test -f "${RELEASE_DIR}/release.json"
test -f "${RELEASE_DIR}/checksums.sha256"
test -f "${PUBLIC_DIR}/index.php"
test ! -e "${PUBLIC_DIR}/index.html"
test ! -e "${APP_DIR}/.env"
test -f "${PUBLIC_DIR}/build/manifest.json"
test -f "${APP_DIR}/resources/views/app.blade.php"

if find "${PUBLIC_DIR}" -type f \( \
    -name 'index.html' -o -name '.env' -o -name '*.sqlite' -o -name '*.sqlite-*' \
    -o -name '*.log' -o -name '*.map' -o -name '*.ts' -o -name '*.tsx' \
    -o -name 'composer.json' -o -name 'package.json' -o -name 'artisan' \
    \) -print -quit | grep -q .; then
    echo "Forbidden private or source file found in public_html." >&2
    exit 1
fi

if find "${PUBLIC_DIR}" -type d \( -name 'node_modules' -o -name 'tests' -o -name 'storage' \) -print -quit | grep -q .; then
    echo "Forbidden private directory found in public_html." >&2
    exit 1
fi

if find "${APP_DIR}" -type f \( -name '*.sqlite' -o -name '*.sqlite-*' -o -name '*.log' -o -name '*.js.map' -o -name '*.css.map' -o -name '*.tsx' -o -name '*.ts' \) -print -quit | grep -q .; then
    echo "Development artifact found in app_core." >&2
    exit 1
fi

(cd "${RELEASE_DIR}" && shasum -a 256 -c checksums.sha256 >/dev/null)
php "${ROOT_DIR}/scripts/deploy/verify-vite-manifest.php" "${PUBLIC_DIR}/build/manifest.json" "${PUBLIC_DIR}/build"

echo "Hostinger release verified: ${RELEASE_DIR}"
