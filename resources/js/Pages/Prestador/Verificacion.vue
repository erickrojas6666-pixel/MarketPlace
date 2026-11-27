<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import SubidorFoto from '@/Components/SubidorFoto.vue'

const props = defineProps({
  estado: { type: String, default: 'pendiente' }, // pendiente | en_revision | verificado | rechazado
  motivoRechazo: { type: String, default: null },
  puedeEditar: { type: Boolean, default: false },
  documentos: { type: Array, default: () => [] },
  completo: { type: Boolean, default: false },
  enviadaEn: { type: String, default: null },
  retencionDias: { type: Number, default: 180 },
})

const META = {
  ine_frente: { titulo: 'INE (frente)', ayuda: 'Foto del frente de tu credencial, sin reflejos, con todos los bordes visibles.' },
  ine_reverso: { titulo: 'INE (reverso)', ayuda: 'Foto de la parte de atrás, bien enfocada.' },
  comprobante_domicilio: { titulo: 'Comprobante de domicilio', ayuda: 'Recibo de luz, agua o teléfono reciente (foto o captura de pantalla).' },
}
const meta = (tipo) => META[tipo] || { titulo: tipo, ayuda: '' }

const BANNER = {
  pendiente: { clase: 'bg-amber-50 border-amber-200 text-amber-800', titulo: 'Completa tu verificación', texto: 'Sube los tres documentos y envíalos a revisión para poder publicar servicios.' },
  en_revision: { clase: 'bg-blue-50 border-blue-200 text-blue-800', titulo: 'Tus documentos están en revisión', texto: 'Te avisaremos cuando terminemos. Mientras tanto no necesitas hacer nada.' },
  verificado: { clase: 'bg-green-50 border-green-200 text-green-800', titulo: '¡Estás verificado!', texto: 'Tu perfil muestra la insignia de verificado y ya puedes ofrecer servicios.' },
  rechazado: { clase: 'bg-red-50 border-red-200 text-red-800', titulo: 'Tu solicitud fue rechazada', texto: 'Corrige lo indicado, vuelve a subir los documentos señalados y envíalos de nuevo.' },
}
const banner = computed(() => BANNER[props.estado] || BANNER.pendiente)

const ESTADO_DOC = {
  pendiente: { texto: 'Subido', clase: 'bg-slate-100 text-slate-700 border-slate-200' },
  valido: { texto: 'Válido', clase: 'bg-green-50 text-green-700 border-green-200' },
  rechazado: { texto: 'Rechazado', clase: 'bg-red-50 text-red-700 border-red-200' },
}

const subiendo = ref(null)
const error = ref('')

const subir = (tipo, archivo) => {
  subiendo.value = tipo
  error.value = ''
  router.post(
    route('prestador.verificacion.documentos.store'),
    { tipo, foto: archivo },
    {
      forceFormData: true,
      preserveScroll: true,
      onError: (e) => (error.value = e.foto || e.tipo || 'No se pudo subir el documento.'),
      onFinish: () => (subiendo.value = null),
    },
  )
}

const eliminar = (doc) => {
  if (!confirm('¿Eliminar este documento?')) return
  router.delete(route('prestador.verificacion.documentos.destroy', doc.id), { preserveScroll: true })
}

const aceptaAviso = ref(false)
const enviando = ref(false)
const errorEnvio = ref('')

const enviar = () => {
  enviando.value = true
  errorEnvio.value = ''
  router.post(
    route('prestador.verificacion.enviar'),
    { acepta_aviso: aceptaAviso.value },
    {
      preserveScroll: true,
      onError: (e) => (errorEnvio.value = e.acepta_aviso || 'No se pudo enviar la solicitud.'),
      onFinish: () => (enviando.value = false),
    },
  )
}
</script>

<template>
  <Head title="Verificación de identidad" />

  <AuthenticatedLayout>
    <div class="py-10 max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

      <header class="space-y-1">
        <h1 class="text-2xl font-extrabold text-principal tracking-tight">Verificación de identidad</h1>
        <p class="text-sm text-gris-medio">
          Los clientes te dejan entrar a su casa: verificar quién eres es lo que genera esa confianza.
        </p>
      </header>

      <section class="rounded-2xl border-2 p-4" :class="banner.clase" role="status">
        <p class="font-extrabold">{{ banner.titulo }}</p>
        <p class="text-sm mt-0.5">{{ banner.texto }}</p>
        <p v-if="estado === 'rechazado' && motivoRechazo" class="text-sm mt-2">
          <strong>Motivo:</strong> {{ motivoRechazo }}
        </p>
      </section>

      <p v-if="error" class="px-3 py-2 rounded-xl bg-red-50 border border-red-200 text-xs font-semibold text-red-700" role="alert">{{ error }}</p>

      <!-- ============ DOCUMENTOS ============ -->
      <ul class="space-y-4">
        <li v-for="d in documentos" :key="d.tipo" class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md p-5 sm:p-6">
          <div class="flex flex-col sm:flex-row gap-5">
            <div class="sm:w-48 shrink-0 aspect-[4/3] rounded-2xl bg-niebla border border-slate-200 overflow-hidden flex items-center justify-center">
              <img
                v-if="d.id && !d.purgado"
                :src="route('prestador.verificacion.documentos.ver', d.id)"
                :alt="meta(d.tipo).titulo"
                class="w-full h-full object-contain"
              />
              <span v-else class="text-xs text-gris-medio px-3 text-center">{{ d.purgado ? 'Eliminado por política de retención' : 'Sin subir' }}</span>
            </div>

            <div class="flex-1 space-y-2.5">
              <div class="flex items-center justify-between gap-2">
                <h2 class="font-extrabold text-principal">{{ meta(d.tipo).titulo }}</h2>
                <span v-if="d.estado" class="px-2 py-0.5 rounded-full border text-[10px] font-bold uppercase tracking-wider" :class="ESTADO_DOC[d.estado]?.clase">
                  {{ ESTADO_DOC[d.estado]?.texto }}
                </span>
              </div>
              <p class="text-xs text-gris-medio">{{ meta(d.tipo).ayuda }}</p>
              <p v-if="d.estado === 'rechazado' && d.motivo" class="text-xs font-semibold text-red-700">Motivo: {{ d.motivo }}</p>

              <div v-if="puedeEditar" class="flex flex-wrap gap-2 pt-1">
                <SubidorFoto :etiqueta="d.id ? 'Reemplazar' : 'Tomar foto'" :camara="true" :cargando="subiendo === d.tipo" @seleccionada="(f) => subir(d.tipo, f)" />
                <SubidorFoto etiqueta="Elegir archivo" variante="secundario" :cargando="subiendo === d.tipo" @seleccionada="(f) => subir(d.tipo, f)" />
                <button v-if="d.id" type="button" class="px-3 py-2 text-xs font-bold text-red-600 hover:underline" @click="eliminar(d)">Eliminar</button>
              </div>
            </div>
          </div>
        </li>
      </ul>

      <!-- ============ AVISO DE PRIVACIDAD Y ENVÍO ============ -->
      <section v-if="puedeEditar" class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md p-5 sm:p-6 space-y-4">
        <div class="text-xs text-grafito leading-relaxed space-y-2">
          <p class="font-extrabold text-principal text-sm">Aviso de privacidad</p>
          <p>
            OficiosYa usa tu INE y tu comprobante de domicilio únicamente para verificar tu identidad y dar confianza a los clientes.
            Se guardan <strong>cifrados</strong>, solo los ve personal autorizado y cada consulta queda registrada.
          </p>
          <p>
            Una vez resuelta la solicitud, los archivos se eliminan automáticamente a los {{ retencionDias }} días.
            Puedes solicitar acceso, rectificación, cancelación u oposición (derechos ARCO) a tus datos cuando quieras.
          </p>
        </div>

        <label class="flex items-start gap-2.5 text-sm text-grafito cursor-pointer">
          <input v-model="aceptaAviso" type="checkbox" class="mt-0.5 rounded border-slate-300 text-principal focus:ring-principal" />
          <span>He leído el aviso de privacidad y autorizo el tratamiento de mis documentos para verificar mi identidad.</span>
        </label>

        <p v-if="errorEnvio" class="text-xs font-semibold text-red-600" role="alert">{{ errorEnvio }}</p>

        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            class="px-5 py-2.5 bg-acento hover:bg-yellow-400 text-principal text-sm font-extrabold rounded-xl shadow-md transition active:scale-95 disabled:opacity-50 disabled:pointer-events-none"
            :disabled="!completo || !aceptaAviso || enviando"
            @click="enviar"
          >
            {{ enviando ? 'Enviando…' : 'Enviar a revisión' }}
          </button>
          <span v-if="!completo" class="text-xs text-gris-medio">Sube los tres documentos para poder enviar.</span>
        </div>
      </section>

      <p v-else-if="estado === 'en_revision' && enviadaEn" class="text-xs text-gris-medio">
        Enviado el {{ new Date(enviadaEn).toLocaleDateString('es-MX') }}. No se pueden cambiar documentos durante la revisión.
      </p>
    </div>
  </AuthenticatedLayout>
</template>
