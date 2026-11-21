<script setup>
import { ref, watch } from 'vue'
import { celebrando } from '../composables/useInstalacion'

// Aviso verde (esquina inferior derecha): dura un poco más que el mensaje grande.
const avisoVisible = ref(false)
let temporizadorAviso = null

watch(celebrando, (activa) => {
  if (!activa) return
  avisoVisible.value = true
  clearTimeout(temporizadorAviso)
  temporizadorAviso = setTimeout(() => (avisoVisible.value = false), 5500)
})

const cerrar = () => (celebrando.value = false)
</script>

<template>
  <!-- Fondo difuminado + mensaje grande -->
  <Transition
    enter-active-class="transition-opacity duration-300"
    enter-from-class="opacity-0"
    leave-active-class="transition-opacity duration-500"
    leave-to-class="opacity-0"
  >
    <div
      v-if="celebrando"
      class="fixed inset-0 z-[90] flex cursor-pointer items-center justify-center bg-principal/30 p-6 backdrop-blur-md"
      role="status"
      aria-live="polite"
      @click="cerrar"
    >
      <div class="mensaje-pop text-center">
        <div class="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full bg-green-500 text-white shadow-2xl ring-8 ring-white/20">
          <svg class="h-12 w-12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
          </svg>
        </div>
        <h2 class="text-4xl font-extrabold tracking-tight text-white drop-shadow-lg sm:text-6xl">
          ¡Gracias por instalarnos!
        </h2>
        <p class="mt-3 text-base text-white drop-shadow sm:text-lg">
          OficiosYa ya está lista en tu dispositivo.
        </p>
      </div>
    </div>
  </Transition>

  <!-- Aviso verde, igual a las confirmaciones del sistema -->
  <Transition
    enter-active-class="transition-all duration-500 ease-out"
    enter-from-class="opacity-0 translate-y-6 translate-x-6"
    leave-active-class="transition-all duration-300 ease-in"
    leave-to-class="opacity-0 translate-y-4"
  >
    <div
      v-if="avisoVisible"
      class="fixed bottom-4 right-4 z-[95] flex max-w-sm items-center gap-3 rounded-lg bg-green-600 px-4 py-3 text-sm font-semibold text-white shadow-lg"
      role="status"
    >
      <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
      </svg>
      ¡Listo! OficiosYa se instaló correctamente.
    </div>
  </Transition>
</template>

<style scoped>
.mensaje-pop {
  animation: aparecer 0.5s cubic-bezier(0.2, 0.9, 0.3, 1.2) both;
}

@keyframes aparecer {
  0% {
    opacity: 0;
    transform: scale(0.7);
  }
  60% {
    opacity: 1;
    transform: scale(1.05);
  }
  100% {
    opacity: 1;
    transform: scale(1);
  }
}

@media (prefers-reduced-motion: reduce) {
  .mensaje-pop {
    animation: none;
  }
}
</style>