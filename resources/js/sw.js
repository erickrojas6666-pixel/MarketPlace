import { clientsClaim } from 'workbox-core';
import { cleanupOutdatedCaches, matchPrecache, precacheAndRoute } from 'workbox-precaching';
import { NavigationRoute, registerRoute } from 'workbox-routing';
import { CacheFirst, NetworkOnly, StaleWhileRevalidate } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';
import { CacheableResponsePlugin } from 'workbox-cacheable-response';

const OFFLINE_URL = '/offline.html';

// --- PWA-02: precache del app shell ------------------------------------------
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();
clientsClaim();

// --- PWA-12: el cliente decide cuándo activar la nueva versión ---------------
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// --- PWA-06: Background Sync --------------------------------------------------
// Cuando el navegador recupera la red dispara "sync". Las acciones pendientes
// viven en IndexedDB y se envían desde la página (necesita la sesión y el token
// CSRF), así que el SW solo despierta a las pestañas abiertas.
// Limitación: si la app está cerrada no hay pestaña que envíe; se sincroniza al abrirla.
self.addEventListener('sync', (event) => {
    if (event.tag !== 'oficiosya-sync') return;

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientes) => clientes.forEach((c) => c.postMessage({ type: 'SYNC_PENDIENTES' }))),
    );
});

// --- PWA-07: notificaciones push ------------------------------------------------
// El payload es JSON { titulo, cuerpo, url, tag } enviado por WebPushService (Laravel).
// El texto es corto y sin datos sensibles porque se muestra en la pantalla de bloqueo.
function urlSegura(url) {
    try {
        const destino = new URL(url, self.location.origin);
        return destino.origin === self.location.origin ? destino.pathname + destino.search : '/dashboard';
    } catch {
        return '/dashboard';
    }
}

self.addEventListener('push', (event) => {
    let datos = {};
    try {
        datos = event.data ? event.data.json() : {};
    } catch {
        datos = {};
    }

    // Los navegadores exigen mostrar siempre una notificación (userVisibleOnly).
    event.waitUntil(
        self.registration.showNotification(String(datos.titulo || 'OficiosYa').slice(0, 80), {
            body: String(datos.cuerpo || 'Tienes una novedad en tu cuenta.').slice(0, 200),
            icon: '/icons/icon-192x192.png',
            badge: '/icons/icon-192x192.png',
            tag: typeof datos.tag === 'string' ? datos.tag : undefined,
            lang: 'es-MX',
            data: { url: urlSegura(datos.url) },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const destino = urlSegura(event.notification.data && event.notification.data.url);

    event.waitUntil(
        (async () => {
            const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

            for (const ventana of ventanas) {
                if (new URL(ventana.url).origin === self.location.origin) {
                    await ventana.focus();
                    try {
                        await ventana.navigate(destino);
                    } catch {
                        /* ventana no controlada: basta con enfocarla */
                    }
                    return;
                }
            }

            await self.clients.openWindow(destino);
        })(),
    );
});

// --- PWA-03: cache-first para estáticos ---------------------------------------
registerRoute(
    ({ request, url }) =>
        url.origin === self.location.origin &&
        (request.destination === 'image' || request.destination === 'font') &&
        !url.pathname.startsWith('/build/'), // /build/ ya está en el precache
    new CacheFirst({
        cacheName: 'oficiosya-estaticos',
        plugins: [
            new CacheableResponsePlugin({ statuses: [200] }),
            new ExpirationPlugin({ maxEntries: 60, maxAgeSeconds: 60 * 60 * 24 * 30 }),
        ],
    }),
);

// Fuentes externas (fonts.bunny.net): hoja de estilos + archivos de fuente.
registerRoute(
    ({ url }) => url.origin === 'https://fonts.bunny.net',
    new StaleWhileRevalidate({
        cacheName: 'oficiosya-fuentes',
        plugins: [
            new CacheableResponsePlugin({ statuses: [0, 200] }),
            new ExpirationPlugin({ maxEntries: 20, maxAgeSeconds: 60 * 60 * 24 * 365 }),
        ],
    }),
);

// --- PWA-04: página offline de respaldo para navegaciones --------------------
// Las páginas siempre se piden a la red; solo si falla se muestra offline.html.
const soloRed = new NetworkOnly();

registerRoute(
    new NavigationRoute(async (opciones) => {
        try {
            return await soloRed.handle(opciones);
        } catch {
            return (await matchPrecache(OFFLINE_URL)) || Response.error();
        }
    }),
);
