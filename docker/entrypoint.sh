#!/bin/sh
set -e

# o Laravel exige uma APP_KEY; uma nova a cada início basta aqui
export APP_KEY="${APP_KEY:-base64:$(head -c 32 /dev/urandom | base64)}"

touch "$DB_DATABASE"
chown www-data:www-data "$(dirname "$DB_DATABASE")" "$DB_DATABASE"
php artisan migrate --force

exec "$@"
