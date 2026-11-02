<script setup>
import { ref } from 'vue';

const busqueda = ref('');
const resultados = ref([]);
const cargando = ref(false);
const error = ref(null);

const buscarDireccion = async () => {
    if (busqueda.value.length < 3) return;
    
    cargando.value = true;
    error.value = null;
    resultados.value = [];

    try {
        const res = await fetch(`/api/buscar-ubicacion?direccion=${encodeURIComponent(busqueda.value)}`);
        
        if (!res.ok) {
            throw new Error('Error al conectar con la API');
        }

        const data = await res.json();
        resultados.value = data;
    } catch (e) {
        error.value = 'No se pudo consultar la ubicación. Intenta de nuevo.';
    } finally {
        cargando.value = false;
    }
};
</script>

<template>
    <div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow mb-6">
        <h3 class="font-bold text-lg dark:text-white mb-1">🔍 Buscar Cobertura o Ubicación</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Consulta ciudades o zonas reales mediante la API de OpenStreetMap.
        </p>
        
        <div class="flex gap-2">
            <input 
                v-model="busqueda" 
                @keyup.enter="buscarDireccion"
                type="text" 
                placeholder="Escribe una ciudad o dirección (ej: Mendoza, Buenos Aires)..." 
                class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white dark:border-gray-600"
            />
            <button 
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

        <div v-if="!cargando && resultados.length === 0 && busqueda.length >= 3 && !error" class="mt-3 text-sm text-gray-500">
            No se encontraron coincidencia para esa dirección.
        </div>

        <ul v-if="resultados.length > 0" class="mt-4 divide-y divide-gray-200 dark:divide-gray-700 border-t dark:border-gray-700 pt-2">
            <li v-for="lugar in resultados" :key="lugar.place_id" class="py-2 text-sm dark:text-gray-200">
                <p class="font-semibold text-gray-800 dark:text-gray-100">{{ lugar.display_name }}</p>
                <p class="text-xs text-gray-400">Coordenadas: {{ lugar.lat }}, {{ lugar.lon }}</p>
            </li>
        </ul>
    </div>
</template>