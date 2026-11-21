<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import Dropdown from '@/Components/Dropdown.vue'
import { Link, usePage } from '@inertiajs/vue3'
import Toast from '@/Components/Toast.vue'
import AvatarUsuario from '@/Components/AvatarUsuario.vue'

const menuAbierto = ref(false)
const page = usePage()

// Ambos flags los calcula HandleInertiaRequests a partir de los roles de Spatie.
const esAdmin = computed(() => !!page.props.auth?.user?.esAdmin)
const esPrestador = computed(() => !!page.props.auth?.user?.esPrestador)
const rol = computed(() => (esAdmin.value ? 'Admin' : esPrestador.value ? 'Prestador' : 'Cliente'))

const cerrarMenu = () => (menuAbierto.value = false)
const alPresionarTecla = (e) => {
  if (e.key === 'Escape') cerrarMenu()
}
onMounted(() => window.addEventListener('keydown', alPresionarTecla))
onBeforeUnmount(() => window.removeEventListener('keydown', alPresionarTecla))

// Íconos (trazos SVG en línea)
const iconos = {
  inicio: ['M3 12l9-9 9 9', 'M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10'],
  explorar: ['M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z'],
  prestador: [
    'M20 7H4a1 1 0 00-1 1v11a1 1 0 001 1h16a1 1 0 001-1V8a1 1 0 00-1-1z',
    'M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2',
    'M3 13h18',
  ],
  ajustes: [
    'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
    'M15 12a3 3 0 11-6 0 3 3 0 016 0z',
  ],
  salir: ['M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1'],
  cerrar: ['M6 18L18 6M6 6l12 12'],
}

// Clases de los enlaces del menú superior (el enlace activo va en dorado)
const claseEnlace = (activo) => [
  'inline-flex items-center px-1 pt-1 text-sm font-bold border-b-2 transition-colors duration-200',
  activo
    ? 'border-acento text-acento'
    : 'border-transparent text-white/90 hover:text-white hover:border-white/40',
]

// ---------- Panel lateral (móvil) ----------
const secciones = computed(() => {
  const principal = [
    {
      nombre: 'Inicio',
      href: route('dashboard'),
      activo: !!(route().current('dashboard') || route().current('admin.dashboard')),
      icono: 'inicio',
    },
    {
      nombre: 'Explorar Servicios',
      href: route('servicios.index'),
      activo: !!route().current('servicios.index'),
      icono: 'explorar',
    },
  ]

  if (esPrestador.value) {
    principal.push({
      nombre: 'Mi perfil de prestador',
      href: route('prestador.perfil.edit'),
      activo: !!route().current('prestador.perfil.edit'),
      icono: 'prestador',
    })
  }

  return [
    { titulo: 'Navegación principal', items: principal },
    {
      titulo: 'Cuenta',
      items: [
        {
          nombre: 'Ajustes de cuenta',
          href: route('profile.edit'),
          activo: !!route().current('profile.edit'),
          icono: 'ajustes',
        },
        { nombre: 'Cerrar sesión', href: route('logout'), salir: true, icono: 'salir' },
      ],
    },
  ]
})

const claseItem = (item) => {
  const base =
    'group flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left text-sm font-bold shadow-sm transition-all duration-200 active:scale-[0.98]'
  if (item.salir)
    return `${base} bg-white text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300 hover:text-red-700 active:bg-red-100`
  return item.activo
    ? `${base} bg-acento text-principal border-acento shadow-md`
    : `${base} bg-white text-principal border-slate-200/80 hover:border-principal/40 hover:shadow-md hover:-translate-y-0.5 active:bg-niebla`
}
const claseCaja = (item) => {
  const base = 'w-9 h-9 shrink-0 rounded-lg flex items-center justify-center transition-colors duration-200'
  if (item.salir) return `${base} bg-red-50 text-red-600 group-hover:bg-red-600 group-hover:text-white`
  return item.activo
    ? `${base} bg-principal text-acento`
    : `${base} bg-niebla text-principal group-hover:bg-principal group-hover:text-acento`
}

// ---------- Menú de usuario (escritorio) ----------
const itemEscritorio =
  'group flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm font-semibold whitespace-nowrap text-grafito transition-colors duration-200 hover:bg-niebla hover:text-principal active:bg-principal/10'
const cajaEscritorio =
  'w-7 h-7 shrink-0 rounded-md bg-niebla text-principal flex items-center justify-center transition-colors duration-200 group-hover:bg-principal group-hover:text-acento'
const itemEscritorioSalir =
  'group flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm font-semibold whitespace-nowrap text-red-600 transition-colors duration-200 hover:bg-red-50 hover:text-red-700 active:bg-red-100'
const cajaEscritorioSalir =
  'w-7 h-7 shrink-0 rounded-md bg-red-50 text-red-600 flex items-center justify-center transition-colors duration-200 group-hover:bg-red-600 group-hover:text-white'
</script>

<template>
  <div>
    <div class="min-h-screen bg-niebla text-grafito font-sans">
      <!-- Barra superior (misma identidad que la landing) -->
      <nav class="bg-principal border-b border-principal/20 sticky top-0 z-50 shadow-md">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
          <div class="flex h-20 justify-between">
            <div class="flex">
              <!-- Logo -->
              <div class="flex shrink-0 items-center">
                <Link :href="route('dashboard')">
                  <div class="bg-white px-3 py-1.5 rounded-2xl shadow-md flex items-center border border-white/30 transition-transform duration-200 hover:scale-105">
                    <img src="/images/logo.jpg" alt="OficiosYa" class="h-9 w-auto object-contain" />
                  </div>
                </Link>
              </div>

              <!-- Enlaces de navegación -->
              <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                <Link
                  :href="route('dashboard')"
                  :class="claseEnlace(route().current('dashboard') || route().current('admin.dashboard'))"
                >
                  Inicio
                </Link>

                <Link
                  :href="route('servicios.index')"
                  :class="claseEnlace(route().current('servicios.index'))"
                >
                  Explorar Servicios
                </Link>

                <!-- "Perfil Prestador" solo para usuarios con rol prestador -->
                <Link
                  v-if="esPrestador"
                  :href="route('prestador.perfil.edit')"
                  :class="claseEnlace(route().current('prestador.perfil.edit'))"
                >
                  Perfil Prestador
                </Link>
              </div>
            </div>

            <div class="hidden sm:ms-6 sm:flex sm:items-center">
              <!-- Menú de usuario (Escritorio) -->
              <div class="relative ms-3">
                <Dropdown align="right" width="48">
                  <template #trigger>
                    <span class="inline-flex rounded-md">
                      <button
                        type="button"
                        class="inline-flex items-center gap-2.5 rounded-xl border border-white/20 bg-white/10 px-3 py-1.5 text-sm font-bold text-white transition duration-200 ease-in-out hover:bg-white/20 focus:outline-none"
                      >
                        <AvatarUsuario
                          :nombre="$page.props.auth?.user?.nombre"
                          :url="$page.props.auth?.user?.fotoUrl"
                          clase="w-7 h-7 text-[10px]"
                        />
                        {{ $page.props.auth?.user?.nombre || $page.props.auth?.user?.name }}

                        <svg
                          class="-me-0.5 ms-2 h-4 w-4"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                        >
                          <path
                            fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd"
                          />
                        </svg>
                      </button>
                    </span>
                  </template>

                  <template #content>
                    <div class="p-1.5 space-y-0.5">
                      <Link v-if="esPrestador" :href="route('prestador.perfil.edit')" :class="itemEscritorio">
                        <span :class="cajaEscritorio">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path v-for="(d, i) in iconos.prestador" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
                          </svg>
                        </span>
                        Perfil prestador
                      </Link>

                      <Link :href="route('profile.edit')" :class="itemEscritorio">
                        <span :class="cajaEscritorio">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path v-for="(d, i) in iconos.ajustes" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
                          </svg>
                        </span>
                        Ajustes de cuenta
                      </Link>

                      <div class="my-1 border-t border-slate-200"></div>

                      <Link :href="route('logout')" method="post" as="button" :class="itemEscritorioSalir">
                        <span :class="cajaEscritorioSalir">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path v-for="(d, i) in iconos.salir" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
                          </svg>
                        </span>
                        Cerrar sesión
                      </Link>
                    </div>
                  </template>
                </Dropdown>
              </div>
            </div>

            <!-- Botón hamburguesa (Móvil) -->
            <div class="-me-2 flex items-center sm:hidden">
              <button
                type="button"
                @click="menuAbierto = true"
                class="inline-flex items-center justify-center rounded-xl p-2 text-white hover:bg-white/10 active:bg-white/20 focus:outline-none transition"
                :aria-expanded="menuAbierto"
                aria-label="Abrir menú"
              >
                <svg class="h-7 w-7" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </nav>

      <!-- ============ PANEL LATERAL (MÓVIL, sale por la derecha) ============ -->
      <Transition
        enter-active-class="transition-opacity duration-300"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity duration-200"
        leave-to-class="opacity-0"
      >
        <div
          v-if="menuAbierto"
          class="fixed inset-0 z-[60] bg-principal/60 backdrop-blur-sm sm:hidden"
          @click="cerrarMenu"
        ></div>
      </Transition>

      <Transition
        enter-active-class="transition-transform duration-300 ease-out"
        enter-from-class="translate-x-full"
        leave-active-class="transition-transform duration-200 ease-in"
        leave-to-class="translate-x-full"
      >
        <aside
          v-if="menuAbierto"
          class="fixed inset-y-0 right-0 z-[70] w-80 max-w-[85%] bg-gradient-to-b from-niebla to-[#eaf0f8] shadow-2xl flex flex-col sm:hidden"
          role="dialog"
          aria-modal="true"
          aria-label="Menú de navegación"
        >
          <!-- Encabezado: logo + cerrar (mismo azul de la navbar) -->
          <div class="flex items-center justify-between px-4 h-20 bg-principal border-b border-principal/20 shadow-md shrink-0">
            <div class="bg-white px-3 py-1.5 rounded-2xl shadow-md flex items-center border border-white/30">
              <img src="/images/logo.jpg" alt="OficiosYa" class="h-9 w-auto object-contain" />
            </div>
            <button
              type="button"
              @click="cerrarMenu"
              class="w-10 h-10 rounded-xl flex items-center justify-center text-white bg-white/10 border border-white/20 hover:bg-white/20 active:scale-95 transition-all duration-200"
              aria-label="Cerrar menú"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path v-for="(d, i) in iconos.cerrar" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
              </svg>
            </button>
          </div>

          <div class="flex-1 overflow-y-auto px-3 py-4">
            <!-- Tarjeta del usuario -->
            <div class="mb-5 flex items-center gap-3 rounded-2xl bg-white border border-slate-200 shadow-sm p-3">
              <AvatarUsuario
                :nombre="$page.props.auth?.user?.nombre"
                :url="$page.props.auth?.user?.fotoUrl"
                clase="w-12 h-12 text-base ring-2 ring-acento"
              />
              <div class="min-w-0 space-y-1">
                <div class="text-sm font-extrabold text-principal truncate">
                  {{ $page.props.auth?.user?.nombre || $page.props.auth?.user?.name }}
                </div>
                <div class="text-xs text-gris-medio truncate">{{ $page.props.auth?.user?.email }}</div>
                <span class="inline-flex px-2 py-0.5 rounded-md bg-acento/20 border border-acento/50 text-principal text-[10px] font-extrabold uppercase tracking-wider">
                  Rol: {{ rol }}
                </span>
              </div>
            </div>

            <!-- Secciones -->
            <div v-for="sec in secciones" :key="sec.titulo" class="mt-5 first:mt-0">
              <p class="px-1 mb-2 text-[11px] font-extrabold uppercase tracking-wider text-gris-medio">
                {{ sec.titulo }}
              </p>
              <nav class="space-y-2">
                <Link
                  v-for="item in sec.items"
                  :key="item.nombre"
                  :href="item.href"
                  :method="item.salir ? 'post' : 'get'"
                  :as="item.salir ? 'button' : 'a'"
                  :class="claseItem(item)"
                  @click="cerrarMenu"
                >
                  <span :class="claseCaja(item)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                      <path v-for="(d, i) in iconos[item.icono]" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
                    </svg>
                  </span>
                  {{ item.nombre }}
                </Link>
              </nav>
            </div>
          </div>
        </aside>
      </Transition>

      <!-- Encabezado de página -->
      <header class="bg-white shadow" v-if="$slots.header">
        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
          <slot name="header" />
        </div>
      </header>

      <!-- Contenido de la página -->
      <main>
        <slot />
      </main>
    </div>

    <!-- Alertas emergentes globales -->
    <Toast />
  </div>
</template>