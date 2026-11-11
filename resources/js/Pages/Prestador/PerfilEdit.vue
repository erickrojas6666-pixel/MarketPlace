<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import BuscadorUbicacion from '@/Components/BuscadorUbicacion.vue'
import MapaUbicacion from '@/Components/MapaUbicacion.vue'
import { useGeolocalizacion, redondear } from '@/composables/useGeolocalizacion'

const props = defineProps({
  perfil: Object,
  categorias: { type: Array, default: () => [] },
  serviciosPropios: { type: Array, default: () => [] },
})

// Formulario del perfil
const formPerfil = useForm({
  descripcion: props.perfil?.descripcion || '',
  ubicacion_aproximada: props.perfil?.ubicacion_aproximada || '',
  latitud: props.perfil?.latitud ?? null,
  longitud: props.perfil?.longitud ?? null,
  radio_cobertura_km: props.perfil?.radio_cobertura_km ?? 10,
})

// --- Ubicación del prestador (para que los clientes lo encuentren por cercanía) ---
const { cargando: ubicando, error: errorUbicacion, obtener } = useGeolocalizacion()

const tieneUbicacion = computed(() => formPerfil.latitud !== null && formPerfil.longitud !== null)

const fijarCoordenadas = (lat, lng) => {
  formPerfil.latitud = redondear(lat)
  formPerfil.longitud = redondear(lng)
}

const usarMiUbicacion = async () => {
  const pos = await obtener()
  if (pos) fijarCoordenadas(pos.lat, pos.lng)
}

const usarLugar = (lugar) => {
  fijarCoordenadas(parseFloat(lugar.lat), parseFloat(lugar.lon))
  if (!formPerfil.ubicacion_aproximada) {
    formPerfil.ubicacion_aproximada = String(lugar.display_name || '').slice(0, 255)
  }
}

const quitarUbicacion = () => {
  formPerfil.latitud = null
  formPerfil.longitud = null
}

// Formulario para publicar servicio
const formServicio = useForm({
  titulo: '',
  categoria_id: '',
  descripcion: '',
  precio: '',
})

const guardarPerfil = () => {
  formPerfil.post(route('prestador.perfil.update'), { preserveScroll: true })
}

const publicarServicio = () => {
  formServicio.post(route('servicios.store'), {
    preserveScroll: true,
    onSuccess: () => formServicio.reset(),
  })
}

const cambiarEstadoServicio = (servId) => {
  router.patch(route('servicios.toggle', servId), {}, { preserveScroll: true })
}

/* ---------- Ayudas visuales ---------- */
const estado = computed(() => props.perfil?.estado_verificacion)
const estaVerificado = computed(() => ['verificado', 'aprobado'].includes(estado.value))

const estiloEstado = computed(() => {
  if (estaVerificado.value) {
    return {
      caja: 'bg-green-50 border-green-200 text-green-800',
      icono: 'bg-green-100 text-green-600',
      badge: 'bg-green-50 text-green-700 border-green-200',
      punto: 'bg-green-500',
    }
  }
  if (estado.value === 'rechazado') {
    return {
      caja: 'bg-red-50 border-red-200 text-red-800',
      icono: 'bg-red-100 text-red-600',
      badge: 'bg-red-50 text-red-700 border-red-200',
      punto: 'bg-red-500',
    }
  }
  return {
    caja: 'bg-amber-50 border-amber-200 text-amber-800',
    icono: 'bg-amber-100 text-amber-600',
    badge: 'bg-amber-50 text-amber-700 border-amber-200',
    punto: 'bg-acento',
  }
})

const claseInput =
  'block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400'
const claseLabel = 'block text-principal font-bold text-xs uppercase tracking-wider mb-1.5'

const iconos = {
  maletin: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
  pin: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
  mas: 'M12 4v16m8-8H4',
  check: 'M5 13l4 4L19 7',
  reloj: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
  alerta: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
  cerrar: 'M6 18L18 6M6 6l12 12',
}
</script>

<template>
  <Head title="Mi Perfil y Servicios" />

  <AuthenticatedLayout>
    <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

      <!-- ============ BANNER ============ -->
      <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] p-6 sm:p-8 shadow-xl shadow-principal/20">
        <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-acento/20 blur-2xl"></div>
        <div class="absolute -bottom-24 left-1/3 w-72 h-72 rounded-full bg-white/5"></div>

        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 shrink-0 rounded-2xl bg-white/10 border border-white/15 text-acento flex items-center justify-center">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.maletin" />
              </svg>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Gestión de prestador</h1>
              <p class="text-sm text-niebla/80 mt-1">Completa tu perfil profesional y publica tus servicios.</p>
            </div>
          </div>

          <span
            v-if="estado"
            class="inline-flex items-center gap-2 self-start md:self-auto px-4 py-2 rounded-full text-[11px] font-bold uppercase tracking-wider border"
            :class="estiloEstado.badge"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="estiloEstado.punto"></span>
            Perfil {{ estado }}
          </span>
        </div>
      </section>

      <!-- ============ ESTADO DE VERIFICACIÓN ============ -->
      <div
        v-if="estado"
        class="p-4 rounded-2xl border-2 flex items-start gap-3"
        :class="estiloEstado.caja"
      >
        <div class="w-10 h-10 shrink-0 rounded-xl flex items-center justify-center" :class="estiloEstado.icono">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              :d="estaVerificado ? iconos.check : (estado === 'rechazado' ? iconos.cerrar : iconos.reloj)"
            />
          </svg>
        </div>
        <div>
          <p class="font-extrabold text-sm uppercase tracking-wider">Estado de verificación: {{ estado }}</p>
          <p v-if="estado === 'pendiente'" class="text-sm mt-0.5">
            Tu perfil está en revisión. Podrás ofrecer servicios cuando el administrador lo apruebe.
          </p>
          <p v-else-if="estaVerificado" class="text-sm mt-0.5">
            ¡Tu perfil está verificado! Tus servicios están visibles en el catálogo público.
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">

        <!-- ============ INFORMACIÓN PROFESIONAL ============ -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border-2 border-slate-200/90 shadow-md space-y-6">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 shrink-0 rounded-xl bg-niebla text-principal flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.maletin" />
              </svg>
            </div>
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Información profesional</h2>
              <p class="text-xs text-gris-medio">Lo que ven los clientes de ti</p>
            </div>
          </div>

          <form @submit.prevent="guardarPerfil" class="space-y-5">
            <div>
              <label :class="claseLabel">Descripción / Bio</label>
              <textarea
                v-model="formPerfil.descripcion"
                rows="4"
                required
                placeholder="Describe tu experiencia..."
                :class="claseInput"
              ></textarea>
            </div>

            <div>
              <label :class="claseLabel">Ubicación aproximada</label>
              <input
                v-model="formPerfil.ubicacion_aproximada"
                type="text"
                placeholder="Ej: Godoy Cruz, Mendoza"
                :class="claseInput"
              />
            </div>

            <!-- UBICACIÓN PARA BÚSQUEDA POR CERCANÍA -->
            <div class="space-y-3 rounded-2xl border border-slate-200 bg-niebla/40 p-4">
              <div>
                <p class="text-principal font-bold text-xs uppercase tracking-wider">Mi ubicación de trabajo</p>
                <p class="text-xs text-gris-medio mt-1 leading-relaxed">
                  Los clientes cercanos te encontrarán primero. Guardamos solo una posición aproximada
                  (unos 110 m) y nunca mostramos tus coordenadas, solo la distancia.
                </p>
              </div>

              <div class="flex flex-wrap items-center gap-2">
                <button
                  type="button"
                  @click="usarMiUbicacion"
                  :disabled="ubicando"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-principal hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-md transition-all duration-200 active:scale-95"
                  :class="{ 'opacity-50 cursor-not-allowed': ubicando }"
                >
                  <svg class="w-4 h-4 text-acento" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
                  </svg>
                  {{ ubicando ? 'Obteniendo...' : 'Usar mi ubicación' }}
                </button>

                <button
                  v-if="tieneUbicacion"
                  type="button"
                  @click="quitarUbicacion"
                  class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-red-50 text-red-600 text-xs font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition active:scale-95"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
                  </svg>
                  Quitar ubicación
                </button>
              </div>

              <p v-if="errorUbicacion" class="text-xs font-semibold text-red-600 bg-red-50 border border-red-200 rounded-xl px-3 py-2">
                {{ errorUbicacion }}
              </p>

              <p
                v-if="tieneUbicacion"
                class="text-xs font-semibold text-green-700 bg-green-50 border border-green-200 rounded-xl px-3 py-2 flex items-center gap-2"
              >
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
                </svg>
                Ubicación lista ({{ formPerfil.latitud }}, {{ formPerfil.longitud }}). Pulsa «Guardar datos del perfil» para confirmarla.
              </p>
              <p v-else class="text-xs text-gris-medio">Aún no has definido tu ubicación.</p>

              <!-- Mapa (OpenStreetMap): permite ajustar la ubicación a mano -->
              <div class="relative z-0 rounded-2xl overflow-hidden border border-slate-200">
                <MapaUbicacion
                  editable
                  :lat="tieneUbicacion ? Number(formPerfil.latitud) : null"
                  :lng="tieneUbicacion ? Number(formPerfil.longitud) : null"
                  :radio-km="tieneUbicacion && formPerfil.radio_cobertura_km ? Number(formPerfil.radio_cobertura_km) : null"
                  @cambiar="({ lat, lng }) => fijarCoordenadas(lat, lng)"
                />
              </div>

              <div v-if="tieneUbicacion">
                <label :class="claseLabel">
                  Radio de cobertura: {{ formPerfil.radio_cobertura_km }} km
                </label>
                <input
                  v-model.number="formPerfil.radio_cobertura_km"
                  type="range"
                  min="1"
                  max="100"
                  class="w-full accent-principal"
                />
              </div>

              <span v-if="formPerfil.errors.latitud" class="block text-xs font-semibold text-red-600">{{ formPerfil.errors.latitud }}</span>
              <span v-if="formPerfil.errors.longitud" class="block text-xs font-semibold text-red-600">{{ formPerfil.errors.longitud }}</span>
              <span v-if="formPerfil.errors.radio_cobertura_km" class="block text-xs font-semibold text-red-600">{{ formPerfil.errors.radio_cobertura_km }}</span>
            </div>

            <button
              type="submit"
              :disabled="formPerfil.processing"
              class="w-full py-3 px-4 bg-principal text-white text-sm font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95"
              :class="{ 'opacity-50 cursor-not-allowed': formPerfil.processing }"
            >
              Guardar datos del perfil
            </button>
          </form>

          <!-- Alternativa al GPS: buscar la dirección. Va fuera del <form> para no enviarlo. -->
          <div class="pt-6 border-t border-slate-100">
            <BuscadorUbicacion
              embebido
              seleccionable
              titulo="¿Prefieres buscar tu dirección?"
              @seleccionar="usarLugar"
            />
          </div>
        </div>

        <!-- ============ PUBLICAR UN SERVICIO ============ -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border-2 border-slate-200/90 shadow-md space-y-6 lg:sticky lg:top-28">
          <div class="flex items-center gap-3">
            <div class="w-11 h-11 shrink-0 rounded-xl bg-acento text-principal flex items-center justify-center">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.mas" />
              </svg>
            </div>
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Publicar un servicio</h2>
              <p class="text-xs text-gris-medio">Aparecerá en el catálogo público</p>
            </div>
          </div>

          <form @submit.prevent="publicarServicio" class="space-y-5">
            <div>
              <label :class="claseLabel">Título del servicio</label>
              <input
                v-model="formServicio.titulo"
                type="text"
                required
                placeholder="Ej: Reparación de cañerías"
                :class="claseInput"
              />
              <span v-if="formServicio.errors.titulo" class="block mt-1 text-xs font-semibold text-red-600">{{ formServicio.errors.titulo }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label :class="claseLabel">Categoría</label>
                <select v-model="formServicio.categoria_id" required :class="claseInput">
                  <option value="" disabled>Selecciona una categoría</option>
                  <option v-for="c in categorias" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
                <span v-if="formServicio.errors.categoria_id" class="block mt-1 text-xs font-semibold text-red-600">{{ formServicio.errors.categoria_id }}</span>
              </div>

              <div>
                <label :class="claseLabel">Precio estimado ($)</label>
                <input
                  v-model="formServicio.precio"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  :class="claseInput"
                />
                <span v-if="formServicio.errors.precio" class="block mt-1 text-xs font-semibold text-red-600">{{ formServicio.errors.precio }}</span>
              </div>
            </div>

            <div>
              <label :class="claseLabel">Detalles / Alcance del servicio</label>
              <textarea
                v-model="formServicio.descripcion"
                rows="4"
                required
                placeholder="¿Qué incluye este servicio?"
                :class="claseInput"
              ></textarea>
              <span v-if="formServicio.errors.descripcion" class="block mt-1 text-xs font-semibold text-red-600">{{ formServicio.errors.descripcion }}</span>
            </div>

            <button
              type="submit"
              :disabled="formServicio.processing"
              class="w-full py-3 px-4 bg-acento hover:bg-yellow-400 text-principal text-sm font-bold rounded-xl shadow-md hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200 active:scale-95"
              :class="{ 'opacity-50 cursor-not-allowed': formServicio.processing }"
            >
              Publicar servicio
            </button>
          </form>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>