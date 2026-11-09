<script setup>
import { computed, ref } from 'vue';
import { usePwa } from '../composables/usePwa';
import { aviso, mostrarAviso, pendientes, sincronizando, sincronizar, usuarioId } from '../offline/sync';
import { activarPush, motivoFallo, ocupado as activandoPush, permiso, pushDisponible, suscrito } from '../composables/usePush';

const { enLinea, hayActualizacion, puedeInstalar, mostrarGuiaIOS, instalar, actualizar } = usePwa();

const ocultarInstalacion = ref(false);

// Invitación a activar notificaciones (solo con sesión iniciada; el permiso se pide al hacer clic).
const CLAVE_PUSH = 'oficiosya-push-descartado';
const leerDescartado = () => {
    try {
        return localStorage.getItem(CLAVE_PUSH) === '1';
    } catch {
        return false;
    }
};
const descartoPush = ref(leerDescartado());

const mostrarPush = computed(
    () => usuarioId.value !== null && pushDisponible() && permiso.value === 'default' && !suscrito.value && !descartoPush.value,
);

const descartarPush = () => {
    descartoPush.value = true;
    try {
        localStorage.setItem(CLAVE_PUSH, '1');
    } catch {
        /* sin almacenamiento: solo se oculta en esta sesión */
    }
};

const MENSAJES_PUSH = {
    'servicio-push': 'Tu navegador no pudo conectarse al servicio de notificaciones. En Brave: Configuración → Privacidad → activa «Usar servicios de Google para mensajería push» y reinicia el navegador.',
    llave: 'La configuración de notificaciones del servidor no es válida. Avisa al administrador.',
    bloqueado: 'El navegador bloqueó las notificaciones para este sitio. Actívalas desde los permisos del sitio.',
    sesion: 'Tu sesión caducó. Recarga la página e inicia sesión de nuevo.',
    'no-admitido': 'Este navegador usa un servicio de notificaciones que aún no está admitido.',
    servidor: 'El servidor no pudo guardar la suscripción. Inténtalo más tarde.',
    'sin-sw': 'La aplicación aún no está lista para notificaciones. Recarga la página e inténtalo de nuevo.',
    desconocido: 'No pudimos activar las notificaciones. Inténtalo de nuevo.',
};

const activarNotificaciones = async () => {
    const ok = await activarPush();
    if (ok) mostrarAviso('ok', 'Listo: te avisaremos cuando cambie el estado de tus pedidos.');
    else if (permiso.value === 'denied') mostrarAviso('info', 'Las notificaciones están bloqueadas. Puedes activarlas desde los permisos del navegador.');
    else mostrarAviso('error', MENSAJES_PUSH[motivoFallo.value] || MENSAJES_PUSH.desconocido, 12000);
};
</script>

<template>
    <!-- PWA-04: aviso claro sin conexión -->
    <div
        v-if="!enLinea"
        role="status"
        aria-live="polite"
        class="fixed inset-x-0 top-0 z-[60] bg-amber-500 px-4 py-2 text-center text-sm font-medium text-white"
    >
        Sin conexión. Algunas funciones no estarán disponibles hasta que vuelvas a estar en línea.
    </div>

    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[60] flex flex-col items-center gap-2 p-4">
        <!-- PWA-05/06: acciones guardadas sin conexión -->
        <div
            v-if="pendientes > 0"
            role="status"
            aria-live="polite"
            class="pointer-events-auto flex w-full max-w-md items-center justify-between gap-3 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-lg ring-1 ring-amber-200"
        >
            <span>
                {{ pendientes === 1 ? '1 acción pendiente de enviar.' : `${pendientes} acciones pendientes de enviar.` }}
                <template v-if="!enLinea"> Se enviará al volver la conexión.</template>
            </span>
            <button
                v-if="enLinea"
                type="button"
                :disabled="sincronizando"
                class="rounded-md bg-amber-600 px-3 py-1 font-semibold text-white disabled:opacity-60"
                @click="sincronizar"
            >
                {{ sincronizando ? 'Enviando…' : 'Enviar ahora' }}
            </button>
        </div>

        <!-- Resultado de la sincronización -->
        <div
            v-if="aviso"
            :role="aviso.tipo === 'error' ? 'alert' : 'status'"
            class="pointer-events-auto w-full max-w-md rounded-lg px-4 py-3 text-sm shadow-lg"
            :class="{
                'bg-green-600 text-white': aviso.tipo === 'ok',
                'bg-red-600 text-white': aviso.tipo === 'error',
                'bg-gray-900 text-white': aviso.tipo === 'info',
            }"
        >
            {{ aviso.texto }}
        </div>

        <!-- PWA-07: activar notificaciones push -->
        <div
            v-if="mostrarPush"
            class="pointer-events-auto flex w-full max-w-md items-center justify-between gap-3 rounded-lg bg-white px-4 py-3 text-sm text-gray-800 shadow-lg ring-1 ring-gray-200"
        >
            <span>¿Quieres recibir avisos cuando cambie el estado de tus pedidos?</span>
            <span class="flex shrink-0 gap-2">
                <button type="button" class="text-gray-500" @click="descartarPush">Ahora no</button>
                <button
                    type="button"
                    :disabled="activandoPush"
                    class="rounded-md bg-[#14305D] px-3 py-1 font-semibold text-white disabled:opacity-60"
                    @click="activarNotificaciones"
                >
                    Activar
                </button>
            </span>
        </div>

        <!-- PWA-12: nueva versión -->
        <div
            v-if="hayActualizacion"
            role="alert"
            class="pointer-events-auto flex w-full max-w-md items-center justify-between gap-3 rounded-lg bg-gray-900 px-4 py-3 text-sm text-white shadow-lg"
        >
            <span>Hay una nueva versión disponible.</span>
            <button
                type="button"
                class="rounded-md bg-white px-3 py-1 font-semibold text-gray-900"
                @click="actualizar"
            >
                Actualizar
            </button>
        </div>

        <!-- PWA-08: instalar (Android/Chrome/Edge) -->
        <div
            v-if="puedeInstalar && !ocultarInstalacion"
            class="pointer-events-auto flex w-full max-w-md items-center justify-between gap-3 rounded-lg bg-white px-4 py-3 text-sm text-gray-800 shadow-lg ring-1 ring-gray-200"
        >
            <span>Instala OficiosYa en tu pantalla de inicio.</span>
            <span class="flex gap-2">
                <button type="button" class="text-gray-500" @click="ocultarInstalacion = true">Ahora no</button>
                <button type="button" class="rounded-md bg-[#14305D] px-3 py-1 font-semibold text-white" @click="instalar">
                    Instalar
                </button>
            </span>
        </div>

        <!-- PWA-08: instrucciones manuales en iOS -->
        <div
            v-if="mostrarGuiaIOS && !ocultarInstalacion"
            class="pointer-events-auto w-full max-w-md rounded-lg bg-white px-4 py-3 text-sm text-gray-800 shadow-lg ring-1 ring-gray-200"
        >
            <p>
                Para instalar: toca el botón <strong>Compartir</strong> de Safari y luego
                <strong>«Añadir a pantalla de inicio»</strong>.
            </p>
            <button type="button" class="mt-2 text-gray-500" @click="ocultarInstalacion = true">Entendido</button>
        </div>
    </div>
</template>