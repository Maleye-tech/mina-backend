#!/bin/sh
set -eu

if [ "${1:-web}" = "web" ]; then
    port="${PORT:-10000}"

    sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
    sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

    php artisan config:cache
    php artisan migrate --force
    php artisan storage:link --force

    exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
fi

exec "$@"
