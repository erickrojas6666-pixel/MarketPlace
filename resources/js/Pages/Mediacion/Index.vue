<script setup>
import { computed } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

/**
 * Cola de mediación (RF-38). Solo nombres: ni correos ni direcciones llegan a esta vista.
 * `disputas` es el paginado de Laravel (data, prev_page_url, next_page_url…).
 */
const props = defineProps({
  disputas: { type: Object, required: true },
  pendientes: { type: Number, default: 0 },
  vista: { type: String, default: 'pendientes' },
})

const filtros = computed(() => [
  { valor: 'pendientes', etiqueta: `Pendientes (${props.pendientes})` },
  { valor: 'resueltas', etiqueta: 'Resueltas' },
])

const filtrar = (vista) => {
  router.get(route('mediacion.disputas.index'), { vista }, { preserveState: true, replace: true })
}

const irA = (url) => {
  if (url) router.get(url, {}, { preserveState: true, preserveScroll: false })
}

const ETIQUETAS = {
  abierta: 'Abierta',
  en_revision: 'En revisión',
  resuelta_favor_cliente: 'A favor del cliente',
  resuelta_favor_prestador: 'A favor del prestador',
  cancelada: 'Cancelada',
}

const etiqueta = (estado) => ETIQUETAS[estado] || estado

const claseEstado = (estado) => {
  switch (estado) {
    case 'abierta':
      return 'bg-orange-50 text-orange-800 border-orange-200'
    case 'en_revision':
      return 'bg-blue-50 text-blue-800 border-blue-200'
    case 'cancelada':
      return 'bg-slate-100 text-slate-600 border-slate-200'
    default:
      return 'bg-emerald-50 text-emerald-800 border-emerald-200'
  }
}

const fecha = (iso) =>
  iso ? new Date(iso).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }) : ''
</script>

<template>
  <Head title="Disputas" />

  <AuthenticatedLayout>
    <div class="py-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <!-- Encabezado -->
      <section class="rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] p-6 sm:p-8 shadow-xl shadow-principal/20">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Mediación de disputas</h1>
        <p class="text-sm text-niebla/80 mt-1">
          Revisa la evidencia de ambas partes y decide si el pago se libera al prestador o se devuelve al cliente.
        </p>
      </section>

      <!-- Filtros -->
      <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filtrar disputas">
        <button
          v-for="f in filtros"
          :key="f.valor"
          type="button"
          role="tab"
          :aria-selected="vista === f.valor"
          @click="filtrar(f.valor)"
          class="px-5 py-2 rounded-xl text-sm font-bold border-2 transition-all duration-200 active:scale-95"
          :class="vista === f.valor
            ? 'bg-principal text-white border-principal shadow-md'
            : 'bg-white text-principal border-slate-200 hover:border-principal/40'"
        >
          {{ f.etiqueta }}
        </button>
      </div>

      <!-- Vacío -->
      <div v-if="!disputas.data.length" class="bg-white rounded-3xl border-2 border-slate-200/90 py-14 text-center px-4">
        <p class="text-sm font-bold text-principal">
          {{ vista === 'pendientes' ? 'No hay disputas pendientes' : 'Todavía no hay disputas resueltas' }}
        </p>
        <p class="text-xs text-gris-medio mt-1">Cuando un cliente reporte un problema, aparecerá aquí.</p>
      </div>

      <!-- Lista -->
      <ul v-else class="space-y-3">
        <li v-for="d in disputas.data" :key="d.id">
          <Link
            :href="route('mediacion.disputas.show', d.id)"
            class="block bg-white rounded-2xl border-2 border-slate-200/90 p-5 shadow-sm hover:border-principal/40 hover:shadow-md transition-all"
          >
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gris-medio">
                  Pedido #{{ d.pedido_id }} · {{ fecha(d.abierta_en) }}
                </p>
                <p class="text-base font-extrabold text-principal truncate">{{ d.servicio || 'Servicio' }}</p>
                <p class="text-sm text-grafito">
                  {{ d.cliente || 'Cliente' }} <span class="text-gris-medio">frente a</span> {{ d.prestador || 'Prestador' }}
                </p>
              </div>

              <div class="flex flex-col items-end gap-1.5 shrink-0">
                <span class="px-3 py-1 rounded-full border text-[11px] font-bold" :class="claseEstado(d.estado)">
                  {{ etiqueta(d.estado) }}
                </span>
                <span v-if="d.mediador" class="text-[11px] text-gris-medio">
                  {{ d.mia ? 'Lo tienes tú' : 'Lo tiene ' + d.mediador }}
                </span>
              </div>
            </div>

            <p class="mt-3 text-sm text-grafito italic">“{{ d.motivo }}”</p>
          </Link>
        </li>
      </ul>

      <!-- Paginación -->
      <nav v-if="disputas.last_page > 1" class="flex items-center justify-between" aria-label="Paginación">
        <button
          type="button"
          :disabled="!disputas.prev_page_url"
          @click="irA(disputas.prev_page_url)"
          class="px-4 py-2 rounded-xl text-sm font-bold border-2 border-slate-200 bg-white text-principal disabled:opacity-40"
        >
          Anterior
        </button>
        <span class="text-xs text-gris-medio">Página {{ disputas.current_page }} de {{ disputas.last_page }}</span>
        <button
          type="button"
          :disabled="!disputas.next_page_url"
          @click="irA(disputas.next_page_url)"
          class="px-4 py-2 rounded-xl text-sm font-bold border-2 border-slate-200 bg-white text-principal disabled:opacity-40"
        >
          Siguiente
        </button>
      </nav>
    </div>
  </AuthenticatedLayout>
</template>
