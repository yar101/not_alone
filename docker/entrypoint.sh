#!/bin/sh
set -e

# Функция ожидания готовности базы данных
wait_for_db() {
    if [ -n "${DB_HOST}" ] && [ "${DB_CONNECTION}" = "pgsql" ]; then
        echo "Waiting for PostgreSQL (${DB_HOST}:${DB_PORT:-5432})..."
        until php -r "
        try {
            new PDO(
                'pgsql:host='.getenv('DB_HOST').';port='.(getenv('DB_PORT') ?: '5432').';dbname='.getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD'),
                [PDO::ATTR_TIMEOUT => 2]
            );
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
        " 2>/dev/null; do
            sleep 1
        done
        echo "PostgreSQL is ready!"
    fi
}

# Только web-контейнер запускает миграции
if [ "${CONTAINER_ROLE}" = "web" ] || [ -z "${CONTAINER_ROLE}" ]; then
    chmod -R a+rwX /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
    wait_for_db
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
    wait_for_db
    if [ "${APP_ENV}" = "local" ]; then
        exec php artisan queue:work --tries=3
    else
        exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600
    fi
fi

if [ "${CONTAINER_ROLE}" = "scheduler" ]; then
    wait_for_db
    exec php artisan schedule:work
fi

if [ "${CONTAINER_ROLE}" = "reverb" ]; then
    wait_for_db
    exec php artisan reverb:start --host=0.0.0.0 --port=8080
fi

exec "$@"

