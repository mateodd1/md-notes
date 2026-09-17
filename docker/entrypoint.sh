#!/usr/bin/env bash
set -euo pipefail

if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
fi

exec "$@"
