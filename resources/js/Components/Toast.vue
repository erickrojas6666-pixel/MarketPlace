<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const visible = ref(false)
const mensaje = ref('')
const tipo = ref('success') // 'success' o 'error'

const page = usePage()

watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) {
      mensaje.value = flash.success
      tipo.value = 'success'
      mostrarToast()
    } else if (flash?.error) {
      mensaje.value = flash.error
      tipo.value = 'error'
      mostrarToast()
    }
  },
  { deep: true, immediate: true }
)

const mostrarToast = () => {
  visible.value = true
  setTimeout(() => {
    visible.value = false
  }, 4000) // Se oculta automáticamente a los 4 segundos
}
</script>

<template>
  <Transition
    enter-active-class="transform ease-out duration-300 transition"
    enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
    enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
    leave-active-class="transition ease-in duration-100"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="visible"
      class="fixed bottom-5 right-5 z-50 flex items-center p-4 max-w-xs w-full text-white rounded-lg shadow-lg"
      :class="tipo === 'success' ? 'bg-green-600' : 'bg-red-600'"
      role="alert"
    >
      <div class="inline-flex items-center justify-center flex-shrink-0 w-8 h-8 rounded-lg bg-white/20 text-white">
        <span v-if="tipo === 'success'">✓</span>
        <span v-else>✕</span>
      </div>
      <div class="ml-3 text-sm font-medium pr-2">
        {{ mensaje }}
      </div>
      <button
        type="button"
        @click="visible = false"
        class="ml-auto -mx-1.5 -my-1.5 bg-transparent text-white hover:bg-white/20 rounded-lg p-1.5 inline-flex h-8 w-8"
      >
        ✕
      </button>
    </div>
  </Transition>
</template>