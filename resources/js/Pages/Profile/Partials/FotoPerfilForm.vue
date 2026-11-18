<script setup>
import { computed, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AvatarUsuario from '@/Components/AvatarUsuario.vue'
import SubidorFoto from '@/Components/SubidorFoto.vue'

const page = usePage()
const usuario = computed(() => page.props.auth?.user)

const subiendo = ref(false)
const error = ref('')

const subir = (archivo) => {
  subiendo.value = true
  error.value = ''
  router.post(
    route('perfil.foto.store'),
    { foto: archivo },
    {
      forceFormData: true,
      preserveScroll: true,
      onError: (e) => (error.value = e.foto || 'No se pudo subir la foto.'),
      onFinish: () => (subiendo.value = false),
    },
  )
}

const quitar = () => {
  if (!confirm('¿Quitar tu foto de perfil?')) return
  router.delete(route('perfil.foto.destroy'), { preserveScroll: true })
}
</script>

<template>
  <section class="flex flex-col sm:flex-row sm:items-center gap-5">
    <AvatarUsuario :nombre="usuario?.nombre" :url="usuario?.fotoUrl" clase="w-24 h-24 text-3xl" />

    <div class="space-y-3">
      <div>
        <h2 class="text-base font-extrabold text-principal">Foto de perfil</h2>
        <p class="text-xs text-gris-medio mt-0.5">
          Una foto clara de tu rostro genera confianza. JPG, PNG o WebP.
        </p>
      </div>

      <div class="flex flex-wrap gap-2">
        <SubidorFoto :etiqueta="usuario?.fotoUrl ? 'Cambiar foto' : 'Subir foto'" :cargando="subiendo" @seleccionada="subir" />
        <button
          v-if="usuario?.fotoUrl"
          type="button"
          class="px-4 py-2.5 text-xs font-bold rounded-xl bg-white text-red-600 border-2 border-red-200 hover:border-red-300 transition active:scale-95"
          @click="quitar"
        >
          Quitar
        </button>
      </div>

      <p v-if="error" class="text-xs font-semibold text-red-600" role="alert">{{ error }}</p>
    </div>
  </section>
</template>
