import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers'
import { VitePWA } from 'vite-plugin-pwa'
import fs from 'fs'

const devHost = process.env.DEV_HOST;
const httpsConfig = devHost && fs.existsSync(`./${devHost}.pem`) ? {
    cert: fs.readFileSync(`./${devHost}.pem`),
    key: fs.readFileSync(`./${devHost}-key.pem`),
} : undefined;

export default defineConfig({
    server: {
        host: '0.0.0.0',
        cors: { origin: true },
        https: httpsConfig,
        ...(devHost ? {
            origin: `${httpsConfig ? 'https' : 'http'}://${devHost}:5173`,
            hmr: { host: devHost },
        } : {}),
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        AutoImport({
            resolvers: [ElementPlusResolver()],
        }),
        Components({
            resolvers: [ElementPlusResolver()],
        }),
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.js',
            registerType: 'autoUpdate',
            injectRegister: null,
            injectManifest: {
                globPatterns: ['**/*.{js,css,woff2,ico,png,svg}'],
                additionalManifestEntries: [
                    { url: '/offline.html', revision: null },
                ],
            },
            manifest: {
                name: 'Not Alone',
                short_name: 'Not Alone',
                description: 'Найди своего айдола',
                theme_color: '#0e0e1a',
                background_color: '#0e0e1a',
                display: 'standalone',
                orientation: 'portrait',
                scope: '/',
                start_url: '/',
                icons: [
                    { src: '/pwa-64x64-v3.png', sizes: '64x64', type: 'image/png' },
                    { src: '/pwa-192x192-v3.png', sizes: '192x192', type: 'image/png' },
                    { src: '/pwa-512x512-v3.png', sizes: '512x512', type: 'image/png' },
                    { src: '/maskable-icon-512x512-v3.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
        }),
    ],
});
