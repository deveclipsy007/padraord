#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DIST_DIR="${ROOT_DIR}/dist/hostinger"

case "${DIST_DIR}" in
    "${ROOT_DIR}/dist/hostinger") ;;
    *) echo "Refusing unexpected release directory: ${DIST_DIR}" >&2; exit 1 ;;
esac

rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}/app_core" "${DIST_DIR}/public_html"

cd "${ROOT_DIR}"
composer install --no-interaction --prefer-dist
npm ci --no-audit --no-fund
php artisan test --compact
./vendor/bin/pint --test app bootstrap/app.php config database routes tests
npm run typecheck
npm run build

rsync -a \
    --exclude='.env' \
    --exclude='storage/' \
    --exclude='resources/js/' \
    --exclude='resources/css/' \
    --exclude='resources/images/' \
    --exclude='public/' \
    --exclude='tests/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='database/*.sqlite' \
    --exclude='*.log' \
    app bootstrap config database routes artisan composer.json composer.lock vendor resources \
    "${DIST_DIR}/app_core/"

mkdir -p \
    "${DIST_DIR}/app_core/storage/app/private" \
    "${DIST_DIR}/app_core/storage/framework/cache/data" \
    "${DIST_DIR}/app_core/storage/framework/sessions" \
    "${DIST_DIR}/app_core/storage/framework/views" \
    "${DIST_DIR}/app_core/storage/logs"

composer install \
    --working-dir="${DIST_DIR}/app_core" \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader

sed "s#dirname(__DIR__).'/public'#dirname(__DIR__).'/../public_html'#" \
    app/../bootstrap/app.php > "${DIST_DIR}/app_core/bootstrap/app.php"

sed \
    -e "s#__DIR__.'/../storage#__DIR__.'/../app_core/storage#" \
    -e "s#__DIR__.'/../vendor#__DIR__.'/../app_core/vendor#" \
    -e "s#__DIR__.'/../bootstrap#__DIR__.'/../app_core/bootstrap#" \
    public/index.php > "${DIST_DIR}/public_html/index.php"

cp public/.htaccess "${DIST_DIR}/public_html/.htaccess"
cp -R public/build "${DIST_DIR}/public_html/build"
test ! -e "${DIST_DIR}/public_html/index.html"
test ! -e "${DIST_DIR}/app_core/.env"

RELEASE_ID="$(date -u +%Y%m%d%H%M%S)"
printf '{"release":"%s","app":"padrao-rd","php":"8.4","public_entry":"public_html/index.php"}\n' "${RELEASE_ID}" > "${DIST_DIR}/release.json"
(printf '%s\n' "Previous release 20260814161958 is obsolete and must not be published." "Generated release: ${RELEASE_ID}" > "${DIST_DIR}/obsolete-files.txt")
(cd "${DIST_DIR}" && find app_core public_html -type f -print0 | sort -z | xargs -0 shasum -a 256 > checksums.sha256)

"${ROOT_DIR}/scripts/deploy/verify-hostinger-release.sh" "${DIST_DIR}"

echo "Hostinger release ready: ${DIST_DIR}"
