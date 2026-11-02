<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import BuscadorUbicacion from '@/Components/BuscadorUbicacion.vue'

const props = defineProps({
  esPrestador: { type: Boolean, default: false },
  perfil: Object,
  servicios: { type: Array, default: () => [] },
  pedidos: { type: Array, default: () => [] },
  categorias: { type: Array, default: () => [] },
  misPedidos: { type: Array, default: () => [] },
  todosLosServicios: { type: Array, default: () => [] },
  notificaciones: { type: Array, default: () => [] },
})

const user = computed(() => usePage().props.auth?.user)
const esPrestadorUser = computed(() => props.esPrestador || user.value?.esPrestador)

const estaVerificado = computed(() => {
  const est = props.perfil?.estado_verificacion?.toLowerCase()
  return est === 'aprobado' || est === 'verificado'
})

// --- FILTROS DE BÚSQUEDA Y ESPECIALIDAD PARA CLIENTE ---
const categoriaSeleccionada = ref(null)
const busquedaTexto = ref('')

const serviciosFiltrados = computed(() => {
  return props.todosLosServicios.filter(s => {
    const coincideCategoria = !categoriaSeleccionada.value || s.categoria_id === categoriaSeleccionada.value
    
    const texto = busquedaTexto.value.toLowerCase()
    const titulo = (s.titulo || s.nombre || '').toLowerCase()
    const desc = (s.descripcion || '').toLowerCase()
    const prestador = (s.perfil_prestador?.user?.name || s.perfil_prestador?.user?.nombre || '').toLowerCase()
    
    const coincideTexto = !texto || titulo.includes(texto) || desc.includes(texto) || prestador.includes(texto)

    return coincideCategoria && coincideTexto
  })
})

const seleccionarCategoria = (catId) => {
  categoriaSeleccionada.value = categoriaSeleccionada.value === catId ? null : catId
}

// --- MODAL DE SOLICITUD DE SERVICIO ---
const servicioASolicitar = ref(null)
const formPedido = useForm({
  servicio_id: null,
  direccion_servicio: '',
  fecha_deseada: '',
})

const abrirModalSolicitar = (servicio) => {
  servicioASolicitar.value = servicio
  formPedido.servicio_id = servicio.id
  formPedido.direccion_servicio = ''
  formPedido.fecha_deseada = ''
}

const solicitarServicioSubmit = () => {
  formPedido.post(route('pedidos.store'), {
    onSuccess: () => {
      servicioASolicitar.value = null
      formPedido.reset()
    },
  })
}

// Formulario de reseñas por pedido
const calificacionForm = ref({})
const comentarioForm = ref({})

// Formulario Nuevo Servicio (Prestador)
const mostrarFormServicio = ref(false)
const formServicio = useForm({
  nombre: '',
  categoria_id: '',
  precio: '',
  descripcion: '',
})

const guardarServicio = () => {
  formServicio.post(route('servicios.store'), {
    onSuccess: () => {
      formServicio.reset()
      mostrarFormServicio.value = false
    },
  })
}

const toggleServicio = (servicioId) => {
  router.patch(route('servicios.toggle', servicioId), {}, { preserveScroll: true })
}

const cambiarEstadoPedido = (pedidoId, nuevoEstado) => {
  router.patch(route('pedidos.updateEstado', pedidoId), { estado: nuevoEstado }, { preserveScroll: true })
}

const enviarResena = (pedidoId) => {
  router.post(route('resenas.store'), {
    pedido_id: pedidoId,
    calificacion: calificacionForm.value[pedidoId] || 5,
    comentario: comentarioForm.value[pedidoId] || '',
  }, { preserveScroll: true })
}

const marcarNotificacionesLeidas = () => {
  router.post(route('notificaciones.marcarLeidas'), {}, { preserveScroll: true })
}
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-5xl mx-auto px-4 space-y-6">

      <!-- BLOQUE DE NOTIFICACIONES -->
      <div v-if="notificaciones && notificaciones.length > 0" class="space-y-3">
        <div class="flex justify-between items-center">
          <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider dark:text-gray-400">
            Notificaciones pendientes ({{ notificaciones.length }})
          </h3>
          <button 
            @click="marcarNotificacionesLeidas"
            class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-semibold transition"
          >
            ✓ Marcar todas como leídas
          </button>
        </div>

        <div 
          v-for="n in notificaciones" 
          :key="n.id" 
          class="p-4 rounded-lg shadow border-l-4 flex justify-between items-start transition-all"
          :class="{
            'bg-green-50 border-green-500 text-green-900 dark:bg-green-900/20 dark:text-green-200': n?.data?.estado === 'aprobado',
            'bg-red-50 border-red-500 text-red-900 dark:bg-red-900/20 dark:text-red-200': n?.data?.estado === 'rechazado',
            'bg-blue-50 border-blue-500 text-blue-900 dark:bg-blue-900/20 dark:text-blue-200': !n?.data?.estado || n?.data?.estado === 'pendiente'
          }"
        >
          <div>
            <p class="font-bold text-sm uppercase tracking-wide mb-1">{{ n?.data?.titulo || 'Notificación del sistema' }}</p>
            <p class="text-sm">{{ n?.data?.mensaje }}</p>
          </div>
        </div>
      </div>

      <!-- VISTA PARA EL PRESTADOR -->
      <div v-if="esPrestadorUser" class="space-y-6">
        <div class="flex items-center justify-between">
          <h1 class="text-2xl font-bold dark:text-white">Panel de prestador</h1>
          <span 
            v-if="perfil?.estado_verificacion"
            class="px-3 py-1 rounded-full text-xs font-semibold capitalize"
            :class="{
              'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': estaVerificado,
              'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': perfil.estado_verificacion === 'rechazado',
              'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200': perfil.estado_verificacion === 'pendiente'
            }"
          >
            Estado: {{ perfil.estado_verificacion }}
          </span>
        </div>

        <BuscadorUbicacion />

        <!-- Mis Servicios (Prestador) -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
          <div class="flex justify-between items-center">
            <h2 class="font-semibold text-lg dark:text-white">Mis servicios ({{ servicios.length }})</h2>
            <button 
              v-if="estaVerificado"
              @click="mostrarFormServicio = !mostrarFormServicio"
              class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow transition"
            >
              {{ mostrarFormServicio ? 'Cancelar' : '+ Publicar Servicio' }}
            </button>
          </div>

          <form v-if="mostrarFormServicio" @submit.prevent="guardarServicio" class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg space-y-3 border dark:border-gray-600">
            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Nombre del Servicio</label>
              <input v-model="formServicio.nombre" type="text" required placeholder="Ej: Reparación de fugas y cañerías" class="w-full text-sm rounded border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-600" />
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold dark:text-gray-200 mb-1">Categoría</label>
                <select v-model="formServicio.categoria_id" required class="w-full text-sm rounded border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-600">
                  <option value="" disabled>Selecciona una categoría</option>
                  <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-bold dark:text-gray-200 mb-1">Precio Estimado ($)</label>
                <input v-model="formServicio.precio" type="number" step="0.01" placeholder="0.00" class="w-full text-sm rounded border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-600" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Descripción detallada</label>
              <textarea v-model="formServicio.descripcion" rows="2" required placeholder="Describe lo que incluye..." class="w-full text-sm rounded border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-600"></textarea>
            </div>
            <div class="flex justify-end">
              <button type="submit" :disabled="formServicio.processing" class="px-4 py-2 bg-green-600 text-white text-xs font-bold rounded hover:bg-green-700 transition">Guardar y Publicar</button>
            </div>
          </form>

          <p v-if="!servicios.length" class="text-gray-500 dark:text-gray-400">Aún no has publicado servicios.</p>
          <ul v-else class="space-y-2">
            <li v-for="s in servicios" :key="s.id" class="p-3 bg-gray-50 dark:bg-gray-700 rounded border dark:border-gray-600 flex justify-between items-center">
              <div>
                <p class="font-bold text-sm text-gray-800 dark:text-white">{{ s.titulo || s.nombre }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-300">{{ s.descripcion }}</p>
                <span class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">${{ s.precio_base ?? s.precio ?? '0.00' }}</span>
              </div>
              <button @click="toggleServicio(s.id)" class="px-2.5 py-1 text-xs font-semibold rounded border transition" :class="s.activo !== false ? 'bg-green-50 text-green-700 border-green-300' : 'bg-gray-100 text-gray-600 border-gray-300'">
                {{ s.activo !== false ? 'Público (Activo)' : 'Oculto (Inactivo)' }}
              </button>
            </li>
          </ul>
        </div>

        <!-- Pedidos Recibidos (Prestador) -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
          <h2 class="font-semibold text-lg dark:text-white">Pedidos recibidos ({{ pedidos.length }})</h2>
          <p v-if="!pedidos.length" class="text-gray-500 dark:text-gray-400">No tienes pedidos todavía.</p>
          <div v-else class="space-y-3">
            <div v-for="p in pedidos" :key="p.id" class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border dark:border-gray-600 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
              <div>
                <div class="flex items-center gap-2">
                  <span class="font-bold text-gray-800 dark:text-white">Pedido #{{ p.id }}</span>
                  <span class="px-2 py-0.5 rounded text-xs font-semibold uppercase" :class="{ 'bg-yellow-100 text-yellow-800': p?.estado === 'pendiente', 'bg-blue-100 text-blue-800': p?.estado === 'aceptado', 'bg-purple-100 text-purple-800': p?.estado === 'en_progreso', 'bg-green-100 text-green-800': p?.estado === 'completado', 'bg-red-100 text-red-800': p?.estado === 'rechazado' || p?.estado === 'cancelado' }">
                    {{ p?.estado }}
                  </span>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1"><strong>Cliente:</strong> {{ p.cliente?.name || p.cliente?.nombre || p.user?.name || 'Cliente' }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-300"><strong>Servicio:</strong> {{ p.servicio?.titulo || p.servicio?.nombre || ('Servicio #' + p.servicio_id) }}</p>
                <p v-if="p.direccion_servicio" class="text-xs text-gray-500 dark:text-gray-400 mt-1"><strong>Dirección:</strong> {{ p.direccion_servicio }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button v-if="p?.estado === 'pendiente'" @click="cambiarEstadoPedido(p.id, 'aceptado')" class="px-3 py-1 bg-green-600 text-white text-xs font-semibold rounded hover:bg-green-700 transition">Aceptar</button>
                <button v-if="p?.estado === 'pendiente'" @click="cambiarEstadoPedido(p.id, 'rechazado')" class="px-3 py-1 bg-red-600 text-white text-xs font-semibold rounded hover:bg-red-700 transition">Rechazar</button>
                <button v-if="p?.estado === 'aceptado'" @click="cambiarEstadoPedido(p.id, 'en_progreso')" class="px-3 py-1 bg-purple-600 text-white text-xs font-semibold rounded hover:bg-purple-700 transition">Iniciar Trabajo</button>
                <button v-if="p?.estado === 'en_progreso'" @click="cambiarEstadoPedido(p.id, 'completado')" class="px-3 py-1 bg-blue-600 text-white text-xs font-semibold rounded hover:bg-blue-700 transition">Finalizar Trabajo</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- VISTA PARA EL CLIENTE -->
      <div v-else class="space-y-6">
        <h1 class="text-2xl font-bold dark:text-white">Bienvenido</h1>

        <BuscadorUbicacion />

        <!-- FILTROS Y MARKETPLACE DE SERVICIOS -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
          <h2 class="font-semibold text-lg dark:text-white">Explorar Servicios por Especialidad</h2>
          
          <div>
            <input 
              v-model="busquedaTexto" 
              type="text" 
              placeholder="🔍 Buscar por especialidad, servicio o prestador..." 
              class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-700 dark:text-white dark:border-gray-600"
            />
          </div>

          <div class="space-y-2">
            <span class="text-xs font-bold text-gray-500 uppercase tracking-wide dark:text-gray-400">Categorías:</span>
            <div class="flex flex-wrap gap-2">
              <button 
                @click="categoriaSeleccionada = null"
                class="px-3 py-1 rounded-full text-sm font-medium transition"
                :class="!categoriaSeleccionada ? 'bg-indigo-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'"
              >
                Todas
              </button>
              <button 
                v-for="c in categorias" 
                :key="c.id" 
                @click="seleccionarCategoria(c.id)"
                class="px-3 py-1 rounded-full text-sm font-medium transition"
                :class="categoriaSeleccionada === c.id ? 'bg-indigo-600 text-white shadow' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'"
              >
                {{ c.nombre }}
              </button>
            </div>
          </div>

          <!-- LISTA DE SERVICIOS PÚBLICOS -->
          <div class="pt-4">
            <p v-if="!serviciosFiltrados.length" class="text-gray-500 dark:text-gray-400 italic">
              No se encontraron servicios disponibles para esta especialidad o búsqueda.
            </p>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div 
                v-for="s in serviciosFiltrados" 
                :key="s.id" 
                class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border dark:border-gray-600 flex flex-col justify-between space-y-3"
              >
                <div>
                  <div class="flex justify-between items-start">
                    <h3 class="font-bold text-gray-900 dark:text-white text-base">
                      {{ s.titulo || s.nombre }}
                    </h3>
                    <span class="px-2 py-0.5 bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 text-xs font-semibold rounded">
                      {{ s.categoria?.nombre || 'General' }}
                    </span>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Ofrecido por: <strong>{{ s.perfil_prestador?.user?.name || s.perfil_prestador?.user?.nombre || 'Prestador Verificado' }}</strong>
                  </p>
                  <p class="text-sm text-gray-600 dark:text-gray-300 mt-2 line-clamp-2">
                    {{ s.descripcion }}
                  </p>
                </div>

                <div class="flex justify-between items-center pt-2 border-t dark:border-gray-600">
                  <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400">
                    ${{ s.precio_base ?? s.precio ?? '0.00' }}
                  </span>
                  <button 
                    @click="abrirModalSolicitar(s)"
                    class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded shadow transition"
                  >
                    Solicitar Servicio
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- MIS PEDIDOS (CLIENTE) -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4">
          <h2 class="font-semibold text-lg dark:text-white">Mis pedidos ({{ misPedidos.length }})</h2>
          <p v-if="!misPedidos.length" class="text-gray-500 dark:text-gray-400">Aún no has solicitado servicios.</p>
          <div v-else class="space-y-3">
            <div v-for="p in misPedidos" :key="p.id" class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border dark:border-gray-600 space-y-3">
              <div class="flex justify-between items-center">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-bold text-gray-800 dark:text-white">{{ p.servicio?.titulo || p.servicio?.nombre || ('Pedido #' + p.id) }}</span>
                    <span class="px-2 py-0.5 rounded text-xs font-semibold uppercase" :class="{ 'bg-yellow-100 text-yellow-800': p?.estado === 'pendiente', 'bg-blue-100 text-blue-800': p?.estado === 'aceptado', 'bg-purple-100 text-purple-800': p?.estado === 'en_progreso', 'bg-green-100 text-green-800': p?.estado === 'completado', 'bg-red-100 text-red-800': p?.estado === 'rechazado' || p?.estado === 'cancelado' }">
                      {{ p?.estado }}
                    </span>
                  </div>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" v-if="p.created_at">
                    Solicitado el: {{ new Date(p.created_at).toLocaleDateString() }}
                  </p>
                </div>
                <button v-if="p?.estado === 'pendiente'" @click="cambiarEstadoPedido(p.id, 'cancelado')" class="px-3 py-1 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-semibold rounded hover:bg-gray-400 transition">Cancelar</button>
              </div>

              <!-- RESEÑAS PARA EL CLIENTE -->
              <div v-if="p?.estado === 'completado'" class="pt-3 border-t dark:border-gray-600">
                <div v-if="p.resena || (p.resenas && p.resenas.length > 0)" class="text-xs text-gray-600 dark:text-gray-300">
                  <span class="font-bold">Tu reseña:</span> 
                  <span class="text-yellow-500 font-bold ml-1">{{ '★'.repeat((p.resena?.calificacion || p.resenas?.[0]?.calificacion) || 5) }}</span>
                  <p v-if="p.resena?.comentario || p.resenas?.[0]?.comentario" class="italic mt-1">
                    "{{ p.resena?.comentario || p.resenas?.[0]?.comentario }}"
                  </p>
                </div>
                <form v-else @submit.prevent="enviarResena(p.id)" class="space-y-2">
                  <div class="flex items-center gap-2">
                    <label class="text-xs font-bold dark:text-gray-200">Calificar servicio:</label>
                    <select v-model="calificacionForm[p.id]" class="text-xs rounded border-gray-300 dark:bg-gray-800 dark:text-white">
                      <option :value="5">★★★★★ (5)</option>
                      <option :value="4">★★★★☆ (4)</option>
                      <option :value="3">★★★☆☆ (3)</option>
                      <option :value="2">★★☆☆☆ (2)</option>
                      <option :value="1">★☆☆☆☆ (1)</option>
                    </select>
                  </div>
                  <input v-model="comentarioForm[p.id]" type="text" placeholder="Escribe una opinión sobre el trabajo realizado (opcional)..." class="w-full text-xs rounded border-gray-300 dark:bg-gray-800 dark:text-white" />
                  <button type="submit" class="px-3 py-1 bg-indigo-600 text-white text-xs font-semibold rounded hover:bg-indigo-700 transition">Enviar Calificación</button>
                </form>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- MODAL PARA SOLICITAR SERVICIO CON DIRECCIÓN -->
      <div v-if="servicioASolicitar" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg max-w-md w-full p-6 space-y-4 shadow-xl">
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">
            Solicitar Servicio: {{ servicioASolicitar.titulo || servicioASolicitar.nombre }}
          </h3>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            Precio estimado: <strong class="text-indigo-600">${{ servicioASolicitar.precio_base ?? servicioASolicitar.precio ?? '0.00' }}</strong>
          </p>
          
          <form @submit.prevent="solicitarServicioSubmit" class="space-y-3">
            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Dirección del Servicio</label>
              <input 
                v-model="formPedido.direccion_servicio" 
                type="text" 
                required 
                placeholder="Ej: Calle Principal 123, Depto 4B"
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white dark:border-gray-600"
              />
            </div>

            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Fecha preferida (opcional)</label>
              <input 
                v-model="formPedido.fecha_deseada" 
                type="date" 
                class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white dark:border-gray-600"
              />
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button 
                type="button" 
                @click="servicioASolicitar = null"
                class="px-3 py-1.5 bg-gray-200 dark:bg-gray-600 text-gray-700 dark:text-gray-200 text-xs font-bold rounded"
              >
                Cancelar
              </button>
              <button 
                type="submit" 
                :disabled="formPedido.processing"
                class="px-4 py-1.5 bg-indigo-600 text-white text-xs font-bold rounded hover:bg-indigo-700 shadow disabled:opacity-50 transition"
              >
                Confirmar Solicitud
              </button>
            </div>
          </form>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>