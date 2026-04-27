import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// В https-режиме (pwa-lan через Caddy) WebSocket идёт через тот же хост и порт,
// поэтому берём их из window.location — это работает с любого устройства в сети.
// В http-режиме (dev) используем env-переменные, т.к. Reverb на отдельном порту 8080.
const isHttps = window.location.protocol === 'https:';
const wsHost  = isHttps ? window.location.hostname : (import.meta.env.VITE_REVERB_HOST ?? '127.0.0.1');
const wsPort  = isHttps ? Number(window.location.port || 443) : Number(import.meta.env.VITE_REVERB_PORT ?? 80);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost,
    wsPort,
    wssPort:  wsPort,
    forceTLS: isHttps,
    enabledTransports: ['ws', 'wss'],
});
