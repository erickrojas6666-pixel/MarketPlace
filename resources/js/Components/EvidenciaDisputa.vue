<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'

/**
 * Evidencia de un pedido en disputa, en dos columnas (cliente | prestador), para que el
 * mediador compare las dos versiones. Usa el mismo endpoint autorizado que el resto de la
 * evidencia (evidencias.index): las imágenes siguen siendo privadas, con URL firmada de
 * pocos minutos, y cada consulta del mediador queda en la auditoría.
 */
const props = defineProps({
  pedidoId: { type: Number, required: true },
  cliente: { type: String, default: 'Cliente' },
  prestador: { type: String, default: 'Prestador' },
})

const FASES = { antes: 'Antes', durante: 'Durante', despues: 'Después' }

const cargando = ref(true)
const error = ref('')
const fotos = ref([])
const ampliada = ref(null)

const delCliente = computed(() => fotos.value.filter((f) => f.autor === 'cliente'))
const delPrestador = computed(() => fotos.value.filter((f) => f.autor === 'prestador'))

const columnas = computed(() => [
  { clave: 'cliente', titulo: props.cliente, rol: 'Cliente', fotos: delCliente.value },
  { clave: 'prestador', titulo: props.prestador, rol: 'Prestador', fotos: delPrestador.value },
])

const cargar = async () => {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await axios.get(route('evidencias.index', props.pedidoId))
    fotos.value = data.fotos ?? []
  } catch (e) {
    error.value = !e.response
      ? 'Sin conexión: las fotos necesitan internet para verse.'
      : e.response.status === 403
        ? 'No tienes acceso a la evidencia de este pedido.'
        : 'No pudimos cargar las fotos.'
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <div class="space-y-3">
    <p v-if="error" class="px-3 py-2 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-700" role="alert">{{ error }}</p>
    <p v-else-if="cargando" class="text-sm text-gris-medio">Cargando evidencia…</p>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <section
        v-for="col in columnas"
        :key="col.clave"
        class="rounded-2xl border-2 border-slate-200 bg-white p-4 space-y-3"
        :aria-label="`Evidencia de ${col.rol}`"
      >
        <header class="flex items-center justify-between gap-2">
          <div class="min-w-0">
            <p class="text-[11px] font-bold uppercase tracking-wider text-gris-medio">{{ col.rol }}</p>
            <p class="text-sm font-extrabold text-principal truncate">{{ col.titulo }}</p>
          </div>
          <span class="shrink-0 min-w-7 h-7 px-2 rounded-full bg-niebla text-principal text-xs font-extrabold flex items-center justify-center border border-principal/15">
            {{ col.fotos.length }}
          </span>
        </header>

        <p v-if="!col.fotos.length" class="text-xs text-gris-medio py-6 text-center rounded-xl bg-niebla/40 border border-dashed border-slate-300">
          Esta parte no aportó fotos.
        </p>

        <ul v-else class="grid grid-cols-2 gap-2">
          <li v-for="foto in col.fotos" :key="foto.id" class="rounded-xl border border-slate-200 overflow-hidden bg-white">
            <button type="button" class="relative block w-full aspect-square bg-niebla" @click="ampliada = foto">
              <img :src="foto.url_miniatura" :alt="foto.pie || FASES[foto.fase] || foto.fase" loading="lazy" class="w-full h-full object-cover" />
              <span class="absolute top-1.5 left-1.5 px-2 py-0.5 rounded-full bg-principal/90 text-white text-[10px] font-bold uppercase tracking-wider">
                {{ FASES[foto.fase] || foto.fase }}
              </span>
            </button>
            <p v-if="foto.pie" class="px-2 py-1.5 text-[11px] text-grafito leading-snug">{{ foto.pie }}</p>
          </li>
        </ul>
      </section>
    </div>

    <div
      v-if="ampliada"
      class="fixed inset-0 z-[100] bg-black/90 flex flex-col items-center justify-center gap-3 p-4"
      role="dialog"
      aria-modal="true"
      @click.self="ampliada = null"
    >
      <button type="button" class="absolute top-4 right-4 p-2 text-white/80 hover:text-white" aria-label="Cerrar" @click="ampliada = null">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
      <img :src="ampliada.url" :alt="ampliada.pie || FASES[ampliada.fase] || ampliada.fase" class="max-h-[80vh] max-w-full rounded-xl object-contain" />
      <p class="text-xs text-white/80">
        {{ ampliada.autor === 'cliente' ? 'Cliente' : 'Prestador' }} · {{ FASES[ampliada.fase] || ampliada.fase }}<template v-if="ampliada.pie"> · {{ ampliada.pie }}</template>
      </p>
    </div>
  </div>
</template>
