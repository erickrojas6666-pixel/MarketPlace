<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import AvatarUsuario from '@/Components/AvatarUsuario.vue'
import GaleriaFotos from '@/Components/GaleriaFotos.vue'

const props = defineProps({
  prestador: { type: Object, required: true },
  servicios: { type: Array, default: () => [] },
  galeria: { type: Array, default: () => [] },
  resumen: { type: Object, default: () => ({ promedio: null, total_resenas: 0, trabajos_confirmados: 0 }) },
  resenas: { type: Array, default: () => [] },
})

const page = usePage()
const estaAutenticado = computed(() => !!page.props.auth?.user)
const Contenedor = computed(() => (estaAutenticado.value ? AuthenticatedLayout : 'div'))

const estrellas = (n) => '★'.repeat(n) + '☆'.repeat(5 - n)
const desde = computed(() => (props.prestador.miembro_desde ? props.prestador.miembro_desde.slice(0, 4) : null))
</script>

<template>
  <Head :title="`${prestador.nombre} - OficiosYa`" />

  <component :is="Contenedor">
    <div :class="estaAutenticado ? '' : 'min-h-screen bg-niebla text-grafito font-sans'">

      <header v-if="!estaAutenticado" class="bg-principal border-b border-principal/20 sticky top-0 z-50 shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
          <Link href="/" class="flex items-center">
            <div class="bg-white px-3 py-1.5 rounded-2xl shadow-md flex items-center border border-white/30">
              <img src="/images/logo.jpg" alt="OficiosYa" class="h-9 w-auto object-contain" />
            </div>
          </Link>
          <nav class="flex items-center gap-3 sm:gap-4">
            <Link :href="route('login')" class="px-4 py-2 text-sm font-semibold text-white/90 hover:text-white">Iniciar sesión</Link>
            <Link :href="route('register')" class="px-5 py-2.5 text-sm font-bold text-principal bg-acento hover:bg-yellow-400 rounded-xl shadow-md">Registrarse</Link>
          </nav>
        </div>
      </header>

      <div class="py-10 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

        <Link :href="route('servicios.index')" class="inline-flex items-center gap-1.5 text-xs font-bold text-principal hover:underline">
          ← Volver al catálogo
        </Link>

        <!-- ============ ENCABEZADO ============ -->
        <section class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md p-6 sm:p-8">
          <div class="flex flex-col sm:flex-row gap-6">
            <AvatarUsuario :nombre="prestador.nombre" :url="prestador.foto_url" clase="w-28 h-28 text-4xl" />

            <div class="min-w-0 space-y-3">
              <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-principal tracking-tight">{{ prestador.nombre }}</h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-green-50 border border-green-200 text-green-700 text-[11px] font-bold uppercase tracking-wider">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                  Verificado
                </span>
                <span v-if="prestador.es_pro" class="px-2.5 py-1 rounded-full bg-acento/20 border border-acento/50 text-principal text-[11px] font-extrabold uppercase tracking-wider">Pro</span>
              </div>

              <p class="text-sm text-gris-medio">
                <template v-if="prestador.ubicacion_aproximada">{{ prestador.ubicacion_aproximada }} · </template>
                Atiende hasta {{ prestador.radio_cobertura_km }} km
                <template v-if="desde"> · En OficiosYa desde {{ desde }}</template>
              </p>

              <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                <span v-if="resumen.promedio !== null" class="font-bold text-principal">
                  <span class="text-acento" aria-hidden="true">★</span> {{ resumen.promedio }}
                  <span class="font-medium text-gris-medio">({{ resumen.total_resenas }} reseñas)</span>
                </span>
                <span v-else class="text-gris-medio">Aún sin reseñas</span>
                <span class="text-gris-medio"><strong class="text-principal">{{ resumen.trabajos_confirmados }}</strong> trabajos confirmados</span>
              </div>

              <p v-if="prestador.descripcion" class="text-sm text-grafito leading-relaxed whitespace-pre-line">{{ prestador.descripcion }}</p>
            </div>
          </div>
        </section>

        <!-- ============ PORTAFOLIO ============ -->
        <section v-if="galeria.length" class="space-y-4">
          <h2 class="text-lg font-extrabold text-principal">Trabajos realizados</h2>
          <GaleriaFotos :fotos="galeria" />
        </section>

        <!-- ============ SERVICIOS ============ -->
        <section class="space-y-4">
          <h2 class="text-lg font-extrabold text-principal">Servicios</h2>

          <p v-if="!servicios.length" class="text-sm text-gris-medio">Este prestador no tiene servicios publicados por ahora.</p>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <article v-for="s in servicios" :key="s.id" class="bg-white rounded-2xl border-2 border-slate-200/90 shadow-sm overflow-hidden flex flex-col">
              <div v-if="s.portada" class="aspect-[16/9] bg-niebla">
                <img :src="s.portada" :alt="s.titulo" loading="lazy" class="w-full h-full object-cover" />
              </div>
              <div class="p-5 space-y-2 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <span class="text-[11px] font-bold uppercase tracking-wider text-principal bg-niebla px-3 py-1 rounded-full truncate">{{ s.categoria || 'General' }}</span>
                  <span v-if="s.precio_base !== null" class="shrink-0 text-sm font-extrabold text-principal bg-acento/20 border border-acento/50 px-3 py-1 rounded-full">${{ s.precio_base }}</span>
                </div>
                <h3 class="text-base font-extrabold text-principal">{{ s.titulo }}</h3>
                <p class="text-sm text-gris-medio leading-relaxed line-clamp-3">{{ s.descripcion }}</p>
              </div>
              <div class="px-5 py-3 bg-niebla/40 border-t border-slate-200">
                <Link
                  :href="estaAutenticado ? route('dashboard') : route('login')"
                  class="inline-flex items-center px-3.5 py-2 text-xs font-bold rounded-xl shadow-sm"
                  :class="estaAutenticado ? 'bg-principal text-white hover:bg-slate-800' : 'bg-acento text-principal hover:bg-yellow-400'"
                >
                  {{ estaAutenticado ? 'Solicitar desde mi panel' : 'Inicia sesión para solicitar' }}
                </Link>
              </div>
            </article>
          </div>
        </section>

        <!-- ============ RESEÑAS ============ -->
        <section v-if="resenas.length" class="space-y-4">
          <h2 class="text-lg font-extrabold text-principal">Reseñas de clientes</h2>
          <ul class="space-y-3">
            <li v-for="r in resenas" :key="r.id" class="bg-white rounded-2xl border border-slate-200 p-4">
              <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-bold text-principal">{{ r.autor }}</span>
                <span class="text-acento text-sm tracking-wider" :aria-label="`${r.calificacion} de 5 estrellas`">{{ estrellas(r.calificacion) }}</span>
              </div>
              <p v-if="r.comentario" class="mt-1.5 text-sm text-grafito whitespace-pre-line">{{ r.comentario }}</p>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </component>
</template>
