import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const appName = import.meta.env.VITE_APP_NAME || 'Not Alone';

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
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
        setup({ App, props, plugin }) {
            const ziggyConfig = page.props?.ziggy
                ? {
                      ...page.props.ziggy,
                      location: new URL(page.props.ziggy.location || page.url || 'http://localhost'),
                  }
                : undefined;

            const app = createSSRApp({ render: () => h(App, props) }).use(plugin);

            if (ziggyConfig) {
                app.use(ZiggyVue, ziggyConfig);
            }

            return app;
        },
    }),
);
