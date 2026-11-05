<script setup>
import { ref, computed } from 'vue'
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

/* ---------- Ayudas visuales ---------- */
const esAprobacion = computed(() => accionVerificacion.value === 'aprobado')

const filtros = [
  { valor: 'pendiente', etiqueta: 'Pendientes' },
  { valor: 'verificado', etiqueta: 'Verificados' },
  { valor: 'rechazado', etiqueta: 'Rechazados' },
  { valor: 'todos', etiqueta: 'Todos' },
]

const nombreDe = (p) => p?.user?.name || p?.user?.nombre || 'Prestador #' + (p?.id ?? '')

const iniciales = (texto) => {
  const partes = String(texto || '?').trim().split(/\s+/)
  return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?'
}

const estiloEstado = (estado) => {
  const e = String(estado || '').toLowerCase()
  if (e === 'verificado' || e === 'aprobado') return { badge: 'bg-green-50 text-green-700 border-green-200', punto: 'bg-green-500' }
  if (e === 'rechazado') return { badge: 'bg-red-50 text-red-700 border-red-200', punto: 'bg-red-500' }
  return { badge: 'bg-amber-50 text-amber-700 border-amber-200', punto: 'bg-acento' }
}

const fecha = (valor) => {
  if (!valor) return '—'
  return new Date(valor).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

const iconos = {
  buscar: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
  volver: 'M11 17l-5-5m0 0l5-5m-5 5h12',
  check: 'M5 13l4 4L19 7',
  cerrar: 'M6 18L18 6M6 6l12 12',
  pin: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
}
</script>

<template>
  <Head title="Verificación de Prestadores" />

  <AuthenticatedLayout>
    <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

      <!-- ============ BANNER ============ -->
      <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] p-6 sm:p-8 shadow-xl shadow-principal/20">
        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-acento/20 blur-2xl"></div>
        <div class="absolute -bottom-24 left-1/3 w-72 h-72 rounded-full bg-white/5"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 shrink-0 rounded-2xl bg-white/10 border border-white/15 text-acento flex items-center justify-center">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.buscar" />
              </svg>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Verificación de Prestadores</h1>
              <p class="text-sm text-niebla/80 mt-1">Revisa y aprueba las solicitudes de los profesionales.</p>
            </div>
          </div>

          <Link
            :href="route('admin.dashboard')"
            class="inline-flex items-center gap-2 self-start md:self-auto px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white text-sm font-bold rounded-xl border border-white/20 transition-all duration-200 active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.volver" />
            </svg>
            Volver al Dashboard
          </Link>
        </div>
      </section>

      <!-- ============ FILTROS POR ESTADO ============ -->
      <div class="flex flex-wrap gap-2">
        <button
          v-for="f in filtros"
          :key="f.valor"
          @click="filtrar(f.valor)"
          class="px-5 py-2 rounded-xl text-sm font-bold border-2 transition-all duration-200 active:scale-95"
          :class="filtroEstado === f.valor
            ? 'bg-principal text-white border-principal shadow-md'
            : 'bg-white text-principal border-slate-200 hover:border-principal/40'"
        >
          {{ f.etiqueta }}
        </button>
      </div>

      <!-- ============ TABLA DE PRESTADORES ============ -->
      <div class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md overflow-hidden">

        <!-- Vacío -->
        <div v-if="!prestadores.data.length" class="py-14 flex flex-col items-center text-center px-4">
          <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.buscar" />
            </svg>
          </div>
          <p class="text-sm font-bold text-principal">Sin resultados</p>
          <p class="text-xs text-gris-medio mt-0.5">No se encontraron prestadores con el filtro "{{ filtroEstado }}".</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm text-grafito">
            <thead class="bg-niebla/60 text-xs uppercase tracking-wider font-bold text-principal">
              <tr>
                <th class="px-6 py-3">Usuario</th>
                <th class="px-6 py-3">Descripción / Ubicación</th>
                <th class="px-6 py-3">Estado</th>
                <th class="px-6 py-3">Registro</th>
                <th class="px-6 py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr v-for="p in prestadores.data" :key="p.id" class="hover:bg-niebla/30 transition-colors duration-150">
                <td class="px-6 py-4">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-principal to-[#1d4a8a] text-white font-extrabold text-xs flex items-center justify-center shadow-sm">
                      {{ iniciales(nombreDe(p)) }}
                    </div>
                    <div class="min-w-0">
                      <p class="font-bold text-principal truncate">{{ nombreDe(p) }}</p>
                      <p class="text-xs text-gris-medio truncate">{{ p.user?.email }}</p>
                    </div>
                  </div>
                </td>

                <td class="px-6 py-4 max-w-xs">
                  <p class="text-xs text-grafito truncate">{{ p.descripcion || 'Sin descripción' }}</p>
                  <p class="text-xs text-gris-medio mt-1 flex items-center gap-1 truncate">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
                    </svg>
                    {{ p.ubicacion_aproximada || 'Ubicación no especificada' }}
                  </p>
                </td>

                <td class="px-6 py-4">
                  <span
                    class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                    :class="estiloEstado(p.estado_verificacion).badge"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="estiloEstado(p.estado_verificacion).punto"></span>
                    {{ p.estado_verificacion }}
                  </span>
                </td>

                <td class="px-6 py-4 text-xs text-gris-medio whitespace-nowrap">
                  {{ fecha(p.created_at) }}
                </td>

                <td class="px-6 py-4">
                  <div class="flex justify-end gap-2">
                    <button
                      v-if="p.estado_verificacion !== 'verificado'"
                      @click="abrirModal(p, 'aprobado')"
                      class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-xl shadow-sm transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-acento"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
                      </svg>
                      Aprobar
                    </button>
                    <button
                      v-if="p.estado_verificacion !== 'rechazado'"
                      @click="abrirModal(p, 'rechazado')"
                      class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-red-50 text-red-600 text-xs font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition active:scale-95 focus:outline-none focus-visible:ring-2 focus-visible:ring-acento"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
                      </svg>
                      Rechazar
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Paginación -->
        <div
          v-if="prestadores.links && prestadores.links.length > 3"
          class="px-6 py-4 border-t border-slate-200 flex flex-wrap justify-end gap-1.5"
        >
          <template v-for="(link, key) in prestadores.links" :key="key">
            <Link
              v-if="link.url"
              :href="link.url"
              v-html="link.label"
              class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition"
              :class="link.active
                ? 'bg-principal text-white shadow-md'
                : 'bg-niebla/60 text-principal hover:bg-niebla'"
            />
            <span
              v-else
              v-html="link.label"
              class="px-3.5 py-1.5 rounded-xl text-xs font-bold text-slate-400 bg-slate-50 cursor-not-allowed"
            />
          </template>
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