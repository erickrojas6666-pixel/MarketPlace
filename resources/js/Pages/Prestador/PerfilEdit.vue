<script setup>
import { ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  perfil: Object,
  categorias: { type: Array, default: () => [] },
  serviciosPropios: { type: Array, default: () => [] },
})

// Formulario del perfil
const formPerfil = useForm({
  descripcion: props.perfil?.descripcion || '',
  ubicacion_aproximada: props.perfil?.ubicacion_aproximada || '',
})

// Formulario para publicar servicio
const formServicio = useForm({
  titulo: '',
  categoria_id: '',
  descripcion: '',
  precio: '',
})

const guardarPerfil = () => {
  formPerfil.post(route('prestador.perfil.update'), { preserveScroll: true })
}

const publicarServicio = () => {
  formServicio.post(route('servicios.store'), {
    preserveScroll: true,
    onSuccess: () => formServicio.reset(),
  })
}

const cambiarEstadoServicio = (servId) => {
  router.patch(route('servicios.toggle', servId), {}, { preserveScroll: true })
}
</script>

<template>
  <Head title="Mi Perfil y Servicios" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-6xl mx-auto px-4 space-y-8">
      <h1 class="text-2xl font-bold dark:text-white">Gestión de Prestador</h1>

      <!-- ALERTA DE ESTADO DE VERIFICACIÓN -->
      <div 
        class="p-4 rounded-lg shadow text-sm font-medium flex items-center justify-between"
        :class="{
          'bg-yellow-50 text-yellow-800 border border-yellow-200 dark:bg-yellow-900/30 dark:text-yellow-300': perfil?.estado_verificacion === 'pendiente',
          'bg-green-50 text-green-800 border border-green-200 dark:bg-green-900/30 dark:text-green-300': perfil?.estado_verificacion === 'verificado' || perfil?.estado_verificacion === 'aprobado',
          'bg-red-50 text-red-800 border border-red-200 dark:bg-red-900/30 dark:text-red-300': perfil?.estado_verificacion === 'rechazado'
        }"
      >
        <div>
          <span class="font-bold uppercase text-xs block">Estado de Verificación: {{ perfil?.estado_verificacion }}</span>
          <p v-if="perfil?.estado_verificacion === 'pendiente'" class="text-xs mt-1">
            Tu perfil está en revisión. Podrás ofrecer servicios cuando el administrador lo apruebe.
          </p>
          <p v-else-if="perfil?.estado_verificacion === 'verificado' || perfil?.estado_verificacion === 'aprobado'" class="text-xs mt-1">
            ¡Tu perfil está verificado! Tus servicios están visibles en el catálogo público.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- FORMULARIO DE MI PERFIL -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
          <h2 class="text-lg font-semibold dark:text-white">Información Profesional</h2>

          <form @submit.prevent="guardarPerfil" class="space-y-4">
            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Descripción / Bio</label>
              <textarea 
                v-model="formPerfil.descripcion" 
                rows="4" 
                required
                placeholder="Describe tu experiencia..." 
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white"
              ></textarea>
            </div>

            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Ubicación Aproximada</label>
              <input 
                v-model="formPerfil.ubicacion_aproximada" 
                type="text" 
                placeholder="Ej: Godoy Cruz, Mendoza" 
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white"
              />
            </div>

            <button 
              type="submit" 
              :disabled="formPerfil.processing" 
              class="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow transition"
            >
              Guardar Datos del Perfil
            </button>
          </form>
        </div>

        <!-- FORMULARIO PARA CREAR SERVICIO -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
          <h2 class="text-lg font-semibold dark:text-white">Publicar un Servicio</h2>

          <form @submit.prevent="publicarServicio" class="space-y-3">
            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Título del Servicio</label>
              <input v-model="formServicio.titulo" type="text" required placeholder="Ej: Reparación de cañerías" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white" />
            </div>

            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Categoría</label>
              <select v-model="formServicio.categoria_id" required class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white">
                <option value="" disabled>Selecciona una categoría</option>
                <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Detalles / Alcance del Servicio</label>
              <textarea v-model="formServicio.descripcion" rows="3" required placeholder="¿Qué incluye este servicio?" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white"></textarea>
            </div>

            <button 
              type="submit" 
              :disabled="formServicio.processing" 
              class="w-full py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded shadow transition"
            >
              Publicar Servicio
            </button>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>