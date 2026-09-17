#!/usr/bin/env bash
set -euo pipefail

if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
fi

# The application must be able to read its configuration after a cache clear,
# while the file remains inaccessible to users outside the application group.
if [ -f /var/www/html/.env ]; then
    chgrp www-data /var/www/html/.env
    chmod 640 /var/www/html/.env
fi

exec "$@"
