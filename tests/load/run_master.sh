#!/usr/bin/env bash
set -e

DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$DIR/../.."

IP=$(ip -4 addr show wlp39s0f3u2 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n 1)
if [ -z "$IP" ]; then IP=$(hostname -I | awk '{print $1}'); fi
IP=$(echo "$IP" | xargs)
if [ -f "$ROOT_DIR/192.168.1.100.pem" ]; then IP="192.168.1.100"; fi

echo "========================================================"
echo "🚀 ИНИЦИАЛИЗАЦИЯ ИЗОЛИРОВАННОЙ СРЕДЫ (E2E Master Test)"
echo "========================================================"

cd "$ROOT_DIR"

# 1. Бэкап текущего .env
echo "📦 Делаем бэкап .env..."
cp .env .env.backup

# Очистка в случае прерывания (Ctrl+C) или ошибки
cleanup() {
    echo ""
    echo "🧹 Уборка: Возвращаем оригинальную базу..."
    mv .env.backup .env
    DEV_HOST=1 docker compose exec app php artisan config:clear > /dev/null
    
    echo "🗑️  Удаляем тестовую базу..."
    docker compose exec db psql -U postgres -c "DROP DATABASE IF EXISTS noalone_loadtest;" > /dev/null
    
    rm -f .env.k6
    echo "✅ Изолированная среда уничтожена. Вы вернулись в dev-режим."
}
trap cleanup EXIT

# 2. Подмена на тестовую БД
echo "🔧 Переключаемся на noalone_loadtest..."
sed -i 's/DB_DATABASE=noalone/DB_DATABASE=noalone_loadtest/g' .env
DEV_HOST=1 docker compose exec app php artisan config:clear > /dev/null

# 3. Создание чистой БД
echo "💽 Создаём базу noalone_loadtest..."
docker compose exec db psql -U postgres -c "DROP DATABASE IF EXISTS noalone_loadtest;" > /dev/null 2>&1 || true
docker compose exec db psql -U postgres -c "CREATE DATABASE noalone_loadtest;" > /dev/null

# 4. Накат миграций, сидеров и нашего k6-сидера
echo "🏗️  Выполняем migrate:fresh --seed..."
DEV_HOST=1 docker compose exec app php artisan migrate:fresh --seed > /dev/null
echo "🤖 Создаём тестовых пользователей..."
DEV_HOST=1 docker compose exec app php artisan db:seed --class=K6LoadTestSeeder > /dev/null

# Читаем переменные из .env.k6
source .env.k6

echo "========================================================"
echo "🔥 ЗАПУСК НАГРУЗКИ k6..."
echo "========================================================"
docker run --rm -i \
  -e BASE_URL=https://$IP:8443 \
  -e IDOL_ID=$K6_IDOL_ID \
  -e SERVICE_ID=$K6_SERVICE_ID \
  -e CONV_ID=$K6_CONV_ID \
  grafana/k6 run --insecure-skip-tls-verify - < "$DIR/master_stress.js"
