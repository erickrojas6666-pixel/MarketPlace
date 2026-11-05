<script setup>
import { ref } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  categorias: { type: Array, default: () => [] },
})

const categoriaEditando = ref(null)

// Formulario Crear / Editar
const form = useForm({
  nombre: '',
  descripcion: '',
})

const abrirEdicion = (cat) => {
  categoriaEditando.value = cat
  form.nombre = cat.nombre
  form.descripcion = cat.descripcion || ''
}

const cancelarEdicion = () => {
  categoriaEditando.value = null
  form.reset()
}

const guardarCategoria = () => {
  if (categoriaEditando.value) {
    form.put(route('admin.categorias.update', categoriaEditando.value.id), {
      preserveScroll: true,
      onSuccess: () => cancelarEdicion(),
    })
  } else {
    form.post(route('admin.categorias.store'), {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    })
  }
}

const eliminarCategoria = (cat) => {
  if (confirm(`¿Estás seguro de eliminar la categoría "${cat.nombre}"?`)) {
    router.delete(route('admin.categorias.destroy', cat.id), {
      preserveScroll: true,
    })
  }
}

/* ---------- Ayudas visuales ---------- */
const textoServicios = (n) => {
  const total = n ?? 0
  return `${total} ${total === 1 ? 'servicio' : 'servicios'}`
}

const iconos = {
  categorias: 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z',
  volver: 'M11 17l-5-5m0 0l5-5m-5 5h12',
  mas: 'M12 4v16m8-8H4',
  editar: 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
  basura: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
}
</script>

<template>
  <Head title="Gestión de Categorías" />

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
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.categorias" />
              </svg>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Categorías</h1>
              <p class="text-sm text-niebla/80 mt-1">Organiza las áreas de servicio que ve el público.</p>
            </div>
          </div>

          <Link
            :href="route('admin.dashboard')"
            class="inline-flex items-center gap-2 self-start md:self-auto px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white text-sm font-bold rounded-xl border border-white/20 transition-all duration-200 active:scale-95"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.volver" />
            </svg>
            Volver al Dashboard
          </Link>
        </div>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">

        <!-- ============ FORMULARIO CREAR / EDITAR ============ -->
        <div
          class="bg-white rounded-3xl p-6 border-2 shadow-md space-y-5 lg:col-span-1 lg:sticky lg:top-28 transition-colors duration-200"
          :class="categoriaEditando ? 'border-acento/70' : 'border-slate-200/90'"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-11 h-11 shrink-0 rounded-xl flex items-center justify-center"
              :class="categoriaEditando ? 'bg-acento text-principal' : 'bg-niebla text-principal'"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="categoriaEditando ? iconos.editar : iconos.mas" />
              </svg>
            </div>
            <div class="min-w-0">
              <h2 class="font-extrabold text-lg text-principal tracking-tight">
                {{ categoriaEditando ? 'Editar categoría' : 'Nueva categoría' }}
              </h2>
              <p class="text-xs text-gris-medio truncate">
                {{ categoriaEditando ? 'Modificando "' + categoriaEditando.nombre + '"' : 'Agrega un área de servicio' }}
              </p>
            </div>
          </div>

          <form @submit.prevent="guardarCategoria" class="space-y-4">
            <div>
              <label class="block text-principal font-bold text-xs uppercase tracking-wider mb-1.5">Nombre</label>
              <input
                v-model="form.nombre"
                type="text"
                required
                placeholder="Ej: Plomería y Gas"
                class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
              />
              <span v-if="form.errors.nombre" class="block mt-1 text-xs font-semibold text-red-600">{{ form.errors.nombre }}</span>
            </div>

            <div>
              <label class="block text-principal font-bold text-xs uppercase tracking-wider mb-1.5">Descripción</label>
              <textarea
                v-model="form.descripcion"
                rows="3"
                placeholder="Breve descripción..."
                class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
              ></textarea>
              <span v-if="form.errors.descripcion" class="block mt-1 text-xs font-semibold text-red-600">{{ form.errors.descripcion }}</span>
            </div>

            <div class="flex gap-3 pt-2">
              <button
                type="submit"
                :disabled="form.processing"
                class="flex-1 py-2.5 px-4 bg-principal text-white text-sm font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
              >
                {{ categoriaEditando ? 'Actualizar' : 'Guardar' }}
              </button>

              <button
                v-if="categoriaEditando"
                type="button"
                @click="cancelarEdicion"
                class="px-4 py-2.5 bg-white hover:bg-slate-50 text-grafito text-sm font-bold rounded-xl border-2 border-slate-200 transition active:scale-95"
              >
                Cancelar
              </button>
            </div>
          </form>
        </div>

        <!-- ============ LISTA DE CATEGORÍAS ============ -->
        <div class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md overflow-hidden lg:col-span-2 min-w-0">
          <div class="p-6 flex items-center justify-between gap-4 border-b border-slate-100">
            <div class="min-w-0">
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Categorías registradas</h2>
              <p class="text-xs text-gris-medio mt-0.5">Áreas que aparecen en la landing y el catálogo</p>
            </div>
            <span class="min-w-8 h-8 px-3 shrink-0 rounded-full bg-niebla text-principal text-sm font-extrabold flex items-center justify-center border border-principal/15">
              {{ categorias.length }}
            </span>
          </div>

          <!-- Vacío -->
          <div v-if="!categorias.length" class="py-12 flex flex-col items-center text-center">
            <div class="w-14 h-14 rounded-2xl bg-niebla text-principal flex items-center justify-center mb-3">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.categorias" />
              </svg>
            </div>
            <p class="text-sm font-bold text-principal">Aún no hay categorías</p>
            <p class="text-xs text-gris-medio mt-0.5">Crea la primera desde el formulario.</p>
          </div>

          <!-- Filas (sin tabla: todo se adapta al ancho del contenedor) -->
          <ul v-else class="divide-y divide-slate-100">
            <li
              v-for="c in categorias"
              :key="c.id"
              class="px-6 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-niebla/30 transition-colors duration-150"
            >
              <!-- Nombre + descripción -->
              <div class="flex items-center gap-3 min-w-0 flex-1">
                <div class="w-11 h-11 shrink-0 rounded-xl bg-niebla border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.categorias" />
                  </svg>
                </div>
                <div class="min-w-0">
                  <p class="font-bold text-principal truncate">{{ c.nombre }}</p>
                  <p class="text-xs text-gris-medio truncate">{{ c.descripcion || 'Sin descripción' }}</p>
                </div>
              </div>

              <!-- Servicios + acciones -->
              <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                <span class="inline-flex items-center px-3 py-1 rounded-full bg-niebla text-principal text-xs font-bold border border-principal/15 whitespace-nowrap">
                  {{ textoServicios(c.servicios_count) }}
                </span>

                <div class="flex gap-2">
                  <button
                    @click="abrirEdicion(c)"
                    title="Editar"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-niebla text-principal text-xs font-bold rounded-xl border-2 border-slate-200 hover:border-principal/40 transition active:scale-95"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.editar" />
                    </svg>
                    <span class="hidden xl:inline">Editar</span>
                  </button>
                  <button
                    @click="eliminarCategoria(c)"
                    title="Eliminar"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white hover:bg-red-50 text-red-600 text-xs font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition active:scale-95"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.basura" />
                    </svg>
                    <span class="hidden xl:inline">Eliminar</span>
                  </button>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>