#!/bin/sh
set -e

# Только web-контейнер запускает миграции
if [ "${CONTAINER_ROLE}" = "web" ] || [ -z "${CONTAINER_ROLE}" ]; then
    php artisan migrate --force
    php artisan db:seed --force
    if [ "${APP_ENV}" = "local" ]; then
        php artisan config:clear
        php artisan route:clear
        php artisan view:clear
    else
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
    fi
    php artisan storage:link 2>/dev/null || true
    exec supervisord -c /etc/supervisor/supervisord.conf
fi

if [ "${CONTAINER_ROLE}" = "worker" ]; then
    if [ "${APP_ENV}" = "local" ]; then
        exec php artisan queue:listen --tries=3
    else
        exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    fi
fi

if [ "${CONTAINER_ROLE}" = "scheduler" ]; then
    exec php artisan schedule:work
fi

if [ "${CONTAINER_ROLE}" = "reverb" ]; then
    exec php artisan reverb:start --host=0.0.0.0 --port=8080
fi

exec "$@"
