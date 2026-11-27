<script setup>
import { ref, watch, computed, nextTick } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AvatarUsuario from '@/Components/AvatarUsuario.vue'
import BuscadorUbicacion from '@/Components/BuscadorUbicacion.vue'
import MapaUbicacion from '@/Components/MapaUbicacion.vue'
import { useGeolocalizacion, redondear } from '@/composables/useGeolocalizacion'

const props = defineProps({
  // Paginador de Laravel: { data: [...], links: [...], total, ... }
  servicios: { type: Object, default: () => ({ data: [], links: [], total: 0 }) },
  categorias: { type: Array, default: () => [] },
  filtros: { type: Object, default: () => ({ categoria_id: null, q: null, lat: null, lng: null, radio_km: 10 }) },
})

const page = usePage()
const estaAutenticado = computed(() => !!page.props.auth?.user)
const Contenedor = computed(() => (estaAutenticado.value ? AuthenticatedLayout : 'div'))

const lista = computed(() => props.servicios?.data ?? [])
const enlaces = computed(() => props.servicios?.links ?? [])

// --- Filtros (se aplican en el servidor para que la paginación sea correcta) ---
const busqueda = ref(props.filtros?.q ?? '')
const categoriaId = ref(props.filtros?.categoria_id ? Number(props.filtros.categoria_id) : null)

// Ubicación del cliente: solo viaja en la URL de la búsqueda, no se guarda en base de datos.
const lat = ref(props.filtros?.lat != null ? Number(props.filtros.lat) : null)
const lng = ref(props.filtros?.lng != null ? Number(props.filtros.lng) : null)
const radioKm = ref(props.filtros?.radio_km ? Number(props.filtros.radio_km) : 10)
const RADIOS = [5, 10, 25, 50]

const { cargando: ubicando, error: errorUbicacion, obtener } = useGeolocalizacion()
const hayUbicacion = computed(() => lat.value !== null && lng.value !== null)

const aplicarFiltros = () => {
  router.get(
    route('servicios.index'),
    {
      q: busqueda.value || undefined,
      categoria_id: categoriaId.value || undefined,
      lat: hayUbicacion.value ? lat.value : undefined,
      lng: hayUbicacion.value ? lng.value : undefined,
      radio_km: hayUbicacion.value ? radioKm.value : undefined,
    },
    { preserveState: true, preserveScroll: true, replace: true }
  )
}

// Al escribir, la lista se filtra sola (con una pequeña pausa para no saturar al servidor).
let temporizador = null
watch(busqueda, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(aplicarFiltros, 350)
})

const seleccionarCategoria = (id) => {
  categoriaId.value = categoriaId.value === id ? null : id
  aplicarFiltros()
}

const quitarCategoria = () => {
  if (categoriaId.value === null) return
  categoriaId.value = null
  aplicarFiltros()
}

const fijarUbicacion = (la, lo) => {
  lat.value = redondear(la)
  lng.value = redondear(lo)
  aplicarFiltros()
}

const usarMiUbicacion = async () => {
  const pos = await obtener()
  if (pos) fijarUbicacion(pos.lat, pos.lng)
}

const usarLugar = (lugar) => fijarUbicacion(parseFloat(lugar.lat), parseFloat(lugar.lon))

const quitarUbicacion = () => {
  lat.value = null
  lng.value = null
  aplicarFiltros()
}

const precioDe = (s) => s?.precio_base ?? null

/* ---------- Lupa: busca el texto cerca del usuario y sube la vista al mapa ---------- */
const campoBusqueda = ref(null)
const seccionGps = ref(null)

const irAlMapa = async () => {
  await nextTick()
  seccionGps.value?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const buscarCerca = async () => {
  clearTimeout(temporizador) // evita que la búsqueda al escribir se repita
  if (hayUbicacion.value) {
    aplicarFiltros()
  } else {
    // Si todavía no hay permiso, el navegador lo pide aquí. Si lo niegan, el motivo
    // queda en `errorUbicacion` y se muestra en el recuadro del GPS.
    const pos = await obtener()
    if (pos) fijarUbicacion(pos.lat, pos.lng)
  }
  irAlMapa()
}

const limpiarBusqueda = () => {
  busqueda.value = ''
  campoBusqueda.value?.focus()
}

/* ---------- Ayudas visuales ---------- */
const nombrePrestador = (s) =>
  s?.perfil_prestador?.user?.nombre || s?.perfil_prestador?.user?.name || 'Prestador verificado'

const mensajeVacio = computed(() => {
  if (hayUbicacion.value) {
    return busqueda.value
      ? `No encontramos «${busqueda.value}» cerca de ti. Prueba un radio mayor o cambia la búsqueda.`
      : 'No encontramos prestadores que atiendan tu zona con esos filtros. Prueba un radio mayor.'
  }
  return 'No hay servicios disponibles con esos filtros en este momento.'
})

const claseChip = (activo) => [
  'shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-full border-2 text-xs sm:text-sm font-bold transition-all duration-200 active:scale-95',
  activo
    ? 'bg-principal text-white border-principal shadow-md'
    : 'bg-white text-principal border-slate-200 hover:border-principal/40 hover:bg-niebla/60',
]

const iconos = {
  buscar: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
  pin: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
  etiqueta: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
  cerrar: 'M6 18L18 6M6 6l12 12',
  check: 'M5 13l4 4L19 7',
  candado: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
  flecha: 'M13 7l5 5m0 0l-5 5m5-5H6',
}

// Íconos de las categorías (los mismos de la landing). Se eligen por el nombre de la categoría.
const iconosCategoria = {
  todas: [
    'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6z',
    'M14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6z',
    'M4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2z',
    'M14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
  ],
  plomeria: [
    'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
    'M12 18a3 3 0 0 1-3-3c0-1.5 3-4.5 3-4.5s3 3 3 4.5a3 3 0 0 1-3 3z',
  ],
  electricidad: ['M13 2L3 14h9l-1 8 10-12h-9l1-8z'],
  cerrajeria: [
    'M3 15.5a4.5 4.5 0 1 0 9 0a4.5 4.5 0 1 0-9 0',
    'm10.7 12.3 8.8-8.8a1.5 1.5 0 0 1 2.1 0v0a1.5 1.5 0 0 1 0 2.1l-2.1 2.1 1.5 1.5-1.5 1.5-1.5-1.5-1.5 1.5-2.1-2.1',
  ],
  pintura: [
    'M5 3h12a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z',
    'M19 6h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-9a2 2 0 0 0-2 2v3',
    'M9 18a1 1 0 0 1 2 0v3a1 1 0 0 1-2 0v-3z',
  ],
  jardineria: [
    'M12 22v-9',
    'M12 13a6 6 0 0 0-6-6H3a6 6 0 0 1 6 6v0a6 6 0 0 0 3 0z',
    'M12 13a6 6 0 0 1 6-6h3a6 6 0 0 0-6 6v0a6 6 0 0 1-3 0z',
  ],
}

const iconoDe = (categoria) => {
  const clave = String(categoria?.nombre || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()

  if (clave.includes('cerraj')) return iconosCategoria.cerrajeria
  if (clave.includes('electric')) return iconosCategoria.electricidad
  if (clave.includes('jardin')) return iconosCategoria.jardineria
  if (clave.includes('pintur')) return iconosCategoria.pintura
  if (clave.includes('plomer')) return iconosCategoria.plomeria
  return [iconos.etiqueta]
}
</script>

<template>
  <Head title="Catálogo de Servicios - OficiosYa" />

  <!-- Usuario autenticado: layout de la app. Invitado: estructura simple. -->
  <component :is="Contenedor">
    <div :class="estaAutenticado ? '' : 'min-h-screen bg-niebla text-grafito font-sans'">

      <!-- ============ ENCABEZADO PARA INVITADOS ============ -->
      <header v-if="!estaAutenticado" class="bg-principal border-b border-principal/20 sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
          <Link href="/" class="flex items-center">
            <div class="bg-white px-3 py-1.5 rounded-2xl shadow-md flex items-center border border-white/30 transition-transform duration-200 hover:scale-105">
              <img src="/images/logo.jpg" alt="OficiosYa" class="h-9 w-auto object-contain" />
            </div>
          </Link>

          <nav class="flex items-center gap-3 sm:gap-4">
            <Link
              :href="route('login')"
              class="px-4 py-2 text-sm font-semibold text-white/90 hover:text-white transition-colors duration-200"
            >
              Iniciar sesión
            </Link>
            <Link
              :href="route('register')"
              class="px-5 py-2.5 text-sm font-bold text-principal bg-acento hover:bg-yellow-400 rounded-xl shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5"
            >
              Registrarse
            </Link>
          </nav>
        </div>
      </header>

      <div class="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- ============ BANNER (delgado) ============ -->
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] px-6 py-6 sm:px-8 sm:py-7 shadow-xl shadow-principal/20">
          <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-acento/20 blur-2xl"></div>
          <div class="absolute -bottom-24 left-1/3 w-72 h-72 rounded-full bg-white/5"></div>

          <div class="relative z-10 space-y-1">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight leading-tight">
              Encuentra a tu profesional <span class="text-acento">de confianza</span>
            </h1>
            <p class="text-sm text-niebla/80">
              Plomeros, electricistas, cerrajeros y más profesionales listos para atenderte en tu zona.
            </p>
          </div>
        </section>

        <!-- ============ BÚSQUEDA Y FILTROS ============ -->
        <section class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md p-4 sm:p-5 space-y-4">
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <div class="relative flex-1">
                <input
                  ref="campoBusqueda"
                  v-model="busqueda"
                  @keydown.enter.prevent="buscarCerca"
                  type="text"
                  placeholder="Buscar por servicio o descripción..."
                  class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2.5 pl-4 pr-10 placeholder:text-slate-400"
                />
                <!-- Borra el texto (solo aparece cuando hay algo escrito) -->
                <button
                  v-if="busqueda"
                  type="button"
                  @click="limpiarBusqueda"
                  class="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg flex items-center justify-center text-gris-medio hover:bg-niebla hover:text-principal transition"
                  aria-label="Borrar texto"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
                  </svg>
                </button>
              </div>

              <!-- Lupa: busca este texto cerca del usuario -->
              <button
                type="button"
                @click="buscarCerca"
                :disabled="ubicando"
                title="Buscar cerca de mí"
                aria-label="Buscar cerca de mí"
                class="shrink-0 w-11 h-11 inline-flex items-center justify-center rounded-xl bg-principal text-acento shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95"
                :class="{ 'opacity-50 cursor-not-allowed': ubicando }"
              >
                <svg v-if="ubicando" class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.buscar" />
                </svg>
              </button>
            </div>

            <p class="text-xs text-gris-medio">Con la lupa buscas este servicio cerca de ti.</p>
          </div>

          <!-- Categorías: filtran al hacer clic -->
          <div class="flex flex-nowrap sm:flex-wrap gap-2 overflow-x-auto sm:overflow-visible -mx-1 px-1 pb-1">
            <button type="button" @click="quitarCategoria" :class="claseChip(categoriaId === null)">
              <svg
                class="w-4 h-4"
                :class="categoriaId === null ? 'text-acento' : 'text-principal'"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                viewBox="0 0 24 24"
              >
                <path v-for="(d, i) in iconosCategoria.todas" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
              </svg>
              Todas
            </button>

            <button
              v-for="categoria in categorias"
              :key="categoria.id"
              type="button"
              @click="seleccionarCategoria(categoria.id)"
              :class="claseChip(categoriaId === categoria.id)"
            >
              <svg
                class="w-4 h-4"
                :class="categoriaId === categoria.id ? 'text-acento' : 'text-principal'"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                viewBox="0 0 24 24"
              >
                <path v-for="(d, i) in iconoDe(categoria)" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
              </svg>
              {{ categoria.nombre }}
            </button>
          </div>
        </section>

        <!-- ============ CERCA DE MÍ (GPS + mapa) ============ -->
        <section ref="seccionGps" class="scroll-mt-24 bg-white rounded-3xl border-2 border-slate-200/90 shadow-md p-6 space-y-4">
          <div class="flex flex-wrap items-center gap-3">
            <button
              type="button"
              @click="usarMiUbicacion"
              :disabled="ubicando"
              class="inline-flex items-center gap-2 px-5 py-2.5 bg-principal hover:bg-slate-800 text-white text-sm font-bold rounded-xl shadow-md transition-all duration-200 active:scale-95"
              :class="{ 'opacity-50 cursor-not-allowed': ubicando }"
            >
              <svg class="w-4 h-4 text-acento" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
              </svg>
              {{ ubicando ? 'Obteniendo ubicación...' : 'Cerca de mí' }}
            </button>

            <template v-if="hayUbicacion">
              <label class="text-sm font-bold text-principal flex items-center gap-2">
                Radio
                <select
                  v-model.number="radioKm"
                  @change="aplicarFiltros"
                  class="text-sm rounded-xl border-slate-300 focus:border-principal focus:ring-principal shadow-sm py-1.5 pl-3 pr-8"
                >
                  <option v-for="r in RADIOS" :key="r" :value="r">{{ r }} km</option>
                </select>
              </label>

              <button
                type="button"
                @click="quitarUbicacion"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-red-50 text-red-600 text-xs font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition active:scale-95"
              >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
                </svg>
                Quitar ubicación
              </button>
            </template>
          </div>

          <p v-if="errorUbicacion" class="text-sm font-semibold text-red-600 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5">
            {{ errorUbicacion }}
          </p>

          <p
            v-if="hayUbicacion"
            class="text-xs font-semibold text-green-700 bg-green-50 border border-green-200 rounded-xl px-4 py-2.5 flex items-center gap-2"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
            </svg>
            Mostrando prestadores que atienden tu zona, del más cercano al más lejano.
          </p>

          <!-- Tu punto de búsqueda y el radio (los prestadores nunca muestran su ubicación exacta) -->
          <MapaUbicacion v-if="hayUbicacion" :lat="lat" :lng="lng" :radio-km="Number(radioKm)" altura="560px" />

          <p class="text-xs text-gris-medio flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.candado" />
            </svg>
            Tu ubicación solo se usa para esta búsqueda; no se guarda en nuestros servidores.
          </p>

          <!-- La búsqueda por dirección usa una ruta que exige sesión -->
          <div v-if="estaAutenticado" class="pt-4 border-t border-slate-100">
            <BuscadorUbicacion
              embebido
              seleccionable
              titulo="¿Sin GPS? Busca tu dirección"
              @seleccionar="usarLugar"
            />
          </div>
        </section>

        <!-- ============ LISTADO DE SERVICIOS ============ -->
        <section class="space-y-4">
          <div class="flex items-center justify-between gap-4">
            <h2 class="text-2xl font-extrabold text-principal tracking-tight">Servicios</h2>
            <span class="min-w-8 h-8 px-3 rounded-full bg-white text-principal text-sm font-extrabold flex items-center justify-center border-2 border-slate-200/90 shadow-sm">
              {{ servicios.total ?? lista.length }}
            </span>
          </div>

          <!-- Vacío -->
          <div
            v-if="lista.length === 0"
            class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md py-14 px-6 flex flex-col items-center text-center"
          >
            <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.buscar" />
              </svg>
            </div>
            <p class="text-sm font-bold text-principal">Sin resultados</p>
            <p class="text-xs text-gris-medio mt-1 max-w-md">
              {{ mensajeVacio }}
            </p>
          </div>

          <!-- Tarjetas -->
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <article
              v-for="servicio in lista"
              :key="servicio.id"
              class="bg-white rounded-2xl border-2 border-slate-200/90 shadow-md hover:shadow-2xl hover:border-principal/40 hover:-translate-y-1.5 transition-all duration-300 overflow-hidden flex flex-col justify-between"
            >
              <!-- Portada: primera foto del portafolio; lleva al perfil público del prestador -->
              <Link
                v-if="servicio.fotos?.length"
                :href="route('prestadores.show', servicio.perfil_prestador.id)"
                class="relative block aspect-[16/9] bg-niebla overflow-hidden"
                :aria-label="`Ver trabajos de ${nombrePrestador(servicio)}`"
              >
                <img
                  :src="servicio.fotos[0].url_miniatura"
                  :alt="servicio.fotos[0].pie || servicio.titulo"
                  loading="lazy"
                  class="w-full h-full object-cover transition-transform duration-300 hover:scale-105"
                />
                <span
                  v-if="servicio.fotos.length > 1"
                  class="absolute bottom-2 right-2 px-2 py-0.5 rounded-full bg-black/60 text-white text-[11px] font-bold"
                >
                  {{ servicio.fotos.length }} fotos
                </span>
              </Link>

              <div class="p-6 space-y-3">
                <div class="flex justify-between items-center gap-2">
                  <span class="text-[11px] font-bold uppercase tracking-wider text-principal bg-niebla px-3 py-1 rounded-full border border-principal/15 truncate">
                    {{ servicio.categoria?.nombre || 'General' }}
                  </span>
                  <span
                    v-if="precioDe(servicio) !== null"
                    class="shrink-0 text-sm font-extrabold text-principal bg-acento/20 border border-acento/50 px-3 py-1 rounded-full"
                  >
                    ${{ precioDe(servicio) }}
                  </span>
                </div>

                <h3 class="text-lg font-extrabold text-principal tracking-tight">
                  {{ servicio.titulo }}
                </h3>

                <p
                  v-if="servicio.distancia_km != null"
                  class="inline-flex items-center gap-1.5 text-xs font-bold text-green-700 bg-green-50 border border-green-200 px-2.5 py-1 rounded-full"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.pin" />
                  </svg>
                  a {{ servicio.distancia_km }} km
                </p>

                <p class="text-sm text-gris-medio leading-relaxed line-clamp-3">
                  {{ servicio.descripcion }}
                </p>
              </div>

              <div class="px-6 py-4 bg-niebla/40 border-t border-slate-200 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 min-w-0">
                  <AvatarUsuario
                    :nombre="nombrePrestador(servicio)"
                    :url="servicio.perfil_prestador?.user?.foto_url"
                    clase="w-9 h-9 text-xs"
                  />
                  <div class="min-w-0">
                    <p class="text-[11px] text-gris-medio leading-none">Por</p>
                    <Link
                      :href="route('prestadores.show', servicio.perfil_prestador.id)"
                      class="block text-xs font-bold text-principal truncate mt-0.5 hover:underline"
                    >
                      {{ nombrePrestador(servicio) }}
                    </Link>
                  </div>
                </div>

                <!-- Los pedidos se crean desde el dashboard; invitados deben iniciar sesión -->
                <Link
                  :href="estaAutenticado ? route('dashboard') : route('login')"
                  class="shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl shadow-sm transition-all duration-200 active:scale-95"
                  :class="estaAutenticado
                    ? 'bg-principal text-white hover:bg-slate-800'
                    : 'bg-acento text-principal hover:bg-yellow-400'"
                >
                  {{ estaAutenticado ? 'Solicitar desde mi panel' : 'Inicia sesión para solicitar' }}
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.flecha" />
                  </svg>
                </Link>
              </div>
            </article>
          </div>

          <!-- Paginación -->
          <nav v-if="enlaces.length > 3" class="pt-6 flex flex-wrap justify-center gap-1.5">
            <template v-for="(enlace, i) in enlaces" :key="i">
              <Link
                v-if="enlace.url"
                :href="enlace.url"
                preserve-scroll
                class="px-3.5 py-1.5 text-xs font-bold rounded-xl transition"
                :class="enlace.active
                  ? 'bg-principal text-white shadow-md'
                  : 'bg-white text-principal border-2 border-slate-200 hover:border-principal/40'"
                v-html="enlace.label"
              />
              <span
                v-else
                class="px-3.5 py-1.5 text-xs font-bold rounded-xl text-slate-400 bg-slate-50 border-2 border-slate-100 cursor-not-allowed"
                v-html="enlace.label"
              />
            </template>
          </nav>
        </section>

      </div>
    </div>
  </component>
</template>