<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    // Muestra un botón "Usar esta ubicación" en cada resultado y emite `seleccionar`.
    seleccionable: { type: Boolean, default: false },
    // Sin tarjeta propia (para usarlo dentro de otra tarjeta o formulario).
    embebido: { type: Boolean, default: false },
    titulo: { type: String, default: '🔍 Buscar Cobertura o Ubicación' },
});

const emit = defineEmits(['seleccionar']);

const busqueda = ref('');
const resultados = ref([]);
const cargando = ref(false);
const error = ref(null);
const yaBuscoAlgo = ref(false);

const contenedor = computed(() =>
    props.embebido ? '' : 'p-6 bg-white dark:bg-gray-800 rounded-lg shadow mb-6'
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
</script>

<template>
    <div :class="contenedor">
        <h3 class="font-bold text-lg dark:text-white mb-1">{{ titulo }}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Consulta ciudades o zonas reales mediante la API de OpenStreetMap.
        </p>

        <div class="flex gap-2">
            <input
                v-model="busqueda"
                @keydown.enter.prevent="buscarDireccion"
                type="text"
                placeholder="Escribe una ciudad o dirección (ej: Mendoza, Buenos Aires)..."
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white dark:border-gray-600"
            />
            <!-- type="button": si el componente vive dentro de un <form> no debe enviarlo -->
            <button
                type="button"
                @click="buscarDireccion"
                :disabled="cargando"
                class="px-4 py-2 bg-indigo-600 text-white font-semibold rounded-md hover:bg-indigo-700 disabled:opacity-50 transition"
            >
                {{ cargando ? 'Buscando...' : 'Buscar' }}
            </button>
        </div>

        <!-- Estados visuales requeridos por el profe -->
        <div v-if="cargando" class="mt-3 text-sm text-indigo-500 font-medium">
            Consultando API externa...
        </div>

        <div v-if="error" class="mt-3 text-sm text-red-500 font-medium">
            {{ error }}
        </div>

        <div v-if="!cargando && yaBuscoAlgo && resultados.length === 0 && !error" class="mt-3 text-sm text-gray-500">
            No se encontraron coincidencias para esa dirección.
        </div>

        <ul v-if="resultados.length > 0" class="mt-4 divide-y divide-gray-200 dark:divide-gray-700 border-t dark:border-gray-700 pt-2">
            <li
                v-for="lugar in resultados"
                :key="lugar.place_id"
                class="py-2 text-sm dark:text-gray-200 flex items-start justify-between gap-3"
            >
                <div>
                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ lugar.display_name }}</p>
                    <p class="text-xs text-gray-400">Coordenadas: {{ lugar.lat }}, {{ lugar.lon }}</p>
                </div>
                <button
                    v-if="seleccionable"
                    type="button"
                    @click="elegir(lugar)"
                    class="shrink-0 px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded transition"
                >
                    Usar esta ubicación
                </button>
            </li>
        </ul>
    </div>
</template>
