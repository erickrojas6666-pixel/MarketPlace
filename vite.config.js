import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        cors: true,
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

        // PWA-02: Service Worker con Workbox (strategy injectManifest).
        // El SW fuente es resources/js/sw.js; el build genera public/sw.js para
        // que su scope sea "/" (los assets de Vite quedan en /build/).
        VitePWA({
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw.js',
            outDir: 'public',          // el SW sale a public/sw.js (scope "/")
            base: '/',
            scope: '/',
            buildBase: '/build/',      // prefijo de los assets precacheados
            registerType: 'prompt',    // PWA-12: avisar y que el usuario decida recargar
            injectRegister: false,     // el registro se hace en resources/js/composables/usePwa.js
            manifest: false,           // se usa public/manifest.json (enlazado en app.blade.php)
            injectManifest: {
                globDirectory: 'public/build',
                globPatterns: ['**/*.{js,css,woff,woff2,svg,png,ico}'],
                // Los archivos compilados viven en public/build/, pero se sirven como /build/...
                // (sin esto el SW pedía /assets/... y recibía 404). No afecta a additionalManifestEntries.
                modifyURLPrefix: { '': '/build/' },
                additionalManifestEntries: [
                    // App shell estático fuera de /build (revision manual: súbela al cambiar el archivo)
                    { url: '/offline.html', revision: '2' },
                    { url: '/icons/icon-192x192.png', revision: '1' },
                    { url: '/icons/icon-512x512.png', revision: '1' },
                    { url: '/images/logo.jpg', revision: '1' },
                ],
            },
            devOptions: {
                enabled: false,        // el SW solo se prueba con `npm run build`
            },
        }),
    ],
});