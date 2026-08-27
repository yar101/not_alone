import { precacheAndRoute } from 'workbox-precaching'
import { registerRoute } from 'workbox-routing'
import { NetworkFirst, StaleWhileRevalidate } from 'workbox-strategies'
import { ExpirationPlugin } from 'workbox-expiration'

precacheAndRoute(self.__WB_MANIFEST)

registerRoute(
    ({ request }) => request.mode === 'navigate',
    new NetworkFirst({
        cacheName: 'pages-cache',
        networkTimeoutSeconds: 3,
        plugins: [
            {
                handlerDidError: async () => caches.match('/offline.html'),
            },
        ],
    })
)

registerRoute(
    ({ request }) => request.destination === 'image' || /\.(?:png|jpg|jpeg|svg|webp|ico)$/i.test(request.url),
    new StaleWhileRevalidate({
        cacheName: 'images-cache',
        plugins: [
            new ExpirationPlugin({
                maxEntries: 100,
                maxAgeSeconds: 30 * 24 * 60 * 60, // 30 дней
            }),
        ],
    })
)

self.addEventListener('push', (event) => {
    const data = event.data?.json() ?? {}
    event.waitUntil(
        self.registration.showNotification(data.title ?? 'Not Alone', {
            body: data.body ?? '',
            icon: data.icon ?? '/pwa-192x192-v3.png',
            badge: '/pwa-64x64-v3.png',
            data: { url: data.data?.url ?? '/' },
        })
    )
})

self.addEventListener('notificationclick', (event) => {
    event.notification.close()
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            const url = event.notification.data?.url ?? '/'
            for (const client of list) {
                if ('focus' in client) return client.focus()
            }
            if (clients.openWindow) return clients.openWindow(url)
        })
    )
})

self.addEventListener('install', () => {
    self.skipWaiting()
})

self.addEventListener('activate', (event) => {
    event.waitUntil(clients.claim())
})
