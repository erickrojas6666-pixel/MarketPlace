import { ref } from 'vue';

/**
 * Notificaciones push (PWA-07). Requiere el service worker, así que solo funciona
 * con la app compilada (`npm run build`) y en HTTPS o localhost.
 *
 * El permiso se pide únicamente desde un clic del usuario (activar()).
 */
const clavePublica = () => document.querySelector('meta[name="vapid-public-key"]')?.content || '';

const compatible =
    typeof window !== 'undefined' &&
    import.meta.env.PROD &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window;

export const permiso = ref(compatible ? Notification.permission : 'denied');
export const suscrito = ref(false);
export const ocupado = ref(false);
export const motivoFallo = ref(null); // 'servicio-push' | 'llave' | 'bloqueado' | 'sesion' | 'no-admitido' | 'servidor' | 'sin-sw' | 'desconocido'

/** Traduce el error técnico a una causa que se puede explicar al usuario. */
function clasificarError(error) {
    const estado = error?.response?.status;

    if (estado === 422) return 'no-admitido';
    if (estado === 401 || estado === 419) return 'sesion';
    if (estado >= 500) return 'servidor';
    if (error?.name === 'NotAllowedError') return 'bloqueado';
    if (error?.name === 'InvalidAccessError') return 'llave';
    if (error?.name === 'AbortError' || error?.name === 'NotSupportedError') return 'servicio-push';
    return 'desconocido';
}

/** Compatible con el navegador Y con llaves VAPID configuradas en el servidor. */
export const pushDisponible = () => compatible && clavePublica() !== '';

function base64AUint8(base64) {
    const relleno = '='.repeat((4 - (base64.length % 4)) % 4);
    const crudo = atob((base64 + relleno).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(crudo, (c) => c.charCodeAt(0));
}

async function suscripcionActual() {
    const registro = await navigator.serviceWorker.getRegistration('/');
    return registro ? registro.pushManager.getSubscription() : null;
}

const enviarAlServidor = (sub) => window.axios.post('/push/suscripciones', sub.toJSON());

/** Actualiza permiso/suscrito (al abrir la app o tras cambios). */
export async function refrescarEstadoPush() {
    if (!compatible) return;
    permiso.value = Notification.permission;
    suscrito.value = !!(await suscripcionActual());
}

/** Devuelve la suscripción del navegador; si no existe, la crea (requiere permiso concedido). */
async function obtenerOCrearSuscripcion(registro) {
    await navigator.serviceWorker.ready; // push exige un SW activo

    let sub = await registro.pushManager.getSubscription();
    if (!sub) {
        sub = await registro.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: base64AUint8(clavePublica()),
        });
    }

    return sub;
}

/**
 * Al iniciar sesión: si el navegador YA concedió el permiso, se asegura de que este
 * dispositivo quede suscrito y asociado a la cuenta actual (un dispositivo = una cuenta).
 *
 * Es necesario volver a suscribir aquí porque al cerrar sesión se da de baja la
 * suscripción, pero el permiso del navegador se conserva: sin esto, quien vuelve a
 * entrar nunca recibiría avisos (y el aviso de "Activar" no aparece con permiso concedido).
 */
export async function sincronizarSuscripcion() {
    if (!pushDisponible() || Notification.permission !== 'granted') return;

    try {
        const registro = await navigator.serviceWorker.getRegistration('/');
        if (!registro) return;

        const sub = await obtenerOCrearSuscripcion(registro);
        await enviarAlServidor(sub);
        suscrito.value = true;
    } catch {
        /* sin red o sin sesión: se reintenta en el próximo inicio */
    }
}

export async function activarPush() {
    if (!pushDisponible() || ocupado.value) return false;
    ocupado.value = true;
    motivoFallo.value = null;

    try {
        const resultado = await Notification.requestPermission();
        permiso.value = resultado;
        if (resultado !== 'granted') return false;

        const registro = await navigator.serviceWorker.getRegistration('/');
        if (!registro) {
            motivoFallo.value = 'sin-sw';
            return false;
        }

        const sub = await obtenerOCrearSuscripcion(registro);
        await enviarAlServidor(sub);
        suscrito.value = true;
        motivoFallo.value = null;
        return true;
    } catch (error) {
        motivoFallo.value = clasificarError(error);
        // Solo consola (para depurar); nunca se muestra el detalle técnico al usuario.
        console.warn('[push]', error?.name, error?.message, error?.response?.status ?? '');
        return false;
    } finally {
        ocupado.value = false;
    }
}

/** Da de baja este dispositivo (servidor y navegador). Nunca se queda colgada. */
export async function desuscribirPush() {
    if (!compatible) return;

    const tarea = (async () => {
        const sub = await suscripcionActual();
        if (!sub) return;

        try {
            await window.axios.delete('/push/suscripciones', { data: { endpoint: sub.endpoint } });
        } catch {
            /* si el servidor no responde igual se cancela en el navegador */
        }
        await sub.unsubscribe();
        suscrito.value = false;
    })();

    await Promise.race([tarea, new Promise((resolver) => setTimeout(resolver, 3000))]).catch(() => {});
}