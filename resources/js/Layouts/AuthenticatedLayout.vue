<script setup>
import { ref, computed } from 'vue'
import Dropdown from '@/Components/Dropdown.vue'
import DropdownLink from '@/Components/DropdownLink.vue'
import ResponsiveNavLink from '@/Components/ResponsiveNavLink.vue'
import { Link, usePage } from '@inertiajs/vue3'
import Toast from '@/Components/Toast.vue'

const showingNavigationDropdown = ref(false)
const page = usePage()

// Ambos flags los calcula HandleInertiaRequests a partir de los roles de Spatie.
const esAdmin = computed(() => !!page.props.auth?.user?.esAdmin)
const esPrestador = computed(() => !!page.props.auth?.user?.esPrestador)

// Clases de los enlaces del menú superior (el enlace activo va en dorado)
const claseEnlace = (activo) => [
  'inline-flex items-center px-1 pt-1 text-sm font-bold border-b-2 transition-colors duration-200',
  activo
    ? 'border-acento text-acento'
    : 'border-transparent text-white/90 hover:text-white hover:border-white/40',
]
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
                        class="inline-flex items-center rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold text-white transition duration-200 ease-in-out hover:bg-white/20 focus:outline-none"
                      >
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
                    <DropdownLink v-if="esPrestador" :href="route('prestador.perfil.edit')">
                      Perfil de Prestador
                    </DropdownLink>

                    <DropdownLink :href="route('profile.edit')">
                      Ajustes de Cuenta
                    </DropdownLink>

                    <DropdownLink
                      :href="route('logout')"
                      method="post"
                      as="button"
                    >
                      Cerrar Sesión
                    </DropdownLink>
                  </template>
                </Dropdown>
              </div>
            </div>

            <!-- Hamburguesa (Móvil) -->
            <div class="-me-2 flex items-center sm:hidden">
              <button
                @click="showingNavigationDropdown = !showingNavigationDropdown"
                class="inline-flex items-center justify-center rounded-xl p-2 text-white hover:bg-white/10 focus:outline-none transition"
              >
                <svg
                  class="h-6 w-6"
                  stroke="currentColor"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <path
                    :class="{
                      hidden: showingNavigationDropdown,
                      'inline-flex': !showingNavigationDropdown,
                    }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16"
                  />
                  <path
                    :class="{
                      hidden: !showingNavigationDropdown,
                      'inline-flex': showingNavigationDropdown,
                    }"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M6 18L18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          </div>
        </div>

        <!-- Menú desplegable (Móvil) -->
        <div
          :class="{
            block: showingNavigationDropdown,
            hidden: !showingNavigationDropdown,
          }"
          class="sm:hidden bg-white border-t border-slate-200"
        >
          <div class="space-y-1 pb-3 pt-2">
            <ResponsiveNavLink
              :href="route('dashboard')"
              :active="route().current('dashboard')"
            >
              Inicio
            </ResponsiveNavLink>

            <ResponsiveNavLink
              :href="route('servicios.index')"
              :active="route().current('servicios.index')"
            >
              Explorar Servicios
            </ResponsiveNavLink>
          </div>

          <div class="border-t border-slate-200 pb-1 pt-4">
            <div class="px-4">
              <div class="text-base font-bold text-principal">
                {{ $page.props.auth?.user?.nombre || $page.props.auth?.user?.name }}
              </div>
              <div class="text-sm font-medium text-gris-medio">
                {{ $page.props.auth?.user?.email }}
              </div>
            </div>

            <div class="mt-3 space-y-1">
              <ResponsiveNavLink v-if="esPrestador" :href="route('prestador.perfil.edit')" :active="route().current('prestador.perfil.edit')">
                Mi Perfil de Prestador / Servicios
              </ResponsiveNavLink>
              <ResponsiveNavLink :href="route('profile.edit')">
                Ajustes de Cuenta
              </ResponsiveNavLink>
              <ResponsiveNavLink
                :href="route('logout')"
                method="post"
                as="button"
              >
                Cerrar Sesión
              </ResponsiveNavLink>
            </div>
          </div>
        </div>
      </nav>

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