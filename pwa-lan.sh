#!/usr/bin/env bash
set -e

IP=$(hostname -I | awk '{print $1}')
echo "Building assets..."
VITE_REVERB_HOST=$IP VITE_REVERB_PORT=8443 VITE_REVERB_SCHEME=https npm run build

echo ""
echo "PWA LAN (HTTPS): https://$IP:8443"
echo ""

npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#00D18F,#fbbf24" \
  "php artisan serve --host=0.0.0.0" \
  "php artisan queue:listen --tries=3" \
  "php artisan pail --timeout=0" \
  "php artisan reverb:start --host=0.0.0.0" \
  "mailpit" \
  "php artisan schedule:work" \
  "caddy run --config Caddyfile" \
  --names=server,queue,logs,reverb,mailpit,schedule,https
