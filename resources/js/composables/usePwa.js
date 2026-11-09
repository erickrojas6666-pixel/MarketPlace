import { computed, ref } from 'vue';

/**
 * Estado PWA compartido: conexión, nueva versión e instalación (PWA-04, 08, 12).
 * El estado vive a nivel de módulo para que lo vea cualquier componente.
 *
 * El service worker se registra con la API nativa del navegador (sin módulos
 * virtuales), y solo en producción: `public/sw.js` existe únicamente tras
 * `npm run build`.
 */
const enLinea = ref(typeof navigator === 'undefined' ? true : navigator.onLine);
const hayActualizacion = ref(false);
const eventoInstalacion = ref(null); // beforeinstallprompt (Chrome/Edge/Android)
const instalada = ref(false);

let registro = null;
let iniciado = false;

const esStandalone = () =>
    window.matchMedia?.('(display-mode: standalone)').matches || window.navigator.standalone === true;

function vigilarActualizaciones(reg) {
    const avisarSiHayControlador = () => {
        // Si no hay controlador es la primera instalación: no es una "actualización".
        if (reg.waiting && navigator.serviceWorker.controller) {
            hayActualizacion.value = true;
        }
    };

    avisarSiHayControlador();

    reg.addEventListener('updatefound', () => {
        const nuevo = reg.installing;
        nuevo?.addEventListener('statechange', () => {
            if (nuevo.state === 'installed') avisarSiHayControlador();
        });
    });
}

async function registrarServiceWorker() {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) return;

    try {
        registro = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        vigilarActualizaciones(registro);
    } catch {
        // Sin detalles al usuario: la app funciona igual, solo sin modo offline.
    }
}

/** Llamar una sola vez al arrancar la app (resources/js/app.js). */
export function iniciarPwa() {
    if (iniciado || typeof window === 'undefined') return;
    iniciado = true;

    instalada.value = esStandalone();

    window.addEventListener('online', () => (enLinea.value = true));
    window.addEventListener('offline', () => (enLinea.value = false));

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        eventoInstalacion.value = e;
    });
    window.addEventListener('appinstalled', () => {
        instalada.value = true;
        eventoInstalacion.value = null;
    });

    if (document.readyState === 'complete') {
        registrarServiceWorker();
    } else {
        window.addEventListener('load', registrarServiceWorker, { once: true });
    }
}

export function usePwa() {
    const esIOS = /iphone|ipad|ipod/i.test(navigator.userAgent);

    const puedeInstalar = computed(() => !instalada.value && eventoInstalacion.value !== null);
    // iOS no dispara beforeinstallprompt: se muestran instrucciones manuales.
    const mostrarGuiaIOS = computed(() => !instalada.value && esIOS && eventoInstalacion.value === null);

    async function instalar() {
        if (!eventoInstalacion.value) return;
        eventoInstalacion.value.prompt();
        await eventoInstalacion.value.userChoice;
        eventoInstalacion.value = null;
    }

    /** Activa el SW nuevo (mensaje SKIP_WAITING) y recarga cuando toma el control. */
    function actualizar() {
        if (!registro?.waiting) return;

        navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload(), {
            once: true,
        });
        registro.waiting.postMessage({ type: 'SKIP_WAITING' });
    }

    return {
        enLinea,
        hayActualizacion,
        puedeInstalar,
        mostrarGuiaIOS,
        instalada,
        instalar,
        actualizar,
    };
}