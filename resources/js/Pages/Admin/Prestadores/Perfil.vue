<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  perfil: Object,
})

const form = useForm({
  descripcion: props.perfil?.descripcion || '',
  ubicacion_aproximada: props.perfil?.ubicacion_aproximada || '',
})

const guardarPerfil = () => {
  form.post(route('prestador.perfil.update'), {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Perfil de Prestador" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-4xl mx-auto px-4 space-y-6">
      <h1 class="text-2xl font-bold dark:text-white">Perfil Profesional de Prestador</h1>

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
            Tu perfil está en revisión por el equipo de administración. Podrás publicar servicios tan pronto seas verificado.
          </p>
          <p v-else-if="perfil?.estado_verificacion === 'verificado' || perfil?.estado_verificacion === 'aprobado'" class="text-xs mt-1">
            ¡Tu perfil está verificado! Tus servicios son visibles para los clientes.
          </p>
          <p v-else class="text-xs mt-1">
            Tu solicitud de verificación fue rechazada. Revisa los datos ingresados o ponte en contacto con soporte.
          </p>
        </div>
      </div>

      <!-- FORMULARIO DE INFORMACIÓN DEL PRESTADOR -->
      <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
        <h2 class="text-lg font-semibold dark:text-white">Información Pública</h2>

        <form @submit.prevent="guardarPerfil" class="space-y-4">
          <div>
            <label class="block text-xs font-bold dark:text-gray-200 mb-1">Descripción / Bio Profesional</label>
            <textarea 
              v-model="form.descripcion" 
              rows="4" 
              placeholder="Describe tu experiencia, especialidades y modalidad de trabajo..." 
              class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white"
            ></textarea>
            <span v-if="form.errors.descripcion" class="text-xs text-red-500">{{ form.errors.descripcion }}</span>
          </div>

          <div>
            <label class="block text-xs font-bold dark:text-gray-200 mb-1">Ubicación / Cobertura Aproximada</label>
            <input 
              v-model="form.ubicacion_aproximada" 
              type="text" 
              placeholder="Ej: Zona Norte, Godoy Cruz, Mendoza" 
              class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white"
            />
            <span v-if="form.errors.ubicacion_aproximada" class="text-xs text-red-500">{{ form.errors.ubicacion_aproximada }}</span>
          </div>

          <div class="flex justify-end pt-2">
            <button 
              type="submit" 
              :disabled="form.processing" 
              class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow transition"
            >
              Guardar Cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>