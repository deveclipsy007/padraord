#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${ROOT_DIR}"

: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:=padrao_rd_test}"
: "${DB_USERNAME:=padrao}"
: "${DB_PASSWORD:=padrao}"

export APP_ENV=testing
export DB_CONNECTION=mysql
export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD
export CACHE_STORE=array
export QUEUE_CONNECTION=sync
export MAIL_MAILER=array
export SESSION_DRIVER=array

php artisan migrate:fresh --force --no-interaction
php artisan test --configuration=phpunit.mariadb.xml --compact
