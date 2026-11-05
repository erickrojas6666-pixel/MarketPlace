<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import BuscadorUbicacion from '@/Components/BuscadorUbicacion.vue'
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

let temporizador = null
watch(busqueda, () => {
  clearTimeout(temporizador)
  temporizador = setTimeout(aplicarFiltros, 350)
})

const seleccionarCategoria = (id) => {
  categoriaId.value = categoriaId.value === id ? null : id
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

/* ---------- Ayudas visuales ---------- */
const limpiarBusqueda = () => {
  busqueda.value = ''
}

const nombrePrestador = (s) =>
  s?.perfil_prestador?.user?.nombre || s?.perfil_prestador?.user?.name || 'Prestador verificado'

const iniciales = (texto) => {
  const partes = String(texto || '?').trim().split(/\s+/)
  return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?'
}

const iconos = {
  buscar: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',
  pin: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
  etiqueta: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
  cerrar: 'M6 18L18 6M6 6l12 12',
  check: 'M5 13l4 4L19 7',
  candado: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
  flecha: 'M13 7l5 5m0 0l-5 5m5-5H6',
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

      <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <!-- ============ HERO ============ -->
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] px-6 py-10 sm:py-14 shadow-xl shadow-principal/20 text-center">
          <div class="absolute -top-16 -right-16 w-72 h-72 rounded-full bg-acento/20 blur-2xl"></div>
          <div class="absolute -bottom-24 left-1/4 w-80 h-80 rounded-full bg-white/5"></div>

          <div class="relative z-10 max-w-3xl mx-auto space-y-4">
            <span class="inline-flex items-center gap-2 py-1.5 px-4 rounded-full bg-white/10 text-niebla text-xs font-bold tracking-wider uppercase border border-white/15">
              <span class="w-2 h-2 rounded-full bg-acento animate-pulse"></span>
              Catálogo de servicios
            </span>

            <h1 class="text-3xl sm:text-5xl font-extrabold text-white tracking-tight leading-tight">
              Encuentra a tu profesional <span class="text-acento">de confianza</span>
            </h1>

            <p class="text-sm sm:text-base text-niebla/80">
              Plomeros, electricistas, cerrajeros y más profesionales listos para atenderte en tu zona.
            </p>
          </div>
        </section>

        <!-- ============ BUSCADOR ============ -->
        <div class="flex items-center gap-2 bg-white p-2.5 rounded-2xl shadow-xl border border-slate-200">
          <div class="flex-1 flex items-center px-3 gap-2">
            <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.buscar" />
            </svg>
            <input
              v-model="busqueda"
              type="text"
              placeholder="Buscar por servicio o descripción..."
              class="w-full border-0 focus:ring-0 text-grafito placeholder-gray-400 text-sm outline-none bg-transparent"
            />
          </div>
          <button
            v-if="busqueda"
            type="button"
            @click="limpiarBusqueda"
            class="shrink-0 inline-flex items-center gap-1.5 px-4 py-2.5 bg-niebla hover:bg-niebla/70 text-principal text-xs font-bold rounded-xl transition active:scale-95"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
            </svg>
            Limpiar
          </button>
        </div>

        <!-- ============ CERCA DE MÍ ============ -->
        <section class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md p-6 space-y-4">
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

        <!-- ============ CATEGORÍAS (filtran al hacer clic) ============ -->
        <section class="space-y-4">
          <div class="flex items-end justify-between gap-4">
            <div>
              <h2 class="text-2xl font-extrabold text-principal tracking-tight">Categorías</h2>
              <p class="text-sm text-gris-medio mt-1">Elige un área para filtrar los servicios.</p>
            </div>

            <button
              v-if="categoriaId"
              type="button"
              @click="seleccionarCategoria(categoriaId)"
              class="inline-flex items-center gap-1.5 px-4 py-2 bg-white hover:bg-niebla text-principal text-xs font-bold rounded-xl border-2 border-slate-200 hover:border-principal/40 transition active:scale-95"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.cerrar" />
              </svg>
              Quitar filtro
            </button>
          </div>

          <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <button
              v-for="categoria in categorias"
              :key="categoria.id"
              type="button"
              @click="seleccionarCategoria(categoria.id)"
              class="group text-left rounded-2xl p-5 border-2 shadow-md hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col gap-4"
              :class="categoriaId === categoria.id
                ? 'bg-principal border-principal'
                : 'bg-white border-slate-200/90 hover:border-principal/40'"
            >
              <div
                class="w-12 h-12 rounded-xl flex items-center justify-center transition-colors duration-300"
                :class="categoriaId === categoria.id
                  ? 'bg-white/15 text-acento'
                  : 'bg-niebla border border-principal/15 text-principal group-hover:bg-principal group-hover:text-acento'"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.etiqueta" />
                </svg>
              </div>

              <div>
                <span
                  class="block font-extrabold text-sm sm:text-base"
                  :class="categoriaId === categoria.id ? 'text-white' : 'text-principal'"
                >
                  {{ categoria.nombre }}
                </span>
                <span
                  class="block text-xs mt-1 leading-relaxed line-clamp-2"
                  :class="categoriaId === categoria.id ? 'text-niebla/80' : 'text-gris-medio'"
                >
                  {{ categoria.descripcion }}
                </span>
              </div>
            </button>
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
              {{ hayUbicacion
                ? 'No encontramos prestadores que atiendan tu zona con esos filtros. Prueba un radio mayor.'
                : 'No hay servicios disponibles con esos filtros en este momento.' }}
            </p>
          </div>

          <!-- Tarjetas -->
          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <article
              v-for="servicio in lista"
              :key="servicio.id"
              class="bg-white rounded-2xl border-2 border-slate-200/90 shadow-md hover:shadow-2xl hover:border-principal/40 hover:-translate-y-1.5 transition-all duration-300 overflow-hidden flex flex-col justify-between"
            >
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
                  <div class="w-9 h-9 shrink-0 rounded-xl bg-gradient-to-br from-principal to-[#1d4a8a] text-white font-extrabold text-xs flex items-center justify-center shadow-sm">
                    {{ iniciales(nombrePrestador(servicio)) }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-[11px] text-gris-medio leading-none">Por</p>
                    <p class="text-xs font-bold text-principal truncate mt-0.5">{{ nombrePrestador(servicio) }}</p>
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