<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  metrics: Object,
  prestadoresPendientesLista: { type: Array, default: () => [] },
  ultimosPedidos: { type: Array, default: () => [] },
})

const prestadorAProcesar = ref(null)
const accionVerificacion = ref('')

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
  <Head title="Admin Dashboard" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-7xl mx-auto px-4 space-y-8">
      <!-- CABECERA CON BOTONES DE ACCIÓN RÁPIDA -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <h1 class="text-2xl font-bold dark:text-white">Panel de Administración</h1>
        
        <div class="flex flex-wrap gap-2">
          <Link 
            :href="route('admin.usuarios.index')" 
            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold rounded shadow transition flex items-center gap-1"
          >
            👥 Gestionar Usuarios & Roles
          </Link>
          <Link 
            :href="route('admin.categorias.index')" 
            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded shadow transition flex items-center gap-1"
          >
            🏷️ Categorías
          </Link>
          <Link 
            :href="route('admin.prestadores.index')" 
            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow transition flex items-center gap-1"
          >
            🔍 Gestionar Prestadores →
          </Link>
        </div>
      </div>

      <!-- MÉTRICAS PRINCIPALES -->
      <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow text-center">
          <p class="text-xs text-gray-500 uppercase font-bold">Usuarios</p>
          <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ metrics?.totalUsuarios || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow text-center">
          <p class="text-xs text-gray-500 uppercase font-bold">Prestadores</p>
          <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ metrics?.totalPrestadores || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow text-center border-l-4 border-yellow-500">
          <p class="text-xs text-yellow-600 dark:text-yellow-400 uppercase font-bold">Pendientes</p>
          <p class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1">{{ metrics?.prestadoresPendientes || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow text-center">
          <p class="text-xs text-gray-500 uppercase font-bold">Servicios</p>
          <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ metrics?.totalServicios || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow text-center">
          <p class="text-xs text-gray-500 uppercase font-bold">Pedidos</p>
          <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ metrics?.totalPedidos || 0 }}</p>
        </div>
        <div class="bg-white dark:bg-gray-800 p-4 rounded-lg shadow text-center">
          <p class="text-xs text-gray-500 uppercase font-bold">Categorías</p>
          <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">{{ metrics?.totalCategorias || 0 }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- SOLICITUDES PENDIENTES DE VERIFICACIÓN -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
          <h2 class="font-semibold text-lg dark:text-white">Prestadores Pendientes</h2>
          <p v-if="!prestadoresPendientesLista.length" class="text-sm text-gray-500">No hay solicitudes pendientes.</p>
          <div v-else class="space-y-3">
            <div 
              v-for="p in prestadoresPendientesLista" 
              :key="p.id" 
              class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg flex justify-between items-center"
            >
              <div>
                <p class="font-bold text-sm text-gray-900 dark:text-white">{{ p.user?.name || p.user?.nombre || 'Prestador #' + p.id }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-300">{{ p.descripcion || 'Sin descripción' }}</p>
              </div>
              <div class="flex gap-2">
                <button 
                  @click="abrirModal(p, 'aprobado')" 
                  class="px-2.5 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded shadow transition"
                >
                  Aprobar
                </button>
                <button 
                  @click="abrirModal(p, 'rechazado')" 
                  class="px-2.5 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded shadow transition"
                >
                  Rechazar
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- ÚLTIMOS PEDIDOS -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
          <h2 class="font-semibold text-lg dark:text-white">Últimos Pedidos del Sistema</h2>
          <p v-if="!ultimosPedidos.length" class="text-sm text-gray-500">No hay pedidos registrados.</p>
          <div v-else class="space-y-3">
            <div 
              v-for="ped in ultimosPedidos" 
              :key="ped.id" 
              class="p-3 bg-gray-50 dark:bg-gray-700 rounded-lg flex justify-between items-center"
            >
              <div>
                <p class="font-bold text-sm text-gray-900 dark:text-white">Pedido #{{ ped.id }} - {{ ped.servicio?.titulo || ped.servicio?.nombre || 'Servicio' }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-300">Cliente: {{ ped.cliente?.name || ped.cliente?.nombre || 'Cliente' }}</p>
              </div>
              <span class="px-2 py-0.5 rounded text-xs font-bold uppercase bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200">
                {{ ped.estado }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL PARA APROBAR / RECHAZAR -->
      <div v-if="prestadorAProcesar" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6 space-y-4 shadow-xl">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white capitalize">
            Confirmar {{ accionVerificacion }}
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-300">
            ¿Confirmas cambiar el estado del prestador <strong>{{ prestadorAProcesar.user?.name || prestadorAProcesar.user?.nombre }}</strong> a <strong>{{ accionVerificacion }}</strong>?
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