import { precacheAndRoute } from 'workbox-precaching'
import { registerRoute } from 'workbox-routing'
import { NetworkFirst } from 'workbox-strategies'

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

self.addEventListener('push', (event) => {
    const data = event.data?.json() ?? {}
    event.waitUntil(
        self.registration.showNotification(data.title ?? 'NoAlone', {
            body: data.body ?? '',
            icon: data.icon ?? '/pwa-192x192.png',
            badge: '/pwa-64x64.png',
            data: { url: data.url ?? '/' },
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
