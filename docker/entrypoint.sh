#!/usr/bin/env bash
set -euo pipefail

if [ -d /var/www/html/storage ]; then
    chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database
fi

# File sessions are not used in production, but keep this fallback private in
# case the driver is changed temporarily or encrypted legacy files remain.
if [ -d /var/www/html/storage/framework/sessions ]; then
    chmod 700 /var/www/html/storage/framework/sessions
    find /var/www/html/storage/framework/sessions -maxdepth 1 -type f -exec chmod 600 {} +
fi

# The application must be able to read its configuration after a cache clear,
# while the file remains inaccessible to users outside the application group.
if [ -f /var/www/html/.env ]; then
    chgrp www-data /var/www/html/.env
    chmod 640 /var/www/html/.env
fi

exec "$@"
