#!/usr/bin/env bash
set -e

DIR="$(cd "$(dirname "$0")" && pwd)"
IP=$(hostname -I | awk '{print $1}')

echo "LAN (HTTPS): https://$IP:8443"
echo "Vite HMR:    https://$IP:5173"

npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#00D18F,#fbbf24,#f87171" \
  "php-fpm --nodaemonize -y $DIR/php-fpm.conf 2>&1" \
  "php artisan queue:listen --tries=3" \
  "php artisan pail --timeout=0" \
  "DEV_HOST=$IP VITE_REVERB_HOST=$IP VITE_REVERB_PORT=8443 VITE_REVERB_SCHEME=https npm run dev" \
  "php artisan reverb:start --host=0.0.0.0" \
  "mailpit" \
  "php artisan schedule:work" \
  "nginx -e /tmp/no-alone-nginx-error.log -c $DIR/nginx.conf -g 'daemon off;'" \
  --names=fpm,queue,logs,vite,reverb,mailpit,schedule,nginx
