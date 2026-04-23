import { usePage } from '@inertiajs/vue3'
import axios from 'axios'

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
    const rawData = atob(base64)
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)))
}

export function usePushNotifications() {
    const page = usePage()

    function isSupported() {
        return 'serviceWorker' in navigator && 'PushManager' in window
    }

    async function getPermission() {
        if (!isSupported()) return 'unsupported'
        return Notification.permission
    }

    async function subscribe() {
        if (!isSupported()) return false

        const permission = await Notification.requestPermission()
        if (permission !== 'granted') return false

        const reg = await navigator.serviceWorker.ready
        const vapidKey = page.props.vapid_public_key
        if (!vapidKey) return false

        const subscription = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidKey),
        })

        await axios.post(route('push.subscribe'), subscription.toJSON())
        return true
    }

    async function unsubscribe() {
        if (!isSupported()) return

        const reg = await navigator.serviceWorker.ready
        const sub = await reg.pushManager.getSubscription()
        if (!sub) return

        await axios.delete(route('push.unsubscribe'), { data: { endpoint: sub.endpoint } })
        await sub.unsubscribe()
    }

    async function syncSubscription() {
        if (!isSupported()) return
        if (!page.props.auth?.user) return

        const reg = await navigator.serviceWorker.ready
        const sub = await reg.pushManager.getSubscription()
        if (sub) {
            await axios.post(route('push.subscribe'), sub.toJSON()).catch(() => {})
        }
    }

    return { isSupported, getPermission, subscribe, unsubscribe, syncSubscription }
}
