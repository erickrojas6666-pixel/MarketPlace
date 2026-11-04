<script setup>
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  pedidosComoCliente: { type: Array, default: () => [] },
  pedidosRecibidos: { type: Array, default: () => [] },
})

const cambiarEstado = (pedidoId, nuevoEstado) => {
  router.patch(route('pedidos.updateEstado', pedidoId), {
    estado: nuevoEstado,
  }, {
    preserveScroll: true,
  })
}
</script>

<template>
  <Head title="Gestión de Pedidos" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-7xl mx-auto px-4 space-y-10">
      
      <!-- SECCIÓN 1: PEDIDOS RECIBIDOS (VISTA PRESTADOR) -->
      <div v-if="pedidosRecibidos.length" class="space-y-4">
        <h1 class="text-xl font-bold dark:text-white flex items-center gap-2">
          🛠️ Solicitudes Recibidas como Prestador
        </h1>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
              <thead class="bg-gray-100 dark:bg-gray-700 text-xs uppercase font-bold text-gray-700 dark:text-gray-200">
                <tr>
                  <th class="p-4"># Pedido</th>
                  <th class="p-4">Servicio</th>
                  <th class="p-4">Cliente</th>
                  <th class="p-4">Estado</th>
                  <th class="p-4 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y dark:divide-gray-700">
                <tr v-for="ped in pedidosRecibidos" :key="ped.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                  <td class="p-4 font-mono font-bold text-gray-900 dark:text-white">#{{ ped.id }}</td>
                  <td class="p-4">
                    <p class="font-bold text-gray-900 dark:text-white">{{ ped.servicio?.titulo }}</p>
                    <p class="text-xs text-gray-500">{{ ped.servicio?.categoria?.nombre }}</p>
                  </td>
                  <td class="p-4">
                    <p class="font-semibold">{{ ped.cliente?.name || ped.cliente?.nombre }}</p>
                    <p class="text-xs text-gray-400">{{ ped.cliente?.email }}</p>
                  </td>
                  <td class="p-4">
                    <span 
                      class="px-2.5 py-1 rounded-full text-xs font-bold uppercase"
                      :class="{
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300': ped.estado === 'pendiente',
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300': ped.estado === 'en_progreso',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': ped.estado === 'completado',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': ped.estado === 'cancelado',
                      }"
                    >
                      {{ ped.estado }}
                    </span>
                  </td>
                  <td class="p-4 text-right space-x-2">
                    <button 
                      v-if="ped.estado === 'pendiente'"
                      @click="cambiarEstado(ped.id, 'en_progreso')"
                      class="px-3 py-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded shadow transition"
                    >
                      Aceptar / En Progreso
                    </button>
                    <button 
                      v-if="ped.estado === 'en_progreso'"
                      @click="cambiarEstado(ped.id, 'completado')"
                      class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded shadow transition"
                    >
                      Marcar Completado
                    </button>
                    <button 
                      v-if="ped.estado !== 'completado' && ped.estado !== 'cancelado'"
                      @click="cambiarEstado(ped.id, 'cancelado')"
                      class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded transition"
                    >
                      Rechazar / Cancelar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- SECCIÓN 2: MIS PEDIDOS SOLICITADOS (VISTA CLIENTE) -->
      <div class="space-y-4">
        <h2 class="text-xl font-bold dark:text-white flex items-center gap-2">
          📋 Mis Pedidos Solicitados
        </h2>

        <p v-if="!pedidosComoCliente.length" class="text-sm text-gray-500 bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
          Aún no has contratado ningún servicio. Explora el catálogo público para realizar tu primer pedido.
        </p>

        <div v-else class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
              <thead class="bg-gray-100 dark:bg-gray-700 text-xs uppercase font-bold text-gray-700 dark:text-gray-200">
                <tr>
                  <th class="p-4"># Pedido</th>
                  <th class="p-4">Servicio Contratado</th>
                  <th class="p-4">Prestador</th>
                  <th class="p-4">Estado</th>
                  <th class="p-4 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y dark:divide-gray-700">
                <tr v-for="ped in pedidosComoCliente" :key="ped.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                  <td class="p-4 font-mono font-bold text-gray-900 dark:text-white">#{{ ped.id }}</td>
                  <td class="p-4 font-semibold text-gray-900 dark:text-white">{{ ped.servicio?.titulo }}</td>
                  <td class="p-4 text-xs">
                    {{ ped.servicio?.perfil_prestador?.user?.name || ped.servicio?.perfil_prestador?.user?.nombre || 'Prestador' }}
                  </td>
                  <td class="p-4">
                    <span 
                      class="px-2.5 py-1 rounded-full text-xs font-bold uppercase"
                      :class="{
                        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300': ped.estado === 'pendiente',
                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300': ped.estado === 'en_progreso',
                        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300': ped.estado === 'completado',
                        'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300': ped.estado === 'cancelado',
                      }"
                    >
                      {{ ped.estado }}
                    </span>
                  </td>
                  <td class="p-4 text-right">
                    <button 
                      v-if="ped.estado === 'pendiente'"
                      @click="cambiarEstado(ped.id, 'cancelado')"
                      class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs font-bold rounded shadow transition"
                    >
                      Cancelar Pedido
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