<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'

/** Cuadrícula de fotos con visor a pantalla completa (teclado: ← → Esc). */
const props = defineProps({
  fotos: { type: Array, default: () => [] }, // [{ url, url_miniatura, pie, servicio }]
})

const indice = ref(null)
const actual = computed(() => (indice.value === null ? null : props.fotos[indice.value]))

const abrir = (i) => (indice.value = i)
const cerrar = () => (indice.value = null)
const mover = (delta) => {
  if (indice.value === null) return
  indice.value = (indice.value + delta + props.fotos.length) % props.fotos.length
}

const teclado = (e) => {
  if (indice.value === null) return
  if (e.key === 'Escape') cerrar()
  if (e.key === 'ArrowRight') mover(1)
  if (e.key === 'ArrowLeft') mover(-1)
}
onMounted(() => window.addEventListener('keydown', teclado))
onUnmounted(() => window.removeEventListener('keydown', teclado))
</script>

<template>
  <div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
      <button
        v-for="(foto, i) in fotos"
        :key="foto.id ?? i"
        type="button"
        class="group relative aspect-square overflow-hidden rounded-2xl bg-niebla border border-slate-200 focus:outline-none focus:ring-2 focus:ring-principal"
        :aria-label="foto.pie ? `Ampliar: ${foto.pie}` : 'Ampliar foto'"
        @click="abrir(i)"
      >
        <img
          :src="foto.url_miniatura || foto.url"
          :alt="foto.pie || foto.servicio || 'Trabajo realizado'"
          loading="lazy"
          class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
        <span
          v-if="foto.pie"
          class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent px-2.5 pt-6 pb-2 text-left text-[11px] font-semibold text-white line-clamp-2"
        >
          {{ foto.pie }}
        </span>
      </button>
    </div>

    <Teleport to="body">
      <div
        v-if="actual"
        class="fixed inset-0 z-[100] bg-black/90 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        @click.self="cerrar"
      >
        <button type="button" class="absolute top-4 right-4 p-2 text-white/80 hover:text-white" aria-label="Cerrar" @click="cerrar">
          <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
        </button>

        <button v-if="fotos.length > 1" type="button" class="absolute left-3 p-3 text-white/80 hover:text-white" aria-label="Anterior" @click="mover(-1)">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
        </button>

        <figure class="max-w-4xl max-h-full flex flex-col items-center gap-3">
          <img :src="actual.url" :alt="actual.pie || 'Trabajo realizado'" class="max-h-[80vh] max-w-full rounded-xl object-contain" />
          <figcaption v-if="actual.pie || actual.servicio" class="text-center text-sm text-white/90">
            <span v-if="actual.pie" class="font-semibold">{{ actual.pie }}</span>
            <span v-if="actual.servicio" class="block text-xs text-white/60">{{ actual.servicio }}</span>
          </figcaption>
        </figure>

        <button v-if="fotos.length > 1" type="button" class="absolute right-3 p-3 text-white/80 hover:text-white" aria-label="Siguiente" @click="mover(1)">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg>
        </button>
      </div>
    </Teleport>
  </div>
</template>
