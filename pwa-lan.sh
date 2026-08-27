#!/usr/bin/env bash
set -e

if [ ! -f .env ] && [ -f .env.example ]; then
    echo "📄 .env file not found. Creating from .env.example..."
    cp .env.example .env
fi

DIR="$(cd "$(dirname "$0")" && pwd)"
IP=$(ip -4 addr show scope global 2>/dev/null | grep -vE '(docker|br-|veth|amn|wg|tun|tap)' | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n 1)
if [ -z "$IP" ]; then
    IP=$(ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '^127\.' | grep -v '^172\.' | head -n 1)
fi

echo "Building assets..."
VITE_REVERB_HOST=$IP VITE_REVERB_PORT=8443 VITE_REVERB_SCHEME=https npm run build

echo ""
echo "PWA LAN (HTTPS): https://$IP:8443"
echo ""

npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#00D18F,#fbbf24" \
  "php-fpm --nodaemonize -y $DIR/php-fpm.conf 2>&1" \
  "php artisan queue:listen --tries=3" \
  "php artisan pail --timeout=0" \
  "php artisan reverb:start --host=0.0.0.0" \
  "mailpit" \
  "php artisan schedule:work" \
  "nginx -e /tmp/no-alone-nginx-error.log -c $DIR/nginx.conf -g 'daemon off;'" \
  --names=fpm,queue,logs,reverb,mailpit,schedule,nginx
