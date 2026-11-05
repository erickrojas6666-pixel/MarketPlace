<script setup>
import { ref, watch, computed } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  // Paginador de Laravel: { data: [...], links: [...], total, ... }
  servicios: { type: Object, default: () => ({ data: [], links: [], total: 0 }) },
  categorias: { type: Array, default: () => [] },
  filtros: { type: Object, default: () => ({ categoria_id: null, q: null }) },
})

const page = usePage()
const estaAutenticado = computed(() => !!page.props.auth?.user)
const Contenedor = computed(() => (estaAutenticado.value ? AuthenticatedLayout : 'div'))

const lista = computed(() => props.servicios?.data ?? [])
const enlaces = computed(() => props.servicios?.links ?? [])

// --- Filtros (se aplican en el servidor para que la paginación sea correcta) ---
const busqueda = ref(props.filtros?.q ?? '')
const categoriaId = ref(props.filtros?.categoria_id ? Number(props.filtros.categoria_id) : null)

const aplicarFiltros = () => {
  router.get(
    route('servicios.index'),
    {
      q: busqueda.value || undefined,
      categoria_id: categoriaId.value || undefined,
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

const precioDe = (s) => s?.precio_base ?? null
</script>

<template>
  <Head title="Catálogo de Servicios - OficiosYa" />

  <!-- Usuario autenticado: layout de la app. Invitado: estructura simple. -->
  <component :is="Contenedor">

    <!-- Encabezado para invitados -->
    <header v-if="!estaAutenticado" class="bg-white dark:bg-gray-800 shadow">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
        <Link href="/" class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">
          OficiosYa
        </Link>

        <nav class="space-x-4">
          <Link :href="route('login')" class="text-sm font-semibold text-gray-700 dark:text-gray-300 hover:text-indigo-500">
            Iniciar Sesión
          </Link>
          <Link :href="route('register')" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-500 transition">
            Registrarse
          </Link>
        </nav>
      </div>
    </header>

    <div class="py-8 bg-gray-100 dark:bg-gray-900 min-h-screen">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <!-- Hero -->
        <div class="bg-indigo-700 dark:bg-indigo-900 rounded-2xl p-8 text-white shadow-lg text-center">
          <h2 class="text-3xl font-extrabold sm:text-4xl">
            Encuentra a tu profesional de confianza
          </h2>
          <p class="mt-2 text-indigo-100">
            Plomeros, electricistas, cerrajeros y más profesionales listos para atenderte en tu zona.
          </p>
        </div>

        <!-- Buscador -->
        <input
          v-model="busqueda"
          type="text"
          placeholder="🔍 Buscar por servicio o descripción..."
          class="w-full text-sm rounded-lg border-gray-300 dark:bg-gray-800 dark:text-white dark:border-gray-700"
        />

        <!-- Categorías (filtran al hacer clic) -->
        <section>
          <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">
            Categorías
          </h3>
          <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
            <button
              v-for="categoria in categorias"
              :key="categoria.id"
              type="button"
              @click="seleccionarCategoria(categoria.id)"
              class="p-4 rounded-xl shadow-sm hover:shadow-md transition border text-center"
              :class="categoriaId === categoria.id
                ? 'bg-indigo-600 border-indigo-600 text-white'
                : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700'"
            >
              <span
                class="block font-bold text-sm sm:text-base"
                :class="categoriaId === categoria.id ? 'text-white' : 'text-indigo-600 dark:text-indigo-400'"
              >
                {{ categoria.nombre }}
              </span>
              <span
                class="text-xs mt-1 line-clamp-2"
                :class="categoriaId === categoria.id ? 'text-indigo-100' : 'text-gray-500 dark:text-gray-400'"
              >
                {{ categoria.descripcion }}
              </span>
            </button>
          </div>
        </section>

        <!-- Listado de servicios -->
        <section>
          <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">
            Servicios ({{ servicios.total ?? lista.length }})
          </h3>

          <div v-if="lista.length === 0" class="bg-white dark:bg-gray-800 p-8 rounded-xl text-center text-gray-500 dark:text-gray-400 shadow-sm">
            No hay servicios disponibles con esos filtros en este momento.
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
              v-for="servicio in lista"
              :key="servicio.id"
              class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col justify-between"
            >
              <div class="p-6">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-xs font-semibold uppercase tracking-wider text-indigo-500 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950 px-2.5 py-0.5 rounded-full">
                    {{ servicio.categoria?.nombre || 'General' }}
                  </span>
                  <span v-if="precioDe(servicio) !== null" class="text-sm font-bold text-indigo-600 dark:text-indigo-400">
                    ${{ precioDe(servicio) }}
                  </span>
                </div>
                <h4 class="text-lg font-bold text-gray-900 dark:text-white">
                  {{ servicio.titulo }}
                </h4>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-2 line-clamp-3">
                  {{ servicio.descripcion }}
                </p>
              </div>

              <div class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-700/50 flex items-center justify-between">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  Por:
                  <strong class="text-gray-700 dark:text-gray-300">
                    {{ servicio.perfil_prestador?.user?.nombre || 'Prestador verificado' }}
                  </strong>
                </span>

                <!-- Los pedidos se crean desde el dashboard; invitados deben iniciar sesión -->
                <Link
                  :href="estaAutenticado ? route('dashboard') : route('login')"
                  class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition shadow-sm"
                >
                  {{ estaAutenticado ? 'Solicitar desde mi panel' : 'Inicia sesión para solicitar' }}
                </Link>
              </div>
            </div>
          </div>

          <!-- Paginación -->
          <nav v-if="enlaces.length > 3" class="mt-8 flex flex-wrap justify-center gap-1">
            <template v-for="(enlace, i) in enlaces" :key="i">
              <Link
                v-if="enlace.url"
                :href="enlace.url"
                preserve-scroll
                class="px-3 py-1.5 text-sm rounded border transition"
                :class="enlace.active
                  ? 'bg-indigo-600 border-indigo-600 text-white'
                  : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50'"
                v-html="enlace.label"
              />
              <span
                v-else
                class="px-3 py-1.5 text-sm rounded border border-gray-200 dark:border-gray-700 text-gray-400"
                v-html="enlace.label"
              />
            </template>
          </nav>
        </section>

      </div>
    </div>
  </component>
</template>