<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  prestadores: Object, // Objeto paginado de Laravel
  filtroEstado: { type: String, default: 'pendiente' },
})

const prestadorAProcesar = ref(null)
const accionVerificacion = ref('')

const filtrar = (estado) => {
  router.get(route('admin.prestadores.index'), { estado }, { preserveState: true, replace: true })
}

const abrirModal = (prestador, accion) => {
  prestadorAProcesar.value = prestador
  accionVerificacion.value = accion
}

const procesarVerificacion = () => {
  if (!prestadorAProcesar.value) return

  router.patch(route('admin.prestadores.updateStatus', prestadorAProcesar.value.id), {
    estado: accionVerificacion.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      prestadorAProcesar.value = null
    },
  })
}
</script>

<template>
  <Head title="Verificación de Prestadores" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-7xl mx-auto px-4 space-y-6">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 class="text-2xl font-bold dark:text-white">Verificación de Prestadores</h1>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Administra y aprueba las solicitudes de prestadores en la plataforma.</p>
        </div>
        <Link 
          :href="route('admin.dashboard')" 
          class="px-3 py-1.5 bg-gray-600 hover:bg-gray-700 text-white text-xs font-bold rounded shadow transition"
        >
          ← Volver al Dashboard
        </Link>
      </div>

      <!-- FILTROS POR ESTADO -->
      <div class="flex gap-2 border-b dark:border-gray-700 pb-3">
        <button 
          v-for="e in ['pendiente', 'verificado', 'rechazado', 'todos']" 
          :key="e"
          @click="filtrar(e)"
          class="px-3 py-1.5 rounded-full text-xs font-bold capitalize transition"
          :class="filtroEstado === e 
            ? 'bg-indigo-600 text-white' 
            : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
        >
          {{ e }}
        </button>
      </div>

      <!-- TABLA DE PRESTADORES -->
      <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
        <p v-if="!prestadores.data.length" class="p-6 text-sm text-gray-500 text-center">
          No se encontraron prestadores con el filtro '{{ filtroEstado }}'.
        </p>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
            <thead class="bg-gray-100 dark:bg-gray-700 text-xs uppercase font-bold text-gray-700 dark:text-gray-200">
              <tr>
                <th class="p-4">Usuario</th>
                <th class="p-4">Descripción / Ubicación</th>
                <th class="p-4">Estado</th>
                <th class="p-4">Registro</th>
                <th class="p-4 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y dark:divide-gray-700">
              <tr v-for="p in prestadores.data" :key="p.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                <td class="p-4">
                  <p class="font-bold text-gray-900 dark:text-white">{{ p.user?.name || p.user?.nombre || 'Prestador #' + p.id }}</p>
                  <p class="text-xs text-gray-500">{{ p.user?.email }}</p>
                </td>
                <td class="p-4 max-w-xs">
                  <p class="text-xs truncate">{{ p.descripcion || 'Sin descripción' }}</p>
                  <p class="text-xs text-gray-400 mt-0.5">{{ p.ubicacion_aproximada || 'Ubicación no especificada' }}</p>
                </td>
                <td class="p-4">
                  <span 
                    class="px-2.5 py-1 rounded-full text-xs font-bold capitalize"
                    :class="{
                      'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': p.estado_verificacion === 'verificado' || p.estado_verificacion === 'aprobado',
                      'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300': p.estado_verificacion === 'pendiente',
                      'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': p.estado_verificacion === 'rechazado'
                    }"
                  >
                    {{ p.estado_verificacion }}
                  </span>
                </td>
                <td class="p-4 text-xs font-mono text-gray-500">
                  {{ new Date(p.created_at).toLocaleDateString() }}
                </td>
                <td class="p-4 text-right space-x-2">
                  <button 
                    v-if="p.estado_verificacion !== 'verificado'"
                    @click="abrirModal(p, 'aprobado')" 
                    class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded shadow transition"
                  >
                    Aprobar
                  </button>
                  <button 
                    v-if="p.estado_verificacion !== 'rechazado'"
                    @click="abrirModal(p, 'rechazado')" 
                    class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded shadow transition"
                  >
                    Rechazar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PAGINACIÓN -->
        <div v-if="prestadores.links.length > 3" class="p-4 border-t dark:border-gray-700 flex justify-end gap-1">
          <Link
            v-for="(link, key) in prestadores.links"
            :key="key"
            :href="link.url || '#'"
            v-html="link.label"
            class="px-3 py-1 rounded text-xs font-bold transition"
            :class="link.active 
              ? 'bg-indigo-600 text-white' 
              : link.url ? 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200' : 'text-gray-400 cursor-not-allowed'"
          />
        </div>
      </div>

      <!-- MODAL DE CONFIRMACIÓN -->
      <div v-if="prestadorAProcesar" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6 space-y-4 shadow-xl">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white capitalize">
            Confirmar {{ accionVerificacion }}
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-300">
            ¿Confirmas cambiar el estado de verificación de <strong>{{ prestadorAProcesar.user?.name || prestadorAProcesar.user?.nombre }}</strong> a <strong>{{ accionVerificacion }}</strong>?
          </p>
          <div class="flex justify-end gap-2 pt-2">
            <button 
              type="button" 
              @click="prestadorAProcesar = null"
              class="px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold rounded"
            >
              Cancelar
            </button>
            <button 
              type="button" 
              @click="procesarVerificacion"
              class="px-4 py-1.5 text-white text-xs font-bold rounded shadow transition"
              :class="accionVerificacion === 'aprobado' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
            >
              Confirmar
            </button>
          </div>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>