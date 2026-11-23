import { ref } from 'vue';
import { borrar, guardar, limpiarTodo, obtener, todos } from './db';

/**
 * Detección de conectividad y sincronización (PWA-05 / PWA-06).
 *
 * - Las instantáneas (pedidos, catálogo) se guardan en IndexedDB para verlas sin red.
 * - Las acciones hechas sin conexión se encolan y se reenvían al volver la red.
 * - Cada acción lleva un UUID que viaja en la cabecera Idempotency-Key: si el
 *   servidor ya la procesó, responde lo mismo y NO la duplica.
 * - Todo se borra al cerrar sesión y no se mezcla entre usuarios.
 */
const ETIQUETA_SYNC = 'oficiosya-sync';
const MAX_INTENTOS = 10;

export const pendientes = ref(0);
export const sincronizando = ref(false);
export const usuarioId = ref(null); // id del usuario autenticado (reactivo)
export const aviso = ref(null); // { tipo: 'ok' | 'error' | 'info', texto }

let usuarioActual; // undefined = aún no se sabe
let iniciado = false;
let temporizadorAviso = null;
const alTerminar = [];

/** Registra un callback que se ejecuta cuando se envió al menos una acción. */
export function alSincronizar(callback) {
    alTerminar.push(callback);
}

export function mostrarAviso(tipo, texto, ms = 6000) {
    aviso.value = { tipo, texto };
    clearTimeout(temporizadorAviso);
    if (ms) temporizadorAviso = setTimeout(() => (aviso.value = null), ms);
}

async function contarPendientes() {
    const cola = await todos('cola');
    pendientes.value = cola.filter((a) => a.usuarioId === usuarioActual).length;
}

// --- Usuario y limpieza --------------------------------------------------------

export async function limpiarDatosLocales() {
    await limpiarTodo();
    pendientes.value = 0;
}

/** Llamar en cada navegación con el id del usuario autenticado (o null). */
export async function setUsuario(id) {
    const nuevo = id ?? null;
    if (usuarioActual === nuevo) return;

    const anterior = usuarioActual;
    usuarioActual = nuevo;
    usuarioId.value = nuevo;

    if (nuevo === null) {
        // Sesión cerrada (o invitado): no debe quedar nada personal en el dispositivo.
        if (anterior !== null) await limpiarDatosLocales();
        return;
    }

    const meta = await obtener('meta', 'usuarioId');
    if (meta && meta.valor !== nuevo) await limpiarDatosLocales(); // otro usuario en el mismo dispositivo
    await guardar('meta', { clave: 'usuarioId', valor: nuevo });
    await contarPendientes();
}

// --- Instantáneas --------------------------------------------------------------

export async function guardarSnapshot(clave, valor) {
    if (usuarioActual == null) return;
    await guardar('cache', { clave, valor, guardadoEn: new Date().toISOString() });
}

export async function leerSnapshot(clave) {
    return (await obtener('cache', clave))?.valor ?? null;
}

// --- Conectividad --------------------------------------------------------------

/** navigator.onLine miente a veces (wifi sin internet): se confirma con /up. */
export async function hayConexion() {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) return false;

    const control = new AbortController();
    const corte = setTimeout(() => control.abort(), 4000);
    try {
        const r = await fetch('/up', { method: 'HEAD', cache: 'no-store', signal: control.signal });
        return r.ok;
    } catch {
        return false;
    } finally {
        clearTimeout(corte);
    }
}

// --- Cola ----------------------------------------------------------------------

const nuevoUuid = () =>
    typeof crypto !== 'undefined' && crypto.randomUUID
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`;

async function pedirBackgroundSync() {
    try {
        const reg = await navigator.serviceWorker?.ready;
        await reg?.sync?.register(ETIQUETA_SYNC); // solo Chromium; en el resto basta el evento "online"
    } catch {
        /* sin Background Sync: se sincroniza al volver la conexión con la app abierta */
    }
}

/**
 * Guarda una acción para enviarla después.
 * @param {{ tipo: string, url: string, metodo?: string, datos?: object, descripcion?: string }} accion
 */
export async function encolar({ tipo, url, metodo = 'post', datos = {}, descripcion = '' }) {
    if (usuarioActual == null) return false;

    await guardar('cola', {
        uuid: nuevoUuid(),
        usuarioId: usuarioActual,
        tipo,
        url,
        metodo,
        datos,
        descripcion,
        intentos: 0,
        creadoEn: new Date().toISOString(),
    });

    await contarPendientes();
    pedirBackgroundSync();
    return true;
}

/** Envía la cola en orden. Seguro de llamar varias veces (candado entre pestañas). */
export async function sincronizar() {
    if (sincronizando.value || usuarioActual == null) return;

    const correr = async () => {
        if (!(await hayConexion())) return;

        sincronizando.value = true;
        let enviadas = 0;

        try {
            const cola = (await todos('cola'))
                .filter((a) => a.usuarioId === usuarioActual)
                .sort((a, b) => a.id - b.id);

            for (const accion of cola) {
                try {
                    await window.axios.request({
                        method: accion.metodo,
                        url: accion.url,
                        data: accion.datos,
                        timeout: 15000,
                        headers: { 'Idempotency-Key': accion.uuid, Accept: 'application/json' },
                    });
                    await borrar('cola', accion.id);
                    enviadas++;
                } catch (error) {
                    const estado = error?.response?.status;

                    if (!estado) break; // sin red: se reintenta luego, conservando el orden

                    if (estado === 401 || estado === 419) {
                        mostrarAviso('error', 'Tu sesión expiró. Inicia sesión de nuevo para enviar tus acciones pendientes.', 0);
                        break;
                    }

                    if (estado >= 500 || estado === 429) {
                        accion.intentos += 1;
                        if (accion.intentos >= MAX_INTENTOS) {
                            await borrar('cola', accion.id);
                            mostrarAviso('error', `No se pudo enviar: ${accion.descripcion || accion.tipo}. Inténtalo manualmente.`);
                        } else {
                            await guardar('cola', accion);
                        }
                        break;
                    }

                    // Otros 4xx (validación, permisos, ya no existe): reintentar no sirve.
                    await borrar('cola', accion.id);
                    const motivo = error.response?.data?.message || 'El servidor la rechazó.';
                    mostrarAviso('error', `No se pudo enviar "${accion.descripcion || accion.tipo}": ${motivo}`, 10000);
                }
            }
        } finally {
            sincronizando.value = false;
            await contarPendientes();
        }

        if (enviadas > 0) {
            mostrarAviso('ok', enviadas === 1 ? 'Se envió 1 acción pendiente.' : `Se enviaron ${enviadas} acciones pendientes.`);
            alTerminar.forEach((cb) => cb());
        }
    };

    if (navigator.locks?.request) {
        await navigator.locks.request('oficiosya-sync', { ifAvailable: true }, (lock) => (lock ? correr() : undefined));
    } else {
        await correr();
    }
}

/** Una sola vez al arrancar la app. */
export function iniciarSync() {
    if (iniciado || typeof window === 'undefined') return;
    iniciado = true;

    window.addEventListener('online', () => sincronizar());
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && pendientes.value > 0) sincronizar();
    });

    // El SW despierta a la app cuando el navegador dispara Background Sync.
    navigator.serviceWorker?.addEventListener('message', (e) => {
        if (e.data?.type === 'SYNC_PENDIENTES') sincronizar();
    });

    // Reintento periódico mientras haya algo pendiente.
    setInterval(() => {
        if (pendientes.value > 0) sincronizar();
    }, 30000);
}
