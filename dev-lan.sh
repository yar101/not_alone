#!/usr/bin/env bash
IP=$(hostname -I | awk '{print $1}')
CERT="$( cd "$(dirname "$0")" && pwd )/${IP}.pem"
KEY="$( cd "$(dirname "$0")" && pwd )/${IP}-key.pem"
echo "LAN (HTTP):  http://$IP:8000"
echo "LAN (HTTPS): https://$IP:8443"
npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#00D18F,#f0e68c,#fbbf24" \
  "php artisan serve --host=0.0.0.0" \
  "php artisan queue:listen --tries=3" \
  "php artisan pail --timeout=0" \
  "DEV_HOST=$IP VITE_REVERB_HOST=$IP VITE_REVERB_PORT=8443 VITE_REVERB_SCHEME=https npm run dev" \
  "php artisan reverb:start --host=0.0.0.0" \
  "mailpit" \
  "php artisan schedule:work" \
  "caddy run --config Caddyfile" \
  --names=server,queue,logs,vite,reverb,mailpit,schedule,https
