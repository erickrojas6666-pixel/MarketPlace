import { clientsClaim } from 'workbox-core';
import { cleanupOutdatedCaches, matchPrecache, precacheAndRoute } from 'workbox-precaching';
import { NavigationRoute, registerRoute } from 'workbox-routing';
import { CacheFirst, NetworkOnly, StaleWhileRevalidate } from 'workbox-strategies';
import { ExpirationPlugin } from 'workbox-expiration';
import { CacheableResponsePlugin } from 'workbox-cacheable-response';

const OFFLINE_URL = '/offline.html';

// --- PWA-02: Precache del app shell ------------------------------------------
precacheAndRoute(self.__WB_MANIFEST || []);
cleanupOutdatedCaches();
clientsClaim();

// --- PWA-12: Activación solicitada por el cliente -----------------------------
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

// --- PWA-06: Background Sync --------------------------------------------------
self.addEventListener('sync', (event) => {
    if (event.tag !== 'oficiosya-sync') return;

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientes) => clientes.forEach((c) => c.postMessage({ type: 'SYNC_PENDIENTES' }))),
    );
});

// --- PWA-07: Notificaciones Push ----------------------------------------------
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
                        /* Ventana no controlada: basta con enfocarla */
                    }
                    return;
                }
            }

            await self.clients.openWindow(destino);
        })(),
    );
});

// --- PWA-03: Cache-First para estáticos ---------------------------------------
registerRoute(
    ({ request, url }) =>
        url.origin === self.location.origin &&
        (request.destination === 'image' || request.destination === 'font') &&
        !url.pathname.startsWith('/build/'),
    new CacheFirst({
        cacheName: 'oficiosya-estaticos',
        plugins: [
            new CacheableResponsePlugin({ statuses: [200] }),
            new ExpirationPlugin({ maxEntries: 60, maxAgeSeconds: 60 * 60 * 24 * 30 }),
        ],
    }),
);

// Fuentes externas (fonts.bunny.net)
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

// --- PWA-04: Página offline de respaldo para navegaciones --------------------
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