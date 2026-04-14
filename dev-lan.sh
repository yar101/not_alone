#!/usr/bin/env bash
IP=$(hostname -I | awk '{print $1}')
echo "LAN: http://$IP:8000"
npx concurrently -c "#93c5fd,#c4b5fd,#fb7185,#fdba74,#86efac,#00D18F,#f0e68c" \
  "php artisan serve --host=0.0.0.0" \
  "php artisan queue:listen --tries=3" \
  "php artisan pail --timeout=0" \
  "DEV_HOST=$IP VITE_REVERB_HOST=$IP npm run dev" \
  "php artisan reverb:start --host=0.0.0.0" \
  "mailpit" \
  "php artisan schedule:work" \
  --names=server,queue,logs,vite,reverb,mailpit,schedule
