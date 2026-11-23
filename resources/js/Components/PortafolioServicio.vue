<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import SubidorFoto from '@/Components/SubidorFoto.vue'

/**
 * Portafolio de un servicio: el prestador sube, ordena y elimina fotos de trabajos realizados.
 * La primera foto es la portada que ven los clientes en el catálogo.
 */
const props = defineProps({
  servicio: { type: Object, required: true }, // { id, titulo, fotos: [...] }
  max: { type: Number, default: 8 },
})

const fotos = computed(() => props.servicio.fotos ?? [])
const lleno = computed(() => fotos.value.length >= props.max)

const subiendo = ref(false)
const error = ref('')
const pieNuevo = ref('')

const subir = (archivo) => {
  subiendo.value = true
  error.value = ''
  router.post(
    route('servicios.fotos.store', props.servicio.id),
    { foto: archivo, pie: pieNuevo.value || null },
    {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => (pieNuevo.value = ''),
      onError: (e) => (error.value = e.foto || e.pie || 'No se pudo subir la foto.'),
      onFinish: () => (subiendo.value = false),
    },
  )
}

const guardarPie = (foto, valor) => {
  if ((valor || '') === (foto.pie || '')) return
  router.patch(route('fotos-trabajo.update', foto.id), { pie: valor }, { preserveScroll: true })
}

const hacerPortada = (foto) =>
  router.patch(route('fotos-trabajo.update', foto.id), { portada: true }, { preserveScroll: true })

const eliminar = (foto) => {
  if (!confirm('¿Eliminar esta foto de tu portafolio?')) return
  router.delete(route('fotos-trabajo.destroy', foto.id), { preserveScroll: true })
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between gap-3">
      <p class="text-xs font-bold uppercase tracking-wider text-principal">
        Fotos de trabajos realizados
        <span class="ms-1 font-semibold text-gris-medio normal-case tracking-normal">({{ fotos.length }}/{{ max }})</span>
      </p>
    </div>

    <p v-if="!fotos.length" class="text-xs text-gris-medio">
      Los servicios con fotos reales reciben más solicitudes. Muestra trabajos terminados (sin personas ni datos del cliente).
    </p>

    <ul v-else class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
      <li v-for="(foto, i) in fotos" :key="foto.id" class="bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col">
        <div class="relative aspect-[4/3] bg-niebla">
          <img :src="foto.url_miniatura" :alt="foto.pie || 'Trabajo realizado'" loading="lazy" class="w-full h-full object-cover" />
          <span v-if="i === 0" class="absolute top-2 left-2 px-2 py-0.5 rounded-full bg-acento text-principal text-[10px] font-extrabold uppercase tracking-wider shadow">
            Portada
          </span>
        </div>
        <div class="p-2.5 space-y-2">
          <input
            type="text"
            maxlength="160"
            :value="foto.pie"
            placeholder="Pie de foto (opcional)"
            class="block w-full rounded-lg border-slate-300 focus:border-principal focus:ring-principal text-xs py-1.5 px-2"
            @change="guardarPie(foto, $event.target.value)"
          />
          <div class="flex items-center justify-between gap-2">
            <button v-if="i !== 0" type="button" class="text-[11px] font-bold text-principal hover:underline" @click="hacerPortada(foto)">
              Usar de portada
            </button>
            <span v-else></span>
            <button type="button" class="text-[11px] font-bold text-red-600 hover:underline" @click="eliminar(foto)">Eliminar</button>
          </div>
        </div>
      </li>
    </ul>

    <div v-if="!lleno" class="flex flex-wrap items-end gap-3">
      <input
        v-model="pieNuevo"
        type="text"
        maxlength="160"
        placeholder="Pie de la nueva foto (opcional)"
        class="flex-1 min-w-[12rem] rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm py-2 px-3"
      />
      <SubidorFoto etiqueta="Agregar foto" :cargando="subiendo" @seleccionada="subir" />
    </div>
    <p v-else class="text-xs font-semibold text-amber-700">Llegaste al máximo de fotos. Elimina alguna para subir otra.</p>

    <p v-if="error" class="text-xs font-semibold text-red-600" role="alert">{{ error }}</p>
  </div>
</template>
