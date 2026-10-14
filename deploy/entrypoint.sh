#!/bin/sh
set -eu

if [ "${APP_ENV:-}" != production ] || [ "${APP_DEBUG:-false}" != false ]; then
    echo "Production requires APP_ENV=production and APP_DEBUG=false." >&2
    exit 1
fi
if [ -z "${APP_KEY:-}" ]; then
    echo "Set a stable APP_KEY before starting the application." >&2
    exit 1
fi
case "${APP_URL:-}" in
    https://*) ;;
    *) echo "APP_URL must be the public HTTPS URL." >&2; exit 1 ;;
esac

mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
runuser -u www-data -- php artisan config:cache
if [ "${RUN_MIGRATIONS:-false}" = true ]; then
    runuser -u www-data -- php artisan migrate --force
fi
runuser -u www-data -- php artisan route:cache
runuser -u www-data -- php artisan view:cache

exec "$@"
