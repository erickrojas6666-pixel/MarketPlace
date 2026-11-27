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

// WebKit (Safari / iPhone) exige mostrar una notificación en CADA push y revoca el permiso si no;
// Chrome y Firefox permiten no mostrarla cuando la app está a la vista.
const esWebKit = /^((?!chrome|android|crios|fxios|edg).)*safari/i.test(self.navigator.userAgent);

self.addEventListener('push', (event) => {
    let datos = {};
    try {
        datos = event.data ? event.data.json() : {};
    } catch {
        datos = {};
    }

    event.waitUntil(
        (async () => {
            const esChat = datos.tipo === 'chat' && Number.isInteger(datos.pedido_id);

            if (esChat) {
                // Mensaje nuevo: las ventanas abiertas consultan solo lo nuevo, en este momento.
                // Así el chat no necesita preguntarle al servidor cada pocos segundos.
                const ventanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
                ventanas.forEach((v) => v.postMessage({ tipo: 'chat:nuevo', pedidoId: datos.pedido_id }));

                // Si la app está a la vista ya lo está viendo: no se muestra la notificación.
                if (!esWebKit && ventanas.some((v) => v.visibilityState === 'visible' && v.focused)) return;
            }

            const conTag = typeof datos.tag === 'string';

            await self.registration.showNotification(String(datos.titulo || 'OficiosYa').slice(0, 80), {
                body: String(datos.cuerpo || 'Tienes una novedad en tu cuenta.').slice(0, 200),
                icon: '/icons/icon-192x192.png',
                badge: '/icons/icon-192x192.png',
                tag: conTag ? datos.tag : undefined,
                // Cada mensaje de chat vuelve a avisar (misma etiqueta: reemplaza, no apila). renotify exige tag.
                renotify: conTag && esChat,
                lang: 'es-MX',
                data: { url: urlSegura(datos.url) },
            });
        })(),
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

// --- Fotos --------------------------------------------------------------------
// Evidencia de pedidos y documentos de identidad (INE, comprobante): NUNCA se guardan
// en caché (un celular compartido no debe conservarlas). Va antes de la regla de imágenes.
registerRoute(
    ({ url }) =>
        url.origin === self.location.origin &&
        (url.pathname.startsWith('/evidencias/') ||
            url.pathname.startsWith('/admin/documentos/') ||
            url.pathname.startsWith('/prestador/verificacion/documentos/')),
    new NetworkOnly(),
);

// Fotos públicas (perfil y portafolio) servidas desde otro origen (Cloudflare R2): se muestran
// al instante desde caché y se refrescan en segundo plano. Las URLs firmadas se excluyen.
registerRoute(
    ({ request, url }) =>
        request.destination === 'image' &&
        url.origin !== self.location.origin &&
        /\.(webp|jpe?g|png)$/i.test(url.pathname) &&
        !url.searchParams.has('X-Amz-Signature'),
    new StaleWhileRevalidate({
        cacheName: 'oficiosya-fotos',
        plugins: [
            new CacheableResponsePlugin({ statuses: [0, 200] }), // 0 = respuesta opaca (otro origen)
            new ExpirationPlugin({ maxEntries: 120, maxAgeSeconds: 60 * 60 * 24 * 30 }),
        ],
    }),
);

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