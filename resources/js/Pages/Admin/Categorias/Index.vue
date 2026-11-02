<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  categorias: { type: Array, default: () => [] },
})

const categoriaEditando = ref(null)

// Formulario Crear / Editar
const form = useForm({
  nombre: '',
  descripcion: '',
})

const abrirEdicion = (cat) => {
  categoriaEditando.value = cat
  form.nombre = cat.nombre
  form.descripcion = cat.descripcion || ''
}

const cancelarEdicion = () => {
  categoriaEditando.value = null
  form.reset()
}

const guardarCategoria = () => {
  if (categoriaEditando.value) {
    form.put(route('admin.categorias.update', categoriaEditando.value.id), {
      preserveScroll: true,
      onSuccess: () => cancelarEdicion(),
    })
  } else {
    form.post(route('admin.categorias.store'), {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    })
  }
}

const eliminarCategoria = (cat) => {
  if (confirm(`¿Estás seguro de eliminar la categoría "${cat.nombre}"?`)) {
    router.delete(route('admin.categorias.destroy', cat.id), {
      preserveScroll: true,
    })
  }
}
</script>

<template>
  <Head title="Gestión de Categorías" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-6xl mx-auto px-4 space-y-8">
      <div class="flex justify-between items-center">
        <h1 class="text-2xl font-bold dark:text-white">Gestión de Categorías</h1>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- FORMULARIO CREAR / EDITAR -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4 md:col-span-1 h-fit">
          <h2 class="font-semibold text-lg dark:text-white">
            {{ categoriaEditando ? 'Editar Categoría' : 'Nueva Categoría' }}
          </h2>

          <form @submit.prevent="guardarCategoria" class="space-y-4">
            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Nombre</label>
              <input 
                v-model="form.nombre" 
                type="text" 
                required 
                placeholder="Ej: Plomería y Gas" 
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white" 
              />
              <span v-if="form.errors.nombre" class="text-xs text-red-500">{{ form.errors.nombre }}</span>
            </div>

            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Descripción</label>
              <textarea 
                v-model="form.descripcion" 
                rows="3" 
                placeholder="Breve descripción..." 
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white"
              ></textarea>
              <span v-if="form.errors.descripcion" class="text-xs text-red-500">{{ form.errors.descripcion }}</span>
            </div>

            <div class="flex gap-2">
              <button 
                type="submit" 
                :disabled="form.processing" 
                class="flex-1 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow transition"
              >
                {{ categoriaEditando ? 'Actualizar' : 'Guardar' }}
              </button>
              
              <button 
                v-if="categoriaEditando" 
                type="button" 
                @click="cancelarEdicion" 
                class="px-3 py-2 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold rounded"
              >
                Cancelar
              </button>
            </div>
          </form>
        </div>

        <!-- LISTA DE CATEGORÍAS -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4 md:col-span-2">
          <h2 class="font-semibold text-lg dark:text-white">Categorías Registraras ({{ categorias.length }})</h2>

          <p v-if="!categorias.length" class="text-gray-500 text-sm">No hay categorías registradas.</p>

          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
              <thead class="bg-gray-100 dark:bg-gray-700 text-xs uppercase font-bold text-gray-700 dark:text-gray-200">
                <tr>
                  <th class="p-3">Nombre</th>
                  <th class="p-3">Descripción</th>
                  <th class="p-3 text-center">Servicios</th>
                  <th class="p-3 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y dark:divide-gray-700">
                <tr v-for="c in categorias" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                  <td class="p-3 font-semibold text-gray-900 dark:text-white">{{ c.nombre }}</td>
                  <td class="p-3 text-xs text-gray-500 dark:text-gray-400">{{ c.descripcion || 'Sin descripción' }}</td>
                  <td class="p-3 text-center font-mono text-xs">{{ c.servicios_count ?? 0 }}</td>
                  <td class="p-3 text-right space-x-2">
                    <button 
                      @click="abrirEdicion(c)" 
                      class="px-2.5 py-1 bg-yellow-500 hover:bg-yellow-600 text-white text-xs font-bold rounded shadow transition"
                    >
                      Editar
                    </button>
                    <button 
                      @click="eliminarCategoria(c)" 
                      class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded shadow transition"
                    >
                      Eliminar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>