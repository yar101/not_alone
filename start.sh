#!/usr/bin/env bash

set -e

if [ ! -f .env ] && [ -f .env.example ]; then
    echo "📄 .env file not found. Creating from .env.example..."
    cp .env.example .env
fi

echo ""
echo "Выберите режим запуска:"
echo "  1) local  — только localhost (как composer run dev)"
echo "  2) lan    — доступен в локальной сети и по белому IP"
echo ""
read -rp "Введите 1 или 2 [по умолчанию: 1]: " choice
choice=${choice:-1}

CONCURRENTLY_CMD='npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#00D18F"'
NAMES='--names=server,queue,logs,vite,reverb,mailpit'
COMMON_SERVICES='"php artisan queue:listen --tries=1" "php artisan pail --timeout=0" "mailpit"'

if [ "$choice" = "2" ]; then
    # Определяем LAN IP (первый 192.168.x.x или берём из аргумента HOST=...)
    LAN_IP=${HOST:-$(ip addr show | grep -oP '192\.168\.\d+\.\d+' | head -1)}

    if [ -z "$LAN_IP" ]; then
        # Фолбэк: любой не-loopback, не-VPN адрес
        LAN_IP=$(ip route get 1 2>/dev/null | awk '{for(i=1;i<=NF;i++) if ($i=="src") print $(i+1)}')
    fi

    echo ""
    echo "LAN/белый IP: $LAN_IP"
    echo "Для белого IP запустите: HOST=<ваш_белый_ip> ./start.sh"
    echo ""
    echo "Доступ:"
    echo "  Приложение:  http://$LAN_IP:8000"
    echo "  WebSocket:   ws://$LAN_IP:8080"
    echo ""

    export APP_URL="http://$LAN_IP:8000"
    export VITE_REVERB_HOST="$LAN_IP"
    export DEV_HOST="$LAN_IP"

    eval $CONCURRENTLY_CMD \
        '"php artisan serve --host=0.0.0.0"' \
        '"php artisan queue:listen --tries=1"' \
        '"php artisan pail --timeout=0"' \
        '"npm run dev -- --host"' \
        '"php artisan reverb:start --host=0.0.0.0"' \
        '"mailpit"' \
        $NAMES
else
    echo ""
    echo "Запуск в локальном режиме (http://localhost:8000)"
    echo ""

    eval $CONCURRENTLY_CMD \
        '"php artisan serve"' \
        '"php artisan queue:listen --tries=1"' \
        '"php artisan pail --timeout=0"' \
        '"npm run dev"' \
        '"php artisan reverb:start"' \
        '"mailpit"' \
        $NAMES
fi
