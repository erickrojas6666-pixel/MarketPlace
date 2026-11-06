<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    // Muestra un botón "Usar esta ubicación" en cada resultado y emite `seleccionar`.
    seleccionable: { type: Boolean, default: false },
    // Sin tarjeta propia (para usarlo dentro de otra tarjeta o formulario).
    embebido: { type: Boolean, default: false },
    titulo: { type: String, default: 'Buscar cobertura o ubicación' },
});

const emit = defineEmits(['seleccionar']);

const busqueda = ref('');
const resultados = ref([]);
const cargando = ref(false);
const error = ref(null);
const yaBuscoAlgo = ref(false);

const contenedor = computed(() =>
    props.embebido
        ? ''
        : 'p-6 bg-white rounded-3xl border-2 border-slate-200/90 shadow-md'
);

const buscarDireccion = async () => {
    if (busqueda.value.length < 3) return;

    cargando.value = true;
    error.value = null;
    resultados.value = [];

    try {
        const res = await fetch(`/api/buscar-ubicacion?direccion=${encodeURIComponent(busqueda.value)}`, {
            headers: { Accept: 'application/json' },
        });

        if (res.status === 429) {
            throw new Error('Demasiadas búsquedas seguidas. Espera un momento.');
        }
        if (!res.ok) {
            throw new Error('Error al conectar con la API');
        }

        resultados.value = await res.json();
        yaBuscoAlgo.value = true;
    } catch (e) {
        error.value = e.message === 'Error al conectar con la API'
            ? 'No se pudo consultar la ubicación. Intenta de nuevo.'
            : e.message;
    } finally {
        cargando.value = false;
    }
};

const elegir = (lugar) => {
    emit('seleccionar', lugar);
    resultados.value = [];
    busqueda.value = '';
    yaBuscoAlgo.value = false;
};

/* ---------- Íconos (trazos SVG) ---------- */
const iconos = {
    pin: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
    buscar: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
    alerta: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    check: 'M5 13l4 4L19 7',
};
</script>

<template>
    <div :class="contenedor">
        <!-- Encabezado -->
        <div class="flex items-center gap-3 mb-4">
            <div class="w-11 h-11 shrink-0 rounded-xl bg-niebla border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
                </svg>
            </div>
            <div class="min-w-0">
                <h3 class="font-extrabold text-lg text-principal tracking-tight">{{ titulo }}</h3>
                <p class="text-xs text-gris-medio">
                    Consulta ciudades o zonas reales mediante la API de OpenStreetMap.
                </p>
            </div>
        </div>

        <!-- Campo de búsqueda -->
        <div class="flex flex-col sm:flex-row gap-2">
            <div class="relative flex-1">
                <svg
                    class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.buscar" />
                </svg>
                <input
                    v-model="busqueda"
                    @keydown.enter.prevent="buscarDireccion"
                    type="text"
                    placeholder="Escribe una ciudad o dirección (ej: Mendoza, Buenos Aires)..."
                    class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2.5 pl-10 pr-3 placeholder:text-slate-400"
                />
            </div>

            <!-- type="button": si el componente vive dentro de un <form> no debe enviarlo -->
            <button
                type="button"
                @click="buscarDireccion"
                :disabled="cargando"
                class="shrink-0 inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-principal text-white text-sm font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95"
                :class="{ 'opacity-50 cursor-not-allowed': cargando }"
            >
                <svg
                    v-if="cargando"
                    class="w-4 h-4 animate-spin"
                    fill="none"
                    viewBox="0 0 24 24"
                >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                {{ cargando ? 'Buscando...' : 'Buscar' }}
            </button>
        </div>

        <!-- Estados visuales requeridos por el profe -->
        <div
            v-if="cargando"
            class="mt-3 text-xs font-semibold text-principal bg-niebla/60 border border-principal/15 rounded-xl px-4 py-2.5 flex items-center gap-2"
        >
            <span class="w-2 h-2 rounded-full bg-acento animate-pulse"></span>
            Consultando API externa...
        </div>

        <div
            v-if="error"
            class="mt-3 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5 flex items-center gap-2"
        >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.alerta" />
            </svg>
            {{ error }}
        </div>

        <div
            v-if="!cargando && yaBuscoAlgo && resultados.length === 0 && !error"
            class="mt-3 text-xs font-semibold text-gris-medio bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5"
        >
            No se encontraron coincidencias para esa dirección.
        </div>

        <!-- Resultados -->
        <ul
            v-if="resultados.length > 0"
            class="mt-4 space-y-2 max-h-80 overflow-y-auto pr-1"
        >
            <li
                v-for="lugar in resultados"
                :key="lugar.place_id"
                class="p-3.5 bg-niebla/40 rounded-2xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-principal/30 hover:bg-niebla/60 transition-colors duration-200"
            >
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-9 h-9 shrink-0 rounded-xl bg-white border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-principal leading-snug">{{ lugar.display_name }}</p>
                        <p class="text-xs text-gris-medio mt-0.5">Coordenadas: {{ lugar.lat }}, {{ lugar.lon }}</p>
                    </div>
                </div>

                <button
                    v-if="seleccionable"
                    type="button"
                    @click="elegir(lugar)"
                    class="shrink-0 inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-acento hover:bg-yellow-400 text-principal text-xs font-bold rounded-xl shadow-sm transition-all duration-200 active:scale-95"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
                    </svg>
                    Usar esta ubicación
                </button>
            </li>
        </ul>
    </div>
</template>