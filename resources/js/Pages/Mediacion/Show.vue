<script setup>
import { computed, ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import EvidenciaDisputa from '@/Components/EvidenciaDisputa.vue'

/**
 * Ficha de una disputa (RF-38): motivo, pago retenido, evidencia de ambas partes y resolución.
 * Solo nombres: ni correos ni la dirección del domicilio llegan a esta vista.
 */
const props = defineProps({
  disputa: { type: Object, required: true },
  pedido: { type: Object, required: true },
  pago: { type: Object, default: null },
  evidenciasVisibles: { type: Boolean, default: false },
  puedeGestionar: { type: Boolean, default: false },
})

const MIN_NOTAS = 10
const MAX_NOTAS = 1500

const form = useForm({ decision: '', notas: '' })
const confirmando = ref(false)
const tomando = ref(false)

const mxn = (valor) =>
  new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(valor) || 0)

const fecha = (iso) =>
  iso
    ? new Date(iso).toLocaleString('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : ''

const ETIQUETAS = {
  abierta: 'Abierta',
  en_revision: 'En revisión',
  resuelta_favor_cliente: 'Resuelta a favor del cliente',
  resuelta_favor_prestador: 'Resuelta a favor del prestador',
  cancelada: 'Cancelada',
}

const etiquetaEstado = computed(() => ETIQUETAS[props.disputa.estado] || props.disputa.estado)
const abierta = computed(() => ['abierta', 'en_revision'].includes(props.disputa.estado))
const sinTomar = computed(() => props.disputa.estado === 'abierta')

const pagoRetenido = computed(() => props.pago?.estado === 'retenido')

/** Consecuencia de cada decisión, dicha en claro antes de confirmar. */
const consecuencia = computed(() => {
  const aFavor = form.decision
  if (!aFavor) return ''

  if (!pagoRetenido.value) {
    return aFavor === 'favor_cliente'
      ? 'El pedido se cancelará. No hay un pago retenido, así que no se moverá dinero.'
      : 'El pedido quedará confirmado. No hay un pago retenido, así que no se moverá dinero.'
  }

  return aFavor === 'favor_cliente'
    ? `El pedido se cancelará y se devolverán ${mxn(props.pago.monto)} al cliente, sin comisión.`
    : `El pedido quedará confirmado, se liberarán ${mxn(props.pago.neto)} al prestador y ${mxn(props.pago.comision)} quedarán como comisión.`
})

const notasValidas = computed(() => form.notas.trim().length >= MIN_NOTAS)
const puedeRevisar = computed(() => !!form.decision && notasValidas.value)

const sinConexion = () => {
  if (!navigator.onLine) {
    form.setError('notas', 'Necesitas conexión para resolver una disputa.')
    return true
  }
  return false
}

const tomar = () => {
  if (tomando.value) return
  tomando.value = true
  router.post(route('mediacion.disputas.tomar', props.disputa.id), {}, {
    preserveScroll: true,
    onFinish: () => (tomando.value = false),
  })
}

const resolver = () => {
  if (sinConexion()) return

  form.post(route('mediacion.disputas.resolver', props.disputa.id), {
    preserveScroll: true,
    onFinish: () => (confirmando.value = false),
  })
}
</script>

<template>
  <Head :title="`Disputa · Pedido #${pedido.id}`" />

  <AuthenticatedLayout>
    <div class="py-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
      <Link :href="route('mediacion.disputas.index')" class="inline-flex items-center gap-1 text-sm font-bold text-principal hover:underline">
        ← Volver a la cola
      </Link>

      <!-- Resumen -->
      <section class="bg-white rounded-3xl border-2 border-slate-200/90 p-6 shadow-sm space-y-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gris-medio">Pedido #{{ pedido.id }} · {{ pedido.categoria || 'Servicio' }}</p>
            <h1 class="text-xl sm:text-2xl font-extrabold text-principal">{{ pedido.servicio || 'Servicio' }}</h1>
            <p class="text-sm text-grafito mt-1">
              <span class="font-bold">{{ pedido.cliente || 'Cliente' }}</span>
              <span class="text-gris-medio"> (cliente) frente a </span>
              <span class="font-bold">{{ pedido.prestador || 'Prestador' }}</span>
              <span class="text-gris-medio"> (prestador)</span>
            </p>
          </div>
          <span class="px-3 py-1 rounded-full bg-niebla text-principal border border-principal/15 text-xs font-bold">{{ etiquetaEstado }}</span>
        </div>

        <p class="text-xs text-gris-medio">
          Abierta el {{ fecha(disputa.abierta_en) }}
          <template v-if="disputa.mediador"> · {{ disputa.mia ? 'La tienes tú' : 'La tiene ' + disputa.mediador }}</template>
        </p>

        <div class="rounded-2xl bg-orange-50 border border-orange-200 p-4">
          <p class="text-[11px] font-bold uppercase tracking-wider text-orange-800">Motivo del cliente</p>
          <p class="text-sm text-orange-950 mt-1 whitespace-pre-line">{{ disputa.motivo }}</p>
        </div>
      </section>

      <!-- Pago -->
      <section class="bg-white rounded-3xl border-2 border-slate-200/90 p-6 shadow-sm space-y-2">
        <h2 class="text-sm font-extrabold text-principal uppercase tracking-wider">Pago</h2>
        <p v-if="!pago" class="text-sm text-gris-medio">Este pedido no tiene un pago cobrado.</p>
        <dl v-else class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
          <div><dt class="text-xs text-gris-medio">Estado</dt><dd class="font-bold text-grafito capitalize">{{ pago.estado }}</dd></div>
          <div><dt class="text-xs text-gris-medio">Monto</dt><dd class="font-bold text-grafito">{{ mxn(pago.monto) }}</dd></div>
          <div><dt class="text-xs text-gris-medio">Si se libera: prestador</dt><dd class="font-bold text-grafito">{{ mxn(pago.neto) }}</dd></div>
          <div><dt class="text-xs text-gris-medio">Si se libera: comisión</dt><dd class="font-bold text-grafito">{{ mxn(pago.comision) }}</dd></div>
        </dl>
      </section>

      <!-- Evidencia -->
      <section class="space-y-3">
        <h2 class="text-sm font-extrabold text-principal uppercase tracking-wider">Evidencia de ambas partes</h2>
        <EvidenciaDisputa
          v-if="evidenciasVisibles"
          :pedido-id="pedido.id"
          :cliente="pedido.cliente || 'Cliente'"
          :prestador="pedido.prestador || 'Prestador'"
        />
        <p v-else class="text-sm text-gris-medio bg-white rounded-2xl border border-slate-200 p-4">
          Con el caso cerrado, las fotos privadas ya no están disponibles para mediación.
        </p>
      </section>

      <!-- Decisión tomada -->
      <section v-if="!abierta" class="bg-white rounded-3xl border-2 border-slate-200/90 p-6 shadow-sm space-y-2">
        <h2 class="text-sm font-extrabold text-principal uppercase tracking-wider">Decisión</h2>
        <p class="text-sm font-bold text-grafito">{{ etiquetaEstado }} · {{ fecha(disputa.resuelta_en) }}</p>
        <p v-if="disputa.resolucion_notas" class="text-sm text-grafito italic">“{{ disputa.resolucion_notas }}”</p>
      </section>

      <!-- Resolver -->
      <section v-else-if="puedeGestionar" class="bg-white rounded-3xl border-2 border-principal/20 p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-sm font-extrabold text-principal uppercase tracking-wider">Tu decisión</h2>
          <button
            v-if="sinTomar"
            type="button"
            :disabled="tomando"
            @click="tomar"
            class="px-4 py-2 rounded-xl text-sm font-bold bg-niebla text-principal border border-principal/20 hover:bg-principal/10 disabled:opacity-50"
          >
            Tomar el caso
          </button>
        </div>

        <fieldset class="space-y-2">
          <legend class="sr-only">Resolver a favor de</legend>
          <label class="flex items-start gap-3 rounded-2xl border-2 p-4 cursor-pointer"
            :class="form.decision === 'favor_prestador' ? 'border-principal bg-niebla/50' : 'border-slate-200'">
            <input v-model="form.decision" type="radio" value="favor_prestador" class="mt-1" :disabled="confirmando" />
            <span>
              <span class="block text-sm font-extrabold text-principal">A favor del prestador</span>
              <span class="block text-xs text-gris-medio">El trabajo se da por bueno: se confirma el pedido y se libera el pago.</span>
            </span>
          </label>
          <label class="flex items-start gap-3 rounded-2xl border-2 p-4 cursor-pointer"
            :class="form.decision === 'favor_cliente' ? 'border-principal bg-niebla/50' : 'border-slate-200'">
            <input v-model="form.decision" type="radio" value="favor_cliente" class="mt-1" :disabled="confirmando" />
            <span>
              <span class="block text-sm font-extrabold text-principal">A favor del cliente</span>
              <span class="block text-xs text-gris-medio">Se cancela el pedido y se devuelve el pago completo, sin comisión.</span>
            </span>
          </label>
          <p v-if="form.errors.decision" class="text-xs font-semibold text-red-600" role="alert">{{ form.errors.decision }}</p>
        </fieldset>

        <div>
          <label for="notas-disputa" class="block text-sm font-bold text-principal mb-1">Explica tu decisión</label>
          <textarea
            id="notas-disputa"
            v-model="form.notas"
            rows="4"
            :maxlength="MAX_NOTAS"
            :disabled="confirmando"
            class="w-full rounded-2xl border-2 border-slate-200 focus:border-principal focus:ring-principal text-sm"
            placeholder="Las dos partes leerán esta explicación."
          ></textarea>
          <div class="flex items-center justify-between mt-1">
            <p v-if="form.errors.notas" class="text-xs font-semibold text-red-600" role="alert">{{ form.errors.notas }}</p>
            <span v-else class="text-[11px] text-gris-medio">Mínimo {{ MIN_NOTAS }} caracteres</span>
            <span class="text-[11px] text-gris-medio">{{ form.notas.trim().length }}/{{ MAX_NOTAS }}</span>
          </div>
        </div>

        <!-- Paso de confirmación: dice qué pasará con el dinero antes de ejecutarlo -->
        <div v-if="confirmando" class="rounded-2xl bg-orange-50 border border-orange-200 p-4 space-y-3" role="alert">
          <p class="text-sm font-bold text-orange-950">{{ consecuencia }}</p>
          <p class="text-xs text-orange-900">Esta decisión no se puede deshacer.</p>
          <div class="flex flex-wrap gap-2">
            <button
              type="button"
              :disabled="form.processing"
              @click="resolver"
              class="px-5 py-2.5 rounded-xl text-sm font-bold bg-principal text-white hover:opacity-90 disabled:opacity-50"
            >
              {{ form.processing ? 'Resolviendo…' : 'Confirmar y resolver' }}
            </button>
            <button type="button" :disabled="form.processing" @click="confirmando = false" class="px-5 py-2.5 rounded-xl text-sm font-bold border-2 border-slate-200 text-principal">
              Volver
            </button>
          </div>
        </div>

        <button
          v-else
          type="button"
          :disabled="!puedeRevisar"
          @click="confirmando = true"
          class="px-5 py-2.5 rounded-xl text-sm font-bold bg-principal text-white hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
        >
          Revisar decisión
        </button>
      </section>

      <section v-else class="bg-white rounded-3xl border-2 border-slate-200/90 p-6 shadow-sm">
        <p class="text-sm text-grafito">
          Este caso lo tiene {{ disputa.mediador || 'otro mediador' }}. Solo esa persona o un superadmin pueden resolverlo.
        </p>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
