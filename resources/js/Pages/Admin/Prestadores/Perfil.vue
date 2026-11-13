<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  perfil: { type: Object, required: true },
  usuario: { type: Object, default: () => ({}) },
  servicios: { type: Array, default: () => [] },
  pedidosPorEstado: { type: Object, default: () => ({}) },
  totalPedidos: { type: Number, default: 0 },
  resenas: { type: Object, default: () => ({ promedio: null, total: 0 }) },
})

/* ---------- Aprobar / rechazar (misma lógica que la tabla de prestadores) ---------- */
const mostrarModal = ref(false)
const accionVerificacion = ref('')

const abrirModal = (accion) => {
  accionVerificacion.value = accion
  mostrarModal.value = true
}

const procesarVerificacion = () => {
  router.patch(route('admin.prestadores.updateStatus', props.perfil.id), {
    estado: accionVerificacion.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      mostrarModal.value = false
    },
  })
}

const esAprobacion = computed(() => accionVerificacion.value === 'aprobado')

/* ---------- Datos listos para mostrar ---------- */
const nombre = computed(() => props.usuario?.nombre || 'Prestador #' + props.perfil.id)

const estado = computed(() => props.perfil.estado_verificacion)
const estaVerificado = computed(() => ['verificado', 'aprobado'].includes(estado.value))

const serviciosActivos = computed(() => props.servicios.filter((s) => s.activo).length)

const pedidosLista = computed(() =>
  Object.entries(props.pedidosPorEstado || {}).map(([clave, total]) => ({
    clave,
    total: Number(total) || 0,
    porcentaje: props.totalPedidos > 0 ? Math.round((Number(total) / props.totalPedidos) * 100) : 0,
  }))
)

const promedioRedondeado = computed(() => Math.round(props.resenas?.promedio || 0))

const iniciales = (texto) => {
  const partes = String(texto || '?').trim().split(/\s+/)
  return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?'
}

const fecha = (valor) => {
  if (!valor) return '—'
  return new Date(valor).toLocaleDateString('es-MX', { day: '2-digit', month: 'long', year: 'numeric' })
}

const precio = (valor) => {
  if (valor === null || valor === undefined || valor === '') return '—'
  return '$' + Number(valor).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const etiquetaEstado = (clave) => String(clave || '').replace('_', ' ')

const estiloVerificacion = computed(() => {
  if (estaVerificado.value) return { badge: 'bg-green-50 text-green-700 border-green-200', punto: 'bg-green-500' }
  if (estado.value === 'rechazado') return { badge: 'bg-red-50 text-red-700 border-red-200', punto: 'bg-red-500' }
  return { badge: 'bg-amber-50 text-amber-700 border-amber-200', punto: 'bg-acento' }
})

const estiloPedido = (clave) => {
  switch (clave) {
    case 'pendiente':
      return { punto: 'bg-acento', barra: 'bg-acento' }
    case 'aceptado':
      return { punto: 'bg-sky-500', barra: 'bg-sky-500' }
    case 'en_progreso':
      return { punto: 'bg-principal', barra: 'bg-principal' }
    case 'completado':
      return { punto: 'bg-green-500', barra: 'bg-green-500' }
    case 'rechazado':
    case 'cancelado':
      return { punto: 'bg-red-500', barra: 'bg-red-500' }
    default:
      return { punto: 'bg-slate-400', barra: 'bg-slate-400' }
  }
}

const iconos = {
  volver: 'M11 17l-5-5m0 0l5-5m-5 5h12',
  check: 'M5 13l4 4L19 7',
  cerrar: 'M6 18L18 6M6 6l12 12',
  pin: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
  pedidos: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
  servicios: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z',
  mapa: 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
  calendario: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
  candado: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
  estrella: 'M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z',
}
</script>

<template>
  <Head title="Ficha del prestador" />

  <AuthenticatedLayout>
    <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

      <!-- ============ BANNER ============ -->
      <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] p-6 sm:p-8 shadow-xl shadow-principal/20">
        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-acento/20 blur-2xl"></div>
        <div class="absolute -bottom-24 left-1/3 w-72 h-72 rounded-full bg-white/5"></div>

        <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
          <div class="flex items-center gap-4 min-w-0">
            <div class="w-16 h-16 shrink-0 rounded-2xl bg-white/10 border border-white/15 text-acento font-extrabold text-xl flex items-center justify-center">
              {{ iniciales(nombre) }}
            </div>

            <div class="min-w-0">
              <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">{{ nombre }}</h1>
                <span
                  v-if="estado"
                  class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                  :class="estiloVerificacion.badge"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="estiloVerificacion.punto"></span>
                  {{ estado }}
                </span>
              </div>
              <p class="text-sm text-niebla/80 mt-1 truncate">{{ usuario?.email }}</p>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <Link
              :href="route('admin.prestadores.index')"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white text-sm font-bold rounded-xl border border-white/20 transition-all duration-200 active:scale-95"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.volver" />
              </svg>
              Volver a prestadores
            </Link>

            <button
              v-if="!estaVerificado"
              @click="abrirModal('aprobado')"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-xl shadow-md transition-all duration-200 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-acento"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
              </svg>
              Aprobar
            </button>

            <button
              v-if="estado !== 'rechazado'"
              @click="abrirModal('rechazado')"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-white hover:bg-red-50 text-red-600 text-sm font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition-all duration-200 active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-acento"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
              </svg>
              Rechazar
            </button>
          </div>
        </div>
      </section>

      <!-- ============ RESUMEN ============ -->
      <section class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 border-2 border-slate-200/90 shadow-md">
          <div class="w-11 h-11 rounded-xl bg-niebla text-principal flex items-center justify-center mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pedidos" />
            </svg>
          </div>
          <p class="text-3xl font-extrabold text-principal leading-none">{{ totalPedidos }}</p>
          <p class="text-xs font-bold text-principal uppercase tracking-wider mt-2">Pedidos</p>
          <p class="text-xs text-gris-medio mt-0.5">En todos sus servicios</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border-2 border-slate-200/90 shadow-md">
          <div class="w-11 h-11 rounded-xl bg-niebla text-principal flex items-center justify-center mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.servicios" />
            </svg>
          </div>
          <p class="text-3xl font-extrabold text-principal leading-none">{{ servicios.length }}</p>
          <p class="text-xs font-bold text-principal uppercase tracking-wider mt-2">Servicios</p>
          <p class="text-xs text-gris-medio mt-0.5">{{ serviciosActivos }} {{ serviciosActivos === 1 ? 'activo' : 'activos' }}</p>
        </div>

        <div class="bg-acento/10 rounded-2xl p-5 border-2 border-acento/60 shadow-md">
          <div class="w-11 h-11 rounded-xl bg-acento text-principal flex items-center justify-center mb-4">
            <svg class="w-6 h-6" viewBox="0 0 20 20" fill="currentColor">
              <path :d="iconos.estrella" />
            </svg>
          </div>
          <p class="text-3xl font-extrabold text-principal leading-none">
            {{ resenas?.promedio !== null && resenas?.promedio !== undefined ? resenas.promedio : '—' }}
          </p>
          <p class="text-xs font-bold text-principal uppercase tracking-wider mt-2">Calificación</p>
          <p class="text-xs text-gris-medio mt-0.5">Promedio de reseñas</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border-2 border-slate-200/90 shadow-md">
          <div class="w-11 h-11 rounded-xl bg-niebla text-principal flex items-center justify-center mb-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.calendario" />
            </svg>
          </div>
          <p class="text-base font-extrabold text-principal leading-tight">{{ fecha(perfil.created_at) }}</p>
          <p class="text-xs font-bold text-principal uppercase tracking-wider mt-2">Registro</p>
          <p class="text-xs text-gris-medio mt-0.5">Fecha de solicitud</p>
        </div>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

        <!-- ============ COLUMNA PRINCIPAL ============ -->
        <div class="lg:col-span-2 space-y-8">

          <!-- Información del perfil -->
          <section class="bg-white rounded-3xl p-6 sm:p-8 border-2 border-slate-200/90 shadow-md space-y-6">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Información del perfil</h2>
              <p class="text-xs text-gris-medio mt-0.5">Lo que el prestador escribió sobre sí mismo</p>
            </div>

            <div>
              <p class="text-principal font-bold text-xs uppercase tracking-wider mb-1.5">Descripción</p>
              <p v-if="perfil.descripcion" class="text-sm text-grafito leading-relaxed whitespace-pre-line">{{ perfil.descripcion }}</p>
              <p v-else class="text-sm text-gris-medio italic">Sin descripción.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div class="p-4 bg-niebla/40 rounded-2xl border border-slate-200 flex items-start gap-3">
                <div class="w-10 h-10 shrink-0 rounded-xl bg-white border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-[11px] font-bold text-gris-medio uppercase tracking-wider">Ubicación aproximada</p>
                  <p class="text-sm font-bold text-principal mt-0.5">{{ perfil.ubicacion_aproximada || 'No especificada' }}</p>
                </div>
              </div>

              <div class="p-4 bg-niebla/40 rounded-2xl border border-slate-200 flex items-start gap-3">
                <div class="w-10 h-10 shrink-0 rounded-xl bg-white border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.mapa" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="text-[11px] font-bold text-gris-medio uppercase tracking-wider">Radio de cobertura</p>
                  <p class="text-sm font-bold text-principal mt-0.5">
                    {{ perfil.radio_cobertura_km ? perfil.radio_cobertura_km + ' km' : 'No definido' }}
                  </p>
                </div>
              </div>
            </div>

            <p class="text-xs text-gris-medio flex items-start gap-1.5">
              <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.candado" />
              </svg>
              Por privacidad, aquí solo se muestra la zona aproximada y el radio de cobertura, nunca las coordenadas exactas.
            </p>
          </section>

          <!-- Servicios publicados -->
          <section class="bg-white rounded-3xl p-6 sm:p-8 border-2 border-slate-200/90 shadow-md space-y-5">
            <div class="flex items-center justify-between gap-4">
              <div>
                <h2 class="font-extrabold text-lg text-principal tracking-tight">Servicios publicados</h2>
                <p class="text-xs text-gris-medio mt-0.5">Lo que ofrece en el catálogo</p>
              </div>
              <span class="min-w-8 h-8 px-3 rounded-full bg-niebla text-principal text-sm font-extrabold flex items-center justify-center border border-principal/15">
                {{ servicios.length }}
              </span>
            </div>

            <!-- Vacío -->
            <div v-if="!servicios.length" class="py-10 flex flex-col items-center text-center">
              <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.servicios" />
                </svg>
              </div>
              <p class="text-sm font-bold text-principal">Aún no publica servicios</p>
              <p class="text-xs text-gris-medio mt-0.5">Cuando publique el primero, aparecerá aquí.</p>
            </div>

            <!-- Lista -->
            <ul v-else class="space-y-3">
              <li
                v-for="s in servicios"
                :key="s.id"
                class="p-4 bg-niebla/40 rounded-2xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-principal/30 hover:bg-niebla/60 transition-colors duration-200"
              >
                <div class="min-w-0">
                  <p class="font-bold text-sm text-principal">{{ s.titulo }}</p>
                  <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-principal bg-white px-2.5 py-0.5 rounded-full border border-principal/15">
                      {{ s.categoria || 'General' }}
                    </span>
                    <span class="text-xs font-extrabold text-principal bg-acento/20 border border-acento/50 px-2.5 py-0.5 rounded-full">
                      {{ precio(s.precio_base) }}
                    </span>
                  </div>
                </div>

                <span
                  class="shrink-0 inline-flex items-center gap-2 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider border self-start sm:self-auto"
                  :class="s.activo
                    ? 'bg-green-50 text-green-700 border-green-200'
                    : 'bg-slate-100 text-grafito border-slate-300'"
                >
                  <span class="w-1.5 h-1.5 rounded-full" :class="s.activo ? 'bg-green-500' : 'bg-slate-400'"></span>
                  {{ s.activo ? 'Público' : 'Oculto' }}
                </span>
              </li>
            </ul>
          </section>
        </div>

        <!-- ============ COLUMNA LATERAL ============ -->
        <div class="space-y-8 lg:sticky lg:top-28">

          <!-- Pedidos por estado -->
          <section class="bg-white rounded-3xl p-6 border-2 border-slate-200/90 shadow-md space-y-5">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Pedidos por estado</h2>
              <p class="text-xs text-gris-medio mt-0.5">Cómo van sus solicitudes</p>
            </div>

            <p v-if="!totalPedidos" class="text-sm text-gris-medio">Aún no tiene pedidos.</p>

            <ul v-else class="space-y-4">
              <li v-for="p in pedidosLista" :key="p.clave" class="space-y-1.5">
                <div class="flex items-center justify-between gap-3">
                  <span class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-principal">
                    <span class="w-2 h-2 rounded-full" :class="estiloPedido(p.clave).punto"></span>
                    {{ etiquetaEstado(p.clave) }}
                  </span>
                  <span class="text-sm font-extrabold text-principal">{{ p.total }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                  <div
                    class="h-full rounded-full transition-all duration-500"
                    :class="estiloPedido(p.clave).barra"
                    :style="{ width: p.porcentaje + '%' }"
                  ></div>
                </div>
              </li>
            </ul>
          </section>

          <!-- Reseñas -->
          <section class="bg-white rounded-3xl p-6 border-2 border-slate-200/90 shadow-md space-y-4">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Reseñas</h2>
              <p class="text-xs text-gris-medio mt-0.5">Opinión de sus clientes</p>
            </div>

            <div v-if="resenas?.total" class="flex items-center gap-4">
              <p class="text-4xl font-extrabold text-principal leading-none">{{ resenas.promedio }}</p>
              <div>
                <div class="flex">
                  <svg
                    v-for="n in 5"
                    :key="n"
                    class="w-5 h-5"
                    :class="n <= promedioRedondeado ? 'text-acento' : 'text-slate-300'"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                  >
                    <path :d="iconos.estrella" />
                  </svg>
                </div>
                <p class="text-xs text-gris-medio mt-1">
                  {{ resenas.total }} {{ resenas.total === 1 ? 'reseña' : 'reseñas' }}
                </p>
              </div>
            </div>

            <p v-else class="text-sm text-gris-medio">Aún no tiene reseñas.</p>
          </section>
        </div>
      </div>

      <!-- ============ MODAL APROBAR / RECHAZAR ============ -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="mostrarModal"
          class="fixed inset-0 bg-principal/60 backdrop-blur-sm flex items-center justify-center p-4 z-50"
          @click.self="mostrarModal = false"
        >
          <div class="bg-white rounded-3xl border-2 border-slate-200/90 max-w-md w-full p-7 space-y-5 shadow-2xl text-center">
            <div
              class="w-16 h-16 mx-auto rounded-2xl flex items-center justify-center"
              :class="esAprobacion ? 'bg-green-100 text-green-600' : 'bg-red-100 text-red-600'"
            >
              <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="esAprobacion ? iconos.check : iconos.cerrar" />
              </svg>
            </div>

            <div class="space-y-2">
              <h3 class="text-xl font-extrabold text-principal tracking-tight">
                {{ esAprobacion ? 'Aprobar prestador' : 'Rechazar prestador' }}
              </h3>
              <p class="text-sm text-gris-medio leading-relaxed">
                ¿Confirmas que quieres {{ esAprobacion ? 'aprobar' : 'rechazar' }} a
                <strong class="text-principal">{{ nombre }}</strong>?
              </p>
            </div>

            <div class="flex gap-3 pt-2">
              <button
                type="button"
                @click="mostrarModal = false"
                class="flex-1 py-2.5 bg-white hover:bg-slate-50 text-grafito text-sm font-bold rounded-xl border-2 border-slate-200 transition active:scale-95"
              >
                Cancelar
              </button>
              <button
                type="button"
                @click="procesarVerificacion"
                class="flex-1 py-2.5 text-white text-sm font-bold rounded-xl shadow-md transition active:scale-95"
                :class="esAprobacion ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
              >
                {{ esAprobacion ? 'Sí, aprobar' : 'Sí, rechazar' }}
              </button>
            </div>
          </div>
        </div>
      </Transition>

    </div>
  </AuthenticatedLayout>
</template>