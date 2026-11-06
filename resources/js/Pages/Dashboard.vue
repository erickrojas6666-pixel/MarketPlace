<script setup>
import { ref, computed } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import BuscadorUbicacion from '@/Components/BuscadorUbicacion.vue'
import { useGeolocalizacion, redondear } from '@/composables/useGeolocalizacion'

const props = defineProps({
  esPrestador: { type: Boolean, default: false },
  perfil: { type: Object, default: null },
  servicios: { type: Array, default: () => [] },
  pedidos: { type: Array, default: () => [] },
  categorias: { type: Array, default: () => [] },
  misPedidos: { type: Array, default: () => [] },
  todosLosServicios: { type: Array, default: () => [] },
  notificaciones: { type: Array, default: () => [] },
})

const page = usePage()

// Única fuente de verdad: lo decide DashboardController (rol + perfil).
const esPrestadorUser = computed(() => props.esPrestador)

const estaVerificado = computed(() => {
  const est = props.perfil?.estado_verificacion?.toLowerCase()
  return est === 'aprobado' || est === 'verificado'
})

/* ---------- Íconos (trazos SVG) ---------- */
const iconos = {
  buscar: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
  campana: 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
  check: 'M5 13l4 4L19 7',
  cerrar: 'M6 18L18 6M6 6l12 12',
  mas: 'M12 4v16m8-8H4',
  pedidos: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
  servicios: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z',
  calendario: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  pin: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
  alerta: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
  flecha: 'M13 7l5 5m0 0l-5 5m5-5H6',
  candado: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
  estrella: 'M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z',
}

/* ---------- Clases reutilizables ---------- */
const claseInput =
  'block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400'
const claseLabel = 'block text-principal font-bold text-xs uppercase tracking-wider mb-1.5'

/* ---------- Ayudas visuales ---------- */
const nombreDe = (usuario, porDefecto) => usuario?.nombre || usuario?.name || porDefecto
const precioDe = (servicio) => servicio?.precio_base ?? '0.00'

const nombreUsuario = computed(() => {
  const u = page.props.auth?.user
  return (u?.nombre || u?.name || 'Usuario').split(' ')[0]
})

const iniciales = (texto) => {
  const partes = String(texto || '?').trim().split(/\s+/)
  return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?'
}

const fecha = (valor) => {
  if (!valor) return '—'
  return new Date(valor).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

const etiquetaEstado = (estado) => String(estado || '').replace('_', ' ')

const estiloEstado = (estado) => {
  switch (estado) {
    case 'pendiente':
      return { badge: 'bg-amber-50 text-amber-700 border-amber-200', punto: 'bg-acento' }
    case 'aceptado':
      return { badge: 'bg-sky-50 text-sky-700 border-sky-200', punto: 'bg-sky-500' }
    case 'en_progreso':
      return { badge: 'bg-niebla text-principal border-principal/20', punto: 'bg-principal' }
    case 'completado':
      return { badge: 'bg-green-50 text-green-700 border-green-200', punto: 'bg-green-500' }
    case 'rechazado':
    case 'cancelado':
      return { badge: 'bg-red-50 text-red-700 border-red-200', punto: 'bg-red-500' }
    default:
      return { badge: 'bg-slate-100 text-grafito border-slate-300', punto: 'bg-slate-400' }
  }
}

const estiloNotificacion = (estado) => {
  if (['verificado', 'aprobado', 'aceptado', 'completado'].includes(estado)) {
    return { caja: 'bg-green-50 border-green-200 text-green-900', icono: 'bg-green-100 text-green-600', path: iconos.check }
  }
  if (['rechazado', 'cancelado'].includes(estado)) {
    return { caja: 'bg-red-50 border-red-200 text-red-900', icono: 'bg-red-100 text-red-600', path: iconos.cerrar }
  }
  return { caja: 'bg-white border-slate-200/90 text-grafito', icono: 'bg-niebla text-principal', path: iconos.campana }
}

const estiloVerificacion = computed(() => {
  const est = props.perfil?.estado_verificacion?.toLowerCase()
  if (est === 'aprobado' || est === 'verificado') return { badge: 'bg-green-50 text-green-700 border-green-200', punto: 'bg-green-500' }
  if (est === 'rechazado') return { badge: 'bg-red-50 text-red-700 border-red-200', punto: 'bg-red-500' }
  return { badge: 'bg-amber-50 text-amber-700 border-amber-200', punto: 'bg-acento' }
})

/* ---------- Resúmenes para los banners ---------- */
const pedidosActivosCliente = computed(
  () => props.misPedidos.filter((p) => ['pendiente', 'aceptado', 'en_progreso'].includes(p.estado)).length
)
const pedidosCompletadosCliente = computed(
  () => props.misPedidos.filter((p) => p.estado === 'completado').length
)
const serviciosActivos = computed(() => props.servicios.filter((s) => s.activo).length)
const pedidosPorAtender = computed(
  () => props.pedidos.filter((p) => ['pendiente', 'aceptado', 'en_progreso'].includes(p.estado)).length
)

// --- FILTROS DE BÚSQUEDA Y ESPECIALIDAD PARA CLIENTE ---
const categoriaSeleccionada = ref(null)
const busquedaTexto = ref('')

const serviciosFiltrados = computed(() => {
  const texto = busquedaTexto.value.trim().toLowerCase()

  return props.todosLosServicios.filter((s) => {
    const coincideCategoria =
      !categoriaSeleccionada.value || s.categoria_id === categoriaSeleccionada.value

    const titulo = (s.titulo || '').toLowerCase()
    const desc = (s.descripcion || '').toLowerCase()
    const categoria = (s.categoria?.nombre || '').toLowerCase()
    const prestador = nombreDe(s.perfil_prestador?.user, '').toLowerCase()

    const coincideTexto =
      !texto ||
      titulo.includes(texto) ||
      desc.includes(texto) ||
      categoria.includes(texto) ||
      prestador.includes(texto)

    return coincideCategoria && coincideTexto
  })
})

const seleccionarCategoria = (catId) => {
  categoriaSeleccionada.value = categoriaSeleccionada.value === catId ? null : catId
}

// --- CERCA DE MÍ (CLIENTE): lleva al catálogo filtrado por cercanía ---
const { cargando: ubicando, error: errorUbicacion, obtener } = useGeolocalizacion()

const irAlCatalogoCercano = (la, lo) => {
  router.get(route('servicios.index'), {
    lat: redondear(la),
    lng: redondear(lo),
    radio_km: 10,
  })
}

const usarMiUbicacion = async () => {
  const pos = await obtener()
  if (pos) irAlCatalogoCercano(pos.lat, pos.lng)
}

const usarLugar = (lugar) => irAlCatalogoCercano(parseFloat(lugar.lat), parseFloat(lugar.lon))

// --- MODAL DE SOLICITUD DE SERVICIO ---
const servicioASolicitar = ref(null)
const hoy = new Date().toISOString().split('T')[0]

const formPedido = useForm({
  servicio_id: null,
  direccion_servicio: '',
  fecha_deseada: '',
})

const abrirModalSolicitar = (servicio) => {
  servicioASolicitar.value = servicio
  formPedido.clearErrors()
  formPedido.servicio_id = servicio.id
  formPedido.direccion_servicio = ''
  formPedido.fecha_deseada = ''
}

const cerrarModal = () => {
  servicioASolicitar.value = null
  formPedido.clearErrors()
}

const solicitarServicioSubmit = () => {
  formPedido.post(route('pedidos.store'), {
    preserveScroll: true,
    onSuccess: () => {
      servicioASolicitar.value = null
      formPedido.reset()
    },
  })
}

// --- RESEÑAS POR PEDIDO ---
const calificacionForm = ref({})
const comentarioForm = ref({})

const enviarResena = (pedidoId) => {
  router.post(
    route('resenas.store'),
    {
      pedido_id: pedidoId,
      calificacion: calificacionForm.value[pedidoId] || 5,
      comentario: comentarioForm.value[pedidoId] || '',
    },
    { preserveScroll: true }
  )
}

// --- NUEVO SERVICIO (PRESTADOR) ---
const mostrarFormServicio = ref(false)
const formServicio = useForm({
  titulo: '',
  categoria_id: '',
  precio: '',
  descripcion: '',
})

const guardarServicio = () => {
  formServicio.post(route('servicios.store'), {
    preserveScroll: true,
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
  router.patch(
    route('pedidos.updateEstado', pedidoId),
    { estado: nuevoEstado },
    { preserveScroll: true }
  )
}

const marcarNotificacionesLeidas = () => {
  router.post(route('notificaciones.marcarLeidas'), {}, { preserveScroll: true })
}
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

      <!-- ============ NOTIFICACIONES ============ -->
      <section v-if="notificaciones.length > 0" class="space-y-3">
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <h2 class="text-xs font-bold text-gris-medio uppercase tracking-wider">Notificaciones pendientes</h2>
            <span class="min-w-7 h-7 px-2 rounded-full bg-acento text-principal text-xs font-extrabold flex items-center justify-center shadow-sm">
              {{ notificaciones.length }}
            </span>
          </div>

          <button
            @click="marcarNotificacionesLeidas"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-niebla text-principal text-xs font-bold rounded-xl border-2 border-slate-200 hover:border-principal/40 transition active:scale-95"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
            </svg>
            Marcar todas como leídas
          </button>
        </div>

        <div
          v-for="n in notificaciones"
          :key="n.id"
          class="p-4 rounded-2xl border-2 shadow-md flex items-start gap-3"
          :class="estiloNotificacion(n?.data?.estado).caja"
        >
          <div
            class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center"
            :class="estiloNotificacion(n?.data?.estado).icono"
          >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="estiloNotificacion(n?.data?.estado).path" />
            </svg>
          </div>
          <div class="min-w-0">
            <p class="font-extrabold text-sm tracking-tight">{{ n?.data?.titulo || 'Notificación del sistema' }}</p>
            <p class="text-sm mt-0.5 opacity-90">{{ n?.data?.mensaje }}</p>
          </div>
        </div>
      </section>

      <!-- =====================================================
           VISTA PARA EL PRESTADOR
           ===================================================== -->
      <div v-if="esPrestadorUser" class="space-y-8">

        <!-- Banner -->
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] p-6 sm:p-10 shadow-xl shadow-principal/20">
          <div class="absolute -top-16 -right-16 w-72 h-72 rounded-full bg-acento/20 blur-2xl"></div>
          <div class="absolute -bottom-24 left-1/3 w-80 h-80 rounded-full bg-white/5"></div>

          <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-8">
            <div class="space-y-3">
              <span class="inline-flex items-center gap-2 py-1.5 px-4 rounded-full bg-white/10 text-niebla text-xs font-bold tracking-wider uppercase border border-white/15">
                <span class="w-2 h-2 rounded-full bg-acento animate-pulse"></span>
                Panel de prestador
              </span>

              <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                Hola, <span class="text-acento">{{ nombreUsuario }}</span>
              </h1>

              <span
                v-if="perfil?.estado_verificacion"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                :class="estiloVerificacion.badge"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="estiloVerificacion.punto"></span>
                Perfil {{ perfil.estado_verificacion }}
              </span>
            </div>

            <div class="flex gap-3">
              <div class="bg-white/10 border border-white/15 rounded-2xl px-5 py-3 text-center min-w-28">
                <p class="text-3xl font-extrabold text-acento leading-none">{{ serviciosActivos }}</p>
                <p class="text-xs text-niebla/80 mt-1.5 font-semibold">Servicios activos</p>
              </div>
              <div class="bg-white/10 border border-white/15 rounded-2xl px-5 py-3 text-center min-w-28">
                <p class="text-3xl font-extrabold text-acento leading-none">{{ pedidosPorAtender }}</p>
                <p class="text-xs text-niebla/80 mt-1.5 font-semibold">Pedidos por atender</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Aviso de perfil sin verificar -->
        <div
          v-if="!estaVerificado"
          class="p-4 rounded-2xl bg-amber-50 border-2 border-amber-200 text-amber-800 flex items-start gap-3"
        >
          <div class="w-10 h-10 shrink-0 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.alerta" />
            </svg>
          </div>
          <div>
            <p class="font-extrabold text-sm">Tu perfil aún no está verificado</p>
            <p class="text-sm mt-0.5">Podrás publicar servicios cuando un administrador lo apruebe.</p>
          </div>
        </div>

        <BuscadorUbicacion />

        <!-- Mis servicios -->
        <section class="bg-white rounded-3xl p-6 border-2 border-slate-200/90 shadow-md space-y-5">
          <div class="flex items-center justify-between gap-4">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Mis servicios</h2>
              <p class="text-xs text-gris-medio mt-0.5">Lo que los clientes ven de ti en el catálogo</p>
            </div>

            <div class="flex items-center gap-3">
              <span class="min-w-8 h-8 px-3 rounded-full bg-niebla text-principal text-sm font-extrabold flex items-center justify-center border border-principal/15">
                {{ servicios.length }}
              </span>
              <button
                v-if="estaVerificado"
                @click="mostrarFormServicio = !mostrarFormServicio"
                class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-bold rounded-xl shadow-md transition-all duration-200 active:scale-95"
                :class="mostrarFormServicio
                  ? 'bg-white text-grafito border-2 border-slate-200 hover:bg-slate-50'
                  : 'bg-acento text-principal hover:bg-yellow-400 hover:-translate-y-0.5 hover:shadow-lg'"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="mostrarFormServicio ? iconos.cerrar : iconos.mas" />
                </svg>
                {{ mostrarFormServicio ? 'Cancelar' : 'Publicar servicio' }}
              </button>
            </div>
          </div>

          <!-- Formulario de nuevo servicio -->
          <form
            v-if="mostrarFormServicio"
            @submit.prevent="guardarServicio"
            class="p-5 bg-niebla/40 rounded-2xl border border-slate-200 space-y-4"
          >
            <div>
              <label :class="claseLabel">Nombre del servicio</label>
              <input
                v-model="formServicio.titulo"
                type="text"
                required
                maxlength="255"
                placeholder="Ej: Reparación de fugas y cañerías"
                :class="claseInput"
              />
              <span v-if="formServicio.errors.titulo" class="block mt-1 text-xs font-semibold text-red-600">{{ formServicio.errors.titulo }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label :class="claseLabel">Categoría</label>
                <select v-model="formServicio.categoria_id" required :class="claseInput">
                  <option value="" disabled>Selecciona una categoría</option>
                  <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
                <span v-if="formServicio.errors.categoria_id" class="block mt-1 text-xs font-semibold text-red-600">{{ formServicio.errors.categoria_id }}</span>
              </div>

              <div>
                <label :class="claseLabel">Precio estimado ($)</label>
                <input
                  v-model="formServicio.precio"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  :class="claseInput"
                />
                <span v-if="formServicio.errors.precio" class="block mt-1 text-xs font-semibold text-red-600">{{ formServicio.errors.precio }}</span>
              </div>
            </div>

            <div>
              <label :class="claseLabel">Descripción detallada</label>
              <textarea
                v-model="formServicio.descripcion"
                rows="3"
                required
                placeholder="Describe lo que incluye..."
                :class="claseInput"
              ></textarea>
              <span v-if="formServicio.errors.descripcion" class="block mt-1 text-xs font-semibold text-red-600">{{ formServicio.errors.descripcion }}</span>
            </div>

            <div class="flex justify-end">
              <button
                type="submit"
                :disabled="formServicio.processing"
                class="px-6 py-2.5 bg-principal text-white text-sm font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95"
                :class="{ 'opacity-50 cursor-not-allowed': formServicio.processing }"
              >
                Guardar y publicar
              </button>
            </div>
          </form>

          <!-- Vacío -->
          <div v-if="!servicios.length" class="py-10 flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.servicios" />
              </svg>
            </div>
            <p class="text-sm font-bold text-principal">Aún no has publicado servicios</p>
            <p class="text-xs text-gris-medio mt-0.5">Cuando publiques el primero, aparecerá aquí.</p>
          </div>

          <!-- Lista -->
          <ul v-else class="space-y-3">
            <li
              v-for="s in servicios"
              :key="s.id"
              class="p-4 bg-niebla/40 rounded-2xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-principal/30 hover:bg-niebla/60 transition-colors duration-200"
            >
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-bold text-sm text-principal">{{ s.titulo }}</p>
                  <span class="text-xs font-extrabold text-principal bg-acento/20 border border-acento/50 px-2.5 py-0.5 rounded-full">
                    ${{ precioDe(s) }}
                  </span>
                </div>
                <p class="text-xs text-gris-medio mt-1 line-clamp-2">{{ s.descripcion }}</p>
              </div>

              <button
                @click="toggleServicio(s.id)"
                class="shrink-0 inline-flex items-center gap-2 px-3.5 py-2 text-[11px] font-bold uppercase tracking-wider rounded-full border transition active:scale-95"
                :class="s.activo
                  ? 'bg-green-50 text-green-700 border-green-200 hover:bg-green-100'
                  : 'bg-slate-100 text-grafito border-slate-300 hover:bg-slate-200'"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="s.activo ? 'bg-green-500' : 'bg-slate-400'"></span>
                {{ s.activo ? 'Público (activo)' : 'Oculto (inactivo)' }}
              </button>
            </li>
          </ul>
        </section>

        <!-- Pedidos recibidos -->
        <section class="bg-white rounded-3xl p-6 border-2 border-slate-200/90 shadow-md space-y-5">
          <div class="flex items-center justify-between gap-4">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Pedidos recibidos</h2>
              <p class="text-xs text-gris-medio mt-0.5">Solicitudes de tus clientes</p>
            </div>
            <span class="min-w-8 h-8 px-3 rounded-full bg-niebla text-principal text-sm font-extrabold flex items-center justify-center border border-principal/15">
              {{ pedidos.length }}
            </span>
          </div>

          <!-- Vacío -->
          <div v-if="!pedidos.length" class="py-10 flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pedidos" />
              </svg>
            </div>
            <p class="text-sm font-bold text-principal">Aún no tienes pedidos</p>
            <p class="text-xs text-gris-medio mt-0.5">Cuando un cliente solicite tu servicio, lo verás aquí.</p>
          </div>

          <!-- Lista -->
          <div v-else class="space-y-3">
            <div
              v-for="p in pedidos"
              :key="p.id"
              class="p-4 bg-niebla/40 rounded-2xl border border-slate-200 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 hover:border-principal/30 hover:bg-niebla/60 transition-colors duration-200"
            >
              <div class="flex items-start gap-3 min-w-0">
                <div class="w-11 h-11 shrink-0 rounded-xl bg-gradient-to-br from-principal to-[#1d4a8a] text-white font-extrabold text-sm flex items-center justify-center shadow-sm">
                  {{ iniciales(nombreDe(p.cliente, 'Cliente')) }}
                </div>

                <div class="min-w-0 space-y-1">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-bold text-sm text-principal">Pedido #{{ p.id }}</span>
                    <span
                      class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                      :class="estiloEstado(p.estado).badge"
                    >
                      <span class="w-1.5 h-1.5 rounded-full" :class="estiloEstado(p.estado).punto"></span>
                      {{ etiquetaEstado(p.estado) }}
                    </span>
                  </div>
                  <p class="text-xs text-grafito"><strong class="text-principal">Cliente:</strong> {{ nombreDe(p.cliente, 'Cliente') }}</p>
                  <p class="text-xs text-grafito"><strong class="text-principal">Servicio:</strong> {{ p.servicio?.titulo || ('Servicio #' + p.servicio_id) }}</p>
                  <p v-if="p.direccion_servicio" class="text-xs text-gris-medio flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
                    </svg>
                    {{ p.direccion_servicio }}
                  </p>
                </div>
              </div>

              <div class="flex flex-wrap gap-2 shrink-0">
                <button
                  v-if="p.estado === 'pendiente'"
                  @click="cambiarEstadoPedido(p.id, 'aceptado')"
                  class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95"
                >
                  Aceptar
                </button>
                <button
                  v-if="p.estado === 'pendiente'"
                  @click="cambiarEstadoPedido(p.id, 'rechazado')"
                  class="px-4 py-2 bg-white hover:bg-red-50 text-red-600 text-xs font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition active:scale-95"
                >
                  Rechazar
                </button>
                <button
                  v-if="p.estado === 'aceptado'"
                  @click="cambiarEstadoPedido(p.id, 'en_progreso')"
                  class="px-4 py-2 bg-principal hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95"
                >
                  Iniciar trabajo
                </button>
                <button
                  v-if="p.estado === 'en_progreso'"
                  @click="cambiarEstadoPedido(p.id, 'completado')"
                  class="px-4 py-2 bg-acento hover:bg-yellow-400 text-principal text-xs font-bold rounded-xl shadow-sm transition active:scale-95"
                >
                  Finalizar trabajo
                </button>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- =====================================================
           VISTA PARA EL CLIENTE
           ===================================================== -->
      <div v-else class="space-y-8">

        <!-- Banner -->
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] p-6 sm:p-10 shadow-xl shadow-principal/20">
          <div class="absolute -top-16 -right-16 w-72 h-72 rounded-full bg-acento/20 blur-2xl"></div>
          <div class="absolute -bottom-24 left-1/3 w-80 h-80 rounded-full bg-white/5"></div>

          <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-8">
            <div class="space-y-3">
              <span class="inline-flex items-center gap-2 py-1.5 px-4 rounded-full bg-white/10 text-niebla text-xs font-bold tracking-wider uppercase border border-white/15">
                <span class="w-2 h-2 rounded-full bg-acento animate-pulse"></span>
                Tu panel
              </span>

              <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
                Hola, <span class="text-acento">{{ nombreUsuario }}</span>
              </h1>

              <p class="text-sm sm:text-base text-niebla/80">¿Qué servicio necesitas hoy?</p>
            </div>

            <div class="flex gap-3">
              <div class="bg-white/10 border border-white/15 rounded-2xl px-5 py-3 text-center min-w-28">
                <p class="text-3xl font-extrabold text-acento leading-none">{{ pedidosActivosCliente }}</p>
                <p class="text-xs text-niebla/80 mt-1.5 font-semibold">Pedidos activos</p>
              </div>
              <div class="bg-white/10 border border-white/15 rounded-2xl px-5 py-3 text-center min-w-28">
                <p class="text-3xl font-extrabold text-acento leading-none">{{ pedidosCompletadosCliente }}</p>
                <p class="text-xs text-niebla/80 mt-1.5 font-semibold">Completados</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Cerca de mí (lleva al catálogo filtrado por cercanía) -->
        <section class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md p-6 space-y-4">
          <div class="flex flex-wrap items-center gap-3">
            <button
              type="button"
              @click="usarMiUbicacion"
              :disabled="ubicando"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-principal hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-md transition-all duration-200 active:scale-95"
              :class="{ 'opacity-50 cursor-not-allowed': ubicando }"
            >
              <svg class="w-4 h-4 text-acento" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
              </svg>
              {{ ubicando ? 'Obteniendo ubicación...' : 'Cerca de mí' }}
            </button>

            <p class="text-xs text-gris-medio">
              Te llevamos al catálogo con los prestadores que atienden tu zona.
            </p>
          </div>

          <p v-if="errorUbicacion" class="text-sm font-semibold text-red-600 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5">
            {{ errorUbicacion }}
          </p>

          <p class="text-xs text-gris-medio flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.candado" />
            </svg>
            Tu ubicación solo se usa para esta búsqueda; no se guarda en nuestros servidores.
          </p>

          <div class="pt-4 border-t border-slate-100">
            <BuscadorUbicacion
              embebido
              seleccionable
              titulo="¿Sin GPS? Busca tu dirección"
              @seleccionar="usarLugar"
            />
          </div>
        </section>

        <!-- Explorar servicios -->
        <section class="space-y-5">
          <div>
            <h2 class="text-2xl font-extrabold text-principal tracking-tight">Explorar servicios</h2>
            <p class="text-sm text-gris-medio mt-1">Busca por especialidad, servicio o prestador.</p>
          </div>

          <!-- Buscador -->
          <div class="flex items-center gap-2 bg-white p-2.5 rounded-2xl shadow-xl border border-slate-200">
            <div class="flex-1 flex items-center px-3 gap-2">
              <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.buscar" />
              </svg>
              <input
                v-model="busquedaTexto"
                type="text"
                placeholder="Buscar por especialidad, servicio o prestador..."
                class="w-full border-0 focus:ring-0 text-grafito placeholder-gray-400 text-sm outline-none bg-transparent"
              />
            </div>
            <button
              v-if="busquedaTexto"
              type="button"
              @click="busquedaTexto = ''"
              class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2.5 bg-niebla hover:bg-niebla/70 text-principal text-xs font-bold rounded-xl transition active:scale-95"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
              </svg>
              Limpiar
            </button>
          </div>

          <!-- Categorías -->
          <div class="flex flex-wrap gap-2">
            <button
              @click="categoriaSeleccionada = null"
              class="px-4 py-2 rounded-xl text-sm font-bold border-2 transition-all duration-200 active:scale-95"
              :class="!categoriaSeleccionada
                ? 'bg-principal text-white border-principal shadow-md'
                : 'bg-white text-principal border-slate-200 hover:border-principal/40'"
            >
              Todas
            </button>
            <button
              v-for="c in categorias"
              :key="c.id"
              @click="seleccionarCategoria(c.id)"
              class="px-4 py-2 rounded-xl text-sm font-bold border-2 transition-all duration-200 active:scale-95"
              :class="categoriaSeleccionada === c.id
                ? 'bg-principal text-white border-principal shadow-md'
                : 'bg-white text-principal border-slate-200 hover:border-principal/40'"
            >
              {{ c.nombre }}
            </button>
          </div>

          <!-- Vacío -->
          <div
            v-if="!serviciosFiltrados.length"
            class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md py-14 px-6 flex flex-col items-center text-center"
          >
            <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.buscar" />
              </svg>
            </div>
            <p class="text-sm font-bold text-principal">Sin resultados</p>
            <p class="text-xs text-gris-medio mt-1 max-w-md">
              No se encontraron servicios disponibles para esta especialidad o búsqueda.
            </p>
          </div>

          <!-- Tarjetas -->
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <article
              v-for="s in serviciosFiltrados"
              :key="s.id"
              class="bg-white rounded-2xl border-2 border-slate-200/90 shadow-md hover:shadow-2xl hover:border-principal/40 hover:-translate-y-1.5 transition-all duration-300 overflow-hidden flex flex-col justify-between"
            >
              <div class="p-6 space-y-3">
                <div class="flex justify-between items-center gap-2">
                  <span class="text-[11px] font-bold uppercase tracking-wider text-principal bg-niebla px-3 py-1 rounded-full border border-principal/15 truncate">
                    {{ s.categoria?.nombre || 'General' }}
                  </span>
                  <span class="shrink-0 text-sm font-extrabold text-principal bg-acento/20 border border-acento/50 px-3 py-1 rounded-full">
                    ${{ precioDe(s) }}
                  </span>
                </div>

                <h3 class="text-lg font-extrabold text-principal tracking-tight">{{ s.titulo }}</h3>
                <p class="text-sm text-gris-medio leading-relaxed line-clamp-3">{{ s.descripcion }}</p>
              </div>

              <div class="px-6 py-4 bg-niebla/40 border-t border-slate-200 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                  <div class="w-9 h-9 shrink-0 rounded-xl bg-gradient-to-br from-principal to-[#1d4a8a] text-white font-extrabold text-xs flex items-center justify-center shadow-sm">
                    {{ iniciales(nombreDe(s.perfil_prestador?.user, 'Prestador Verificado')) }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-[11px] text-gris-medio leading-none">Ofrecido por</p>
                    <p class="text-xs font-bold text-principal truncate mt-0.5">
                      {{ nombreDe(s.perfil_prestador?.user, 'Prestador Verificado') }}
                    </p>
                  </div>
                </div>

                <button
                  @click="abrirModalSolicitar(s)"
                  class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 bg-principal hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-sm transition-all duration-200 active:scale-95"
                >
                  Solicitar
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.flecha" />
                  </svg>
                </button>
              </div>
            </article>
          </div>
        </section>

        <!-- Mis pedidos -->
        <section class="bg-white rounded-3xl p-6 border-2 border-slate-200/90 shadow-md space-y-5">
          <div class="flex items-center justify-between gap-4">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Mis pedidos</h2>
              <p class="text-xs text-gris-medio mt-0.5">Sigue el estado de tus solicitudes</p>
            </div>
            <span class="min-w-8 h-8 px-3 rounded-full bg-niebla text-principal text-sm font-extrabold flex items-center justify-center border border-principal/15">
              {{ misPedidos.length }}
            </span>
          </div>

          <!-- Vacío -->
          <div v-if="!misPedidos.length" class="py-10 flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pedidos" />
              </svg>
            </div>
            <p class="text-sm font-bold text-principal">Aún no has solicitado servicios</p>
            <p class="text-xs text-gris-medio mt-0.5">Elige uno del listado de arriba para empezar.</p>
          </div>

          <!-- Lista -->
          <div v-else class="space-y-3">
            <div
              v-for="p in misPedidos"
              :key="p.id"
              class="p-4 bg-niebla/40 rounded-2xl border border-slate-200 space-y-4"
            >
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                  <div class="w-11 h-11 shrink-0 rounded-xl bg-white border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pedidos" />
                    </svg>
                  </div>

                  <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                      <span class="font-bold text-sm text-principal">{{ p.servicio?.titulo || ('Pedido #' + p.id) }}</span>
                      <span
                        class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                        :class="estiloEstado(p.estado).badge"
                      >
                        <span class="w-1.5 h-1.5 rounded-full" :class="estiloEstado(p.estado).punto"></span>
                        {{ etiquetaEstado(p.estado) }}
                      </span>
                    </div>
                    <p v-if="p.created_at" class="text-xs text-gris-medio mt-1 flex items-center gap-1">
                      <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.calendario" />
                      </svg>
                      Solicitado el {{ fecha(p.created_at) }}
                    </p>
                  </div>
                </div>

                <button
                  v-if="p.estado === 'pendiente'"
                  @click="cambiarEstadoPedido(p.id, 'cancelado')"
                  class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-red-50 text-red-600 text-xs font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition active:scale-95"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
                  </svg>
                  Cancelar
                </button>
              </div>

              <!-- Reseña -->
              <div v-if="p.estado === 'completado'" class="pt-4 border-t border-slate-200">
                <div v-if="p.resena" class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-principal uppercase tracking-wider">Tu reseña</span>
                    <div class="flex">
                      <svg
                        v-for="n in 5"
                        :key="n"
                        class="w-4 h-4"
                        :class="n <= (p.resena.calificacion || 5) ? 'text-acento' : 'text-slate-300'"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                      >
                        <path :d="iconos.estrella" />
                      </svg>
                    </div>
                  </div>
                  <p v-if="p.resena.comentario" class="text-xs text-gris-medio italic">"{{ p.resena.comentario }}"</p>
                </div>

                <form v-else @submit.prevent="enviarResena(p.id)" class="space-y-3">
                  <div class="flex items-center gap-3">
                    <label class="text-xs font-bold text-principal uppercase tracking-wider">Calificar servicio</label>
                    <div class="flex gap-0.5">
                      <button
                        v-for="n in 5"
                        :key="n"
                        type="button"
                        @click="calificacionForm[p.id] = n"
                        class="transition-transform duration-150 hover:scale-125 focus:outline-none"
                        :title="n + (n === 1 ? ' estrella' : ' estrellas')"
                      >
                        <svg
                          class="w-6 h-6"
                          :class="n <= (calificacionForm[p.id] || 5) ? 'text-acento' : 'text-slate-300'"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                        >
                          <path :d="iconos.estrella" />
                        </svg>
                      </button>
                    </div>
                  </div>

                  <div class="flex flex-col sm:flex-row gap-2">
                    <input
                      v-model="comentarioForm[p.id]"
                      type="text"
                      maxlength="1000"
                      placeholder="Escribe una opinión sobre el trabajo realizado (opcional)..."
                      :class="claseInput"
                    />
                    <button
                      type="submit"
                      class="shrink-0 px-5 py-2 bg-principal hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-md transition active:scale-95"
                    >
                      Enviar calificación
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </section>
      </div>

      <!-- =====================================================
           MODAL PARA SOLICITAR SERVICIO CON DIRECCIÓN
           ===================================================== -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="servicioASolicitar && !esPrestadorUser"
          class="fixed inset-0 bg-principal/60 backdrop-blur-sm flex items-center justify-center p-4 z-50"
          @click.self="cerrarModal"
        >
          <div class="bg-white rounded-3xl border-2 border-slate-200/90 max-w-md w-full p-7 space-y-5 shadow-2xl">
            <div class="flex items-start gap-3">
              <div class="w-12 h-12 shrink-0 rounded-2xl bg-niebla border border-principal/15 text-principal flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pedidos" />
                </svg>
              </div>
              <div class="min-w-0">
                <h3 class="text-lg font-extrabold text-principal tracking-tight">Solicitar servicio</h3>
                <p class="text-sm text-gris-medio truncate">{{ servicioASolicitar.titulo }}</p>
                <span class="inline-block mt-2 text-xs font-extrabold text-principal bg-acento/20 border border-acento/50 px-3 py-1 rounded-full">
                  Precio estimado: ${{ precioDe(servicioASolicitar) }}
                </span>
              </div>
            </div>

            <form @submit.prevent="solicitarServicioSubmit" class="space-y-4">
              <div>
                <label :class="claseLabel">Dirección del servicio</label>
                <input
                  v-model="formPedido.direccion_servicio"
                  type="text"
                  required
                  maxlength="255"
                  placeholder="Ej: Calle Principal 123, Depto 4B"
                  :class="claseInput"
                />
                <span v-if="formPedido.errors.direccion_servicio" class="block mt-1 text-xs font-semibold text-red-600">{{ formPedido.errors.direccion_servicio }}</span>
              </div>

              <div>
                <label :class="claseLabel">Fecha preferida (opcional)</label>
                <input
                  v-model="formPedido.fecha_deseada"
                  type="date"
                  :min="hoy"
                  :class="claseInput"
                />
                <span v-if="formPedido.errors.fecha_deseada" class="block mt-1 text-xs font-semibold text-red-600">{{ formPedido.errors.fecha_deseada }}</span>
              </div>

              <span v-if="formPedido.errors.servicio_id" class="block text-xs font-semibold text-red-600">{{ formPedido.errors.servicio_id }}</span>

              <div class="flex gap-3 pt-2">
                <button
                  type="button"
                  @click="cerrarModal"
                  class="flex-1 py-2.5 bg-white hover:bg-slate-50 text-grafito text-sm font-bold rounded-xl border-2 border-slate-200 transition active:scale-95"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  :disabled="formPedido.processing"
                  class="flex-1 py-2.5 bg-principal hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-md transition active:scale-95"
                  :class="{ 'opacity-50 cursor-not-allowed': formPedido.processing }"
                >
                  Confirmar solicitud
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>

    </div>
  </AuthenticatedLayout>
</template>