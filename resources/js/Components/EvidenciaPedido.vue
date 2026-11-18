<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'
import Modal from '@/Components/Modal.vue'
import SubidorFoto from '@/Components/SubidorFoto.vue'

/**
 * Evidencia fotográfica de un pedido (antes / durante / después). Es PRIVADA: solo la ven
 * el cliente y el prestador del pedido (y el mediador si hay disputa). Las imágenes se
 * piden a /evidencias/{id}, que autoriza y redirige a una URL firmada de pocos minutos.
 */
const props = defineProps({
  pedidoId: { type: Number, required: true },
  etiqueta: { type: String, default: 'Fotos del trabajo' },
})

const FASES = [
  { valor: 'antes', texto: 'Antes' },
  { valor: 'durante', texto: 'Durante' },
  { valor: 'despues', texto: 'Después' },
]
const textoFase = (v) => FASES.find((f) => f.valor === v)?.texto ?? v

const abierto = ref(false)
const cargando = ref(false)
const subiendo = ref(false)
const error = ref('')
const datos = ref({ puede_subir: false, maximo: 12, fotos: [] })
const fase = ref('despues')
const pie = ref('')
const ampliada = ref(null)

const total = computed(() => datos.value.fotos.length)

const mensajeError = (e, porDefecto) => {
  if (!e.response) return 'Sin conexión: las fotos necesitan internet para subirse y verse.'
  return e.response.data?.errors?.foto?.[0] || e.response.data?.message || porDefecto
}

const cargar = async () => {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await axios.get(route('evidencias.index', props.pedidoId))
    datos.value = data
  } catch (e) {
    error.value = mensajeError(e, 'No pudimos cargar las fotos.')
  } finally {
    cargando.value = false
  }
}

const abrir = () => {
  abierto.value = true
  cargar()
}

const subir = async (archivo) => {
  subiendo.value = true
  error.value = ''
  const cuerpo = new FormData()
  cuerpo.append('foto', archivo)
  cuerpo.append('fase', fase.value)
  if (pie.value) cuerpo.append('pie', pie.value)

  try {
    await axios.post(route('evidencias.store', props.pedidoId), cuerpo)
    pie.value = ''
    await cargar()
  } catch (e) {
    error.value = mensajeError(e, 'No se pudo subir la foto.')
  } finally {
    subiendo.value = false
  }
}

const eliminar = async (foto) => {
  if (!confirm('¿Eliminar esta foto de evidencia?')) return
  try {
    await axios.delete(route('evidencias.destroy', foto.id))
    await cargar()
  } catch (e) {
    error.value = mensajeError(e, 'No se pudo eliminar la foto.')
  }
}
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-niebla text-principal text-xs font-bold rounded-xl border-2 border-slate-200 hover:border-principal/40 transition active:scale-95"
    @click="abrir"
  >
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
    </svg>
    {{ etiqueta }}
  </button>

  <Modal :show="abierto" max-width="2xl" @close="abierto = false">
    <div class="p-6 space-y-5">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-lg font-extrabold text-principal">Evidencia del pedido #{{ pedidoId }}</h2>
          <p class="text-xs text-gris-medio mt-0.5">
            Privadas: solo las ven el cliente, el prestador y, si hay disputa, el mediador.
          </p>
        </div>
        <button type="button" class="p-1 text-gris-medio hover:text-principal" aria-label="Cerrar" @click="abierto = false">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>
      </div>

      <p v-if="error" class="px-3 py-2 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-700" role="alert">{{ error }}</p>

      <p v-if="cargando && !total" class="text-sm text-gris-medio">Cargando fotos…</p>
      <p v-else-if="!total" class="text-sm text-gris-medio">Todavía no hay fotos en este pedido.</p>

      <ul v-else class="grid grid-cols-2 sm:grid-cols-3 gap-3">
        <li v-for="foto in datos.fotos" :key="foto.id" class="rounded-2xl border border-slate-200 overflow-hidden bg-white">
          <button type="button" class="relative block w-full aspect-square bg-niebla" @click="ampliada = foto">
            <img :src="foto.url_miniatura" :alt="foto.pie || textoFase(foto.fase)" loading="lazy" class="w-full h-full object-cover" />
            <span class="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-principal/90 text-white text-[10px] font-bold uppercase tracking-wider">
              {{ textoFase(foto.fase) }}
            </span>
          </button>
          <div class="p-2 flex items-center justify-between gap-2">
            <span class="text-[11px] text-gris-medio truncate">
              {{ foto.autor === 'cliente' ? 'Cliente' : 'Prestador' }}<template v-if="foto.pie"> · {{ foto.pie }}</template>
            </span>
            <button v-if="foto.puede_eliminar" type="button" class="shrink-0 text-[11px] font-bold text-red-600 hover:underline" @click="eliminar(foto)">
              Eliminar
            </button>
          </div>
        </li>
      </ul>

      <div v-if="datos.puede_subir" class="pt-4 border-t border-slate-200 space-y-3">
        <p class="text-xs font-bold uppercase tracking-wider text-principal">
          Agregar foto <span class="font-semibold text-gris-medio normal-case tracking-normal">(máx. {{ datos.maximo }} por persona)</span>
        </p>

        <div class="flex flex-wrap gap-2" role="radiogroup" aria-label="Momento del trabajo">
          <button
            v-for="f in FASES"
            :key="f.valor"
            type="button"
            role="radio"
            :aria-checked="fase === f.valor"
            class="px-3 py-1.5 rounded-full text-xs font-bold border-2 transition"
            :class="fase === f.valor ? 'bg-principal text-white border-principal' : 'bg-white text-principal border-slate-200 hover:border-principal/40'"
            @click="fase = f.valor"
          >
            {{ f.texto }}
          </button>
        </div>

        <input
          v-model="pie"
          type="text"
          maxlength="160"
          placeholder="Descripción breve (opcional)"
          class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm py-2 px-3"
        />

        <div class="flex flex-wrap gap-2">
          <SubidorFoto etiqueta="Tomar foto" :camara="true" :cargando="subiendo" @seleccionada="subir" />
          <SubidorFoto etiqueta="Elegir de la galería" variante="secundario" :cargando="subiendo" @seleccionada="subir" />
        </div>
      </div>
    </div>

    <div
      v-if="ampliada"
      class="fixed inset-0 z-[100] bg-black/90 flex items-center justify-center p-4"
      role="dialog"
      aria-modal="true"
      @click.self="ampliada = null"
    >
      <button type="button" class="absolute top-4 right-4 p-2 text-white/80 hover:text-white" aria-label="Cerrar" @click="ampliada = null">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
      </button>
      <img :src="ampliada.url" :alt="ampliada.pie || textoFase(ampliada.fase)" class="max-h-[85vh] max-w-full rounded-xl object-contain" />
    </div>
  </Modal>
</template>
