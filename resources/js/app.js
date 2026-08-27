import '../css/app.css';

if ('serviceWorker' in navigator) {
    if (import.meta.env.PROD) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/build/sw.js', { scope: '/' }).catch((err) => {
                console.warn('ServiceWorker registration skipped or failed:', err.message || err);
            });
        });
    } else {
        // Unregister service workers in development to prevent stale caching and Vite HMR conflicts
        navigator.serviceWorker.getRegistrations().then((registrations) => {
            for (const registration of registrations) {
                registration.unregister();
            }
        });
    }
}
import 'element-plus/theme-chalk/dark/css-vars.css';
import 'element-plus/theme-chalk/el-notification.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Not Alone';

createInertiaApp({
    title: (title) => {
        if (!title) return appName;
        if (title.toLowerCase().includes('not alone')) {
            return title;
        }
        return `${title} - ${appName}`;
    },
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
