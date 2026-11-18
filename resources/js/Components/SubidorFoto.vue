<script setup>
import { ref } from 'vue'
import { comprimirImagen } from '@/composables/useImagenes'

/**
 * Botón para elegir (o tomar) una foto. Comprime en el navegador y emite
 * `seleccionada` con un File listo para subir. NO sube nada por sí mismo.
 */
const props = defineProps({
  etiqueta: { type: String, default: 'Elegir foto' },
  camara: { type: Boolean, default: false }, // abre directamente la cámara trasera en el celular
  deshabilitado: { type: Boolean, default: false },
  cargando: { type: Boolean, default: false },
  variante: { type: String, default: 'primario' }, // primario | secundario
})

const emit = defineEmits(['seleccionada'])

const error = ref('')
const procesando = ref(false)

const alElegir = async (evento) => {
  const archivo = evento.target.files?.[0]
  evento.target.value = '' // permite elegir de nuevo la misma foto
  if (!archivo) return

  error.value = ''
  procesando.value = true
  try {
    emit('seleccionada', await comprimirImagen(archivo))
  } catch (e) {
    error.value = e?.message || 'No pudimos preparar la foto.'
  } finally {
    procesando.value = false
  }
}
</script>

<template>
  <div>
    <label
      class="relative inline-flex items-center gap-2 px-4 py-2.5 text-xs font-bold rounded-xl shadow-sm transition-all duration-200 cursor-pointer focus-within:ring-2 focus-within:ring-principal focus-within:ring-offset-2"
      :class="[
        variante === 'primario'
          ? 'bg-principal text-white hover:bg-slate-800'
          : 'bg-white text-principal border-2 border-slate-200 hover:border-principal/40',
        (deshabilitado || cargando || procesando) ? 'opacity-50 pointer-events-none' : 'active:scale-95',
      ]"
    >
      <input
        type="file"
        accept="image/jpeg,image/png,image/webp"
        :capture="camara ? 'environment' : undefined"
        class="sr-only"
        :disabled="deshabilitado || cargando || procesando"
        @change="alElegir"
      />
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <span>{{ procesando ? 'Preparando…' : cargando ? 'Subiendo…' : etiqueta }}</span>
    </label>
    <p v-if="error" class="mt-1.5 text-xs font-semibold text-red-600" role="alert">{{ error }}</p>
  </div>
</template>
