<script setup>
import { computed, ref, watch } from 'vue'

/** Foto de perfil o, si no hay (o falla la carga), las iniciales del nombre. */
const props = defineProps({
  nombre: { type: String, default: '' },
  url: { type: String, default: null },
  clase: { type: String, default: 'w-9 h-9 text-xs' }, // tamaño y tipografía
})

const fallo = ref(false)
watch(() => props.url, () => (fallo.value = false))

const iniciales = computed(() => {
  const partes = String(props.nombre || '?').trim().split(/\s+/)
  return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?'
})
</script>

<template>
  <span
    class="shrink-0 rounded-xl overflow-hidden bg-gradient-to-br from-principal to-[#1d4a8a] text-white font-extrabold flex items-center justify-center shadow-sm"
    :class="clase"
  >
    <img
      v-if="url && !fallo"
      :src="url"
      :alt="`Foto de ${nombre || 'usuario'}`"
      loading="lazy"
      class="w-full h-full object-cover"
      @error="fallo = true"
    />
    <span v-else aria-hidden="true">{{ iniciales }}</span>
  </span>
</template>
