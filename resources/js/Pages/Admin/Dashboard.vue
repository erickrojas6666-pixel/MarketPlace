<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  metrics: Object,
  prestadoresPendientesLista: { type: Array, default: () => [] },
  ultimosPedidos: { type: Array, default: () => [] },
})

const page = usePage()

/* ---------- Datos para el banner de bienvenida ---------- */
const nombreAdmin = computed(() => {
  const u = page.props.auth?.user
  return (u?.nombre || u?.name || 'Admin').split(' ')[0]
})

const hoy = (() => {
  const f = new Date().toLocaleDateString('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  })
  return f.charAt(0).toUpperCase() + f.slice(1)
})()

const pendientes = computed(() => props.metrics?.prestadoresPendientes || 0)

/* ---------- Íconos (trazos SVG) ---------- */
const iconos = {
  usuarios: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
  prestadores: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  reloj: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
  servicios: 'M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z',
  pedidos: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
  categorias: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
  buscar: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
  check: 'M5 13l4 4L19 7',
  cerrar: 'M6 18L18 6M6 6l12 12',
  flecha: 'M13 7l5 5m0 0l-5 5m5-5H6',
}

/* ---------- Tarjetas de métricas ---------- */
const tarjetas = computed(() => [
  { etiqueta: 'Usuarios', valor: props.metrics?.totalUsuarios || 0, nota: 'Cuentas registradas', icono: iconos.usuarios },
  { etiqueta: 'Prestadores', valor: props.metrics?.totalPrestadores || 0, nota: 'Perfiles de prestador', icono: iconos.prestadores },
  { etiqueta: 'Pendientes', valor: pendientes.value, nota: 'Esperan revisión', icono: iconos.reloj, destacada: true },
  { etiqueta: 'Servicios', valor: props.metrics?.totalServicios || 0, nota: 'En la plataforma', icono: iconos.servicios },
  { etiqueta: 'Pedidos', valor: props.metrics?.totalPedidos || 0, nota: 'Solicitudes hechas', icono: iconos.pedidos },
  { etiqueta: 'Categorías', valor: props.metrics?.totalCategorias || 0, nota: 'Áreas de servicio', icono: iconos.categorias },
])

/* ---------- Accesos rápidos ---------- */
const accesos = [
  {
    titulo: 'Usuarios y Roles',
    texto: 'Consulta cuentas y administra los permisos de cada usuario.',
    ruta: 'admin.usuarios.index',
    icono: iconos.usuarios,
  },
  {
    titulo: 'Categorías',
    texto: 'Organiza las áreas de servicio que ve el público.',
    ruta: 'admin.categorias.index',
    icono: iconos.categorias,
  },
  {
    titulo: 'Prestadores',
    texto: 'Revisa perfiles y verifica a los profesionales.',
    ruta: 'admin.prestadores.index',
    icono: iconos.buscar,
  },
]

/* ---------- Ayudas visuales ---------- */
const nombreDe = (p) => p?.user?.name || p?.user?.nombre || 'Prestador #' + (p?.id ?? '')

const iniciales = (texto) => {
  const partes = String(texto || '?').trim().split(/\s+/)
  return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?'
}

const estiloEstado = (estado) => {
  const e = String(estado || '').toLowerCase()
  if (e === 'completado') return { badge: 'bg-green-50 text-green-700 border-green-200', punto: 'bg-green-500' }
  if (e === 'rechazado' || e === 'cancelado') return { badge: 'bg-red-50 text-red-700 border-red-200', punto: 'bg-red-500' }
  if (e === 'pendiente') return { badge: 'bg-amber-50 text-amber-700 border-amber-200', punto: 'bg-acento' }
  return { badge: 'bg-niebla text-principal border-principal/15', punto: 'bg-principal' }
}

/* ---------- Modal aprobar / rechazar ---------- */
const prestadorAProcesar = ref(null)
const accionVerificacion = ref('')
const esAprobacion = computed(() => accionVerificacion.value === 'aprobado')

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
    <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">

      <!-- ============ BANNER DE BIENVENIDA ============ -->
      <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] p-6 sm:p-10 shadow-xl shadow-principal/20">
        <!-- Adornos de fondo -->
        <div class="absolute -top-16 -right-16 w-72 h-72 rounded-full bg-acento/20 blur-2xl"></div>
        <div class="absolute -bottom-24 left-1/3 w-80 h-80 rounded-full bg-white/5"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-8">
          <div class="space-y-3">
            <span class="inline-flex items-center gap-2 py-1.5 px-4 rounded-full bg-white/10 text-niebla text-xs font-bold tracking-wider uppercase border border-white/15">
              <span class="w-2 h-2 rounded-full bg-acento animate-pulse"></span>
              Panel de administración
            </span>

            <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
              Hola, <span class="text-acento">{{ nombreAdmin }}</span>
            </h1>

            <p class="text-sm sm:text-base text-niebla/80">
              {{ hoy }} · Así va hoy OficiosYa.
            </p>
          </div>

          <!-- Aviso de solicitudes -->
          <Link
            v-if="pendientes > 0"
            :href="route('admin.prestadores.index')"
            class="group flex items-center gap-4 bg-acento text-principal rounded-2xl px-6 py-4 shadow-lg hover:shadow-2xl hover:-translate-y-0.5 transition-all duration-200"
          >
            <span class="text-4xl font-extrabold leading-none">{{ pendientes }}</span>
            <span class="text-sm font-bold leading-tight">
              solicitudes de<br />prestadores por revisar
            </span>
            <svg class="w-5 h-5 transform group-hover:translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.flecha" />
            </svg>
          </Link>

          <div
            v-else
            class="flex items-center gap-3 bg-white/10 border border-white/15 text-white rounded-2xl px-6 py-4"
          >
            <span class="w-9 h-9 rounded-full bg-green-500 flex items-center justify-center">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
              </svg>
            </span>
            <span class="text-sm font-bold">Todo al día, sin solicitudes pendientes</span>
          </div>
        </div>
      </section>

      <!-- ============ MÉTRICAS ============ -->
      <section class="space-y-4">
        <h2 class="text-xs font-bold text-gris-medio uppercase tracking-wider">Resumen general</h2>

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
          <div
            v-for="t in tarjetas"
            :key="t.etiqueta"
            class="rounded-2xl p-5 border-2 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300"
            :class="t.destacada ? 'bg-acento/10 border-acento/60' : 'bg-white border-slate-200/90'"
          >
            <div
              class="w-11 h-11 rounded-xl flex items-center justify-center mb-4"
              :class="t.destacada ? 'bg-acento text-principal' : 'bg-niebla text-principal'"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="t.icono" />
              </svg>
            </div>

            <p class="text-3xl font-extrabold text-principal leading-none">{{ t.valor }}</p>
            <p class="text-xs font-bold text-principal uppercase tracking-wider mt-2">{{ t.etiqueta }}</p>
            <p class="text-xs text-gris-medio mt-0.5">{{ t.nota }}</p>
          </div>
        </div>
      </section>

      <!-- ============ ACCESOS RÁPIDOS ============ -->
      <section class="space-y-4">
        <h2 class="text-xs font-bold text-gris-medio uppercase tracking-wider">Accesos rápidos</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Link
            v-for="a in accesos"
            :key="a.titulo"
            :href="route(a.ruta)"
            class="group bg-white rounded-2xl p-6 border-2 border-slate-200/90 shadow-md hover:shadow-2xl hover:border-principal/40 hover:-translate-y-1.5 transition-all duration-300 flex flex-col justify-between"
          >
            <div class="space-y-4">
              <div class="w-14 h-14 rounded-2xl bg-niebla border border-principal/15 flex items-center justify-center text-principal group-hover:bg-principal group-hover:text-acento transition-colors duration-300 shadow-sm">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="a.icono" />
                </svg>
              </div>

              <div>
                <h3 class="font-extrabold text-base text-principal group-hover:text-acento transition-colors duration-200">
                  {{ a.titulo }}
                </h3>
                <p class="text-xs text-gris-medio mt-1.5 leading-relaxed">{{ a.texto }}</p>
              </div>
            </div>

            <div class="mt-5 pt-3.5 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-principal group-hover:text-acento">
              <span>Abrir sección</span>
              <span class="transform group-hover:translate-x-1.5 transition-transform duration-200">→</span>
            </div>
          </Link>
        </div>
      </section>

      <!-- ============ LISTAS ============ -->
      <section class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Prestadores pendientes -->
        <div class="bg-white rounded-3xl p-6 border-2 border-slate-200/90 shadow-md space-y-5">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Prestadores pendientes</h2>
              <p class="text-xs text-gris-medio mt-0.5">Solicitudes que esperan tu decisión</p>
            </div>
            <span class="min-w-8 h-8 px-2 rounded-full bg-acento text-principal text-sm font-extrabold flex items-center justify-center shadow-sm">
              {{ prestadoresPendientesLista.length }}
            </span>
          </div>

          <!-- Vacío -->
          <div v-if="!prestadoresPendientesLista.length" class="py-10 flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
              </svg>
            </div>
            <p class="text-sm font-bold text-principal">Todo al día</p>
            <p class="text-xs text-gris-medio mt-0.5">No hay solicitudes pendientes.</p>
          </div>

          <!-- Lista -->
          <div v-else class="space-y-3">
            <div
              v-for="p in prestadoresPendientesLista"
              :key="p.id"
              class="p-4 bg-niebla/40 rounded-2xl border border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-principal/30 hover:bg-niebla/60 transition-colors duration-200"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 shrink-0 rounded-xl bg-gradient-to-br from-principal to-[#1d4a8a] text-white font-extrabold text-sm flex items-center justify-center shadow-sm">
                  {{ iniciales(nombreDe(p)) }}
                </div>
                <div class="min-w-0">
                  <p class="font-bold text-sm text-principal truncate">{{ nombreDe(p) }}</p>
                  <p class="text-xs text-gris-medio truncate">{{ p.descripcion || 'Sin descripción' }}</p>
                </div>
              </div>

              <div class="flex gap-2 shrink-0">
                <button
                  @click="abrirModal(p, 'aprobado')"
                  class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-acento"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
                  </svg>
                  Aprobar
                </button>
                <button
                  @click="abrirModal(p, 'rechazado')"
                  class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-red-50 text-red-600 text-xs font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-acento"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
                  </svg>
                  Rechazar
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Últimos pedidos -->
        <div class="bg-white rounded-3xl p-6 border-2 border-slate-200/90 shadow-md space-y-5">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Últimos pedidos</h2>
              <p class="text-xs text-gris-medio mt-0.5">Actividad reciente del sistema</p>
            </div>
            <span class="min-w-8 h-8 px-2 rounded-full bg-niebla text-principal text-sm font-extrabold flex items-center justify-center border border-principal/15">
              {{ ultimosPedidos.length }}
            </span>
          </div>

          <!-- Vacío -->
          <div v-if="!ultimosPedidos.length" class="py-10 flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pedidos" />
              </svg>
            </div>
            <p class="text-sm font-bold text-principal">Aún no hay pedidos</p>
            <p class="text-xs text-gris-medio mt-0.5">Cuando los clientes contraten, aparecerán aquí.</p>
          </div>

          <!-- Lista -->
          <div v-else class="space-y-3">
            <div
              v-for="ped in ultimosPedidos"
              :key="ped.id"
              class="p-4 bg-niebla/40 rounded-2xl border border-slate-200 flex items-center justify-between gap-3 hover:border-principal/30 hover:bg-niebla/60 transition-colors duration-200"
            >
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 shrink-0 rounded-xl bg-white border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pedidos" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="font-bold text-sm text-principal truncate">
                    Pedido #{{ ped.id }} · {{ ped.servicio?.titulo || ped.servicio?.nombre || 'Servicio' }}
                  </p>
                  <p class="text-xs text-gris-medio truncate">
                    Cliente: {{ ped.cliente?.name || ped.cliente?.nombre || 'Cliente' }}
                  </p>
                </div>
              </div>

              <span
                class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                :class="estiloEstado(ped.estado).badge"
              >
                <span class="w-1.5 h-1.5 rounded-full" :class="estiloEstado(ped.estado).punto"></span>
                {{ ped.estado }}
              </span>
            </div>
          </div>
        </div>
      </section>

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
          v-if="prestadorAProcesar"
          class="fixed inset-0 bg-principal/60 backdrop-blur-sm flex items-center justify-center p-4 z-50"
          @click.self="prestadorAProcesar = null"
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
                <strong class="text-principal">{{ nombreDe(prestadorAProcesar) }}</strong>?
              </p>
            </div>

            <div class="flex gap-3 pt-2">
              <button
                type="button"
                @click="prestadorAProcesar = null"
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