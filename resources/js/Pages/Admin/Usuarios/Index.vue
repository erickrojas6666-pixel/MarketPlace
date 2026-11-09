<script setup>
import { ref, computed } from 'vue'
import { Head, useForm, router, Link, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  usuarios: Object,
  roles: { type: Array, default: () => [] },
})

const page = usePage()
const yo = computed(() => page.props.auth.user)
const soySuperadmin = computed(() => (yo.value?.roles || []).includes('superadmin'))

const usuarioEditando = ref(null)

// El campo se llama "nombre" (igual que la columna y la validación del backend).
// Por seguridad el rol por defecto es "cliente", no el primero de la lista.
const rolPorDefecto = props.roles.includes('cliente') ? 'cliente' : props.roles[0] || ''

const form = useForm({
  nombre: '',
  email: '',
  password: '',
  role: rolPorDefecto,
})

/* Reglas que el backend también aplica (aquí solo se refleja en la interfaz). */
const esMiUsuario = (u) => u.id === yo.value?.id
const esSuperadminUsuario = (u) => (u.roles || []).some((r) => r.name === 'superadmin')
const puedeGestionar = (u) => !esSuperadminUsuario(u) || soySuperadmin.value
const editandoMiUsuario = computed(() => !!usuarioEditando.value && esMiUsuario(usuarioEditando.value))

const abrirEdicion = (u) => {
  usuarioEditando.value = u
  form.clearErrors()
  form.nombre = u.nombre
  form.email = u.email
  form.password = ''
  form.role = u.roles && u.roles.length ? u.roles[0].name : ''
}

const cancelarEdicion = () => {
  usuarioEditando.value = null
  form.reset()
  form.clearErrors()
}

// Una regla de negocio rechazada (último admin, jerarquía...) vuelve como flash "error"
// con respuesta exitosa: en ese caso el formulario NO debe cerrarse ni limpiarse.
const huboErrorDeNegocio = (pagina) => !!pagina.props.flash?.error

const guardarUsuario = () => {
  if (usuarioEditando.value) {
    form.put(route('admin.usuarios.update', usuarioEditando.value.id), {
      preserveScroll: true,
      onSuccess: (pagina) => {
        if (!huboErrorDeNegocio(pagina)) cancelarEdicion()
      },
    })
  } else {
    form.post(route('admin.usuarios.store'), {
      preserveScroll: true,
      onSuccess: (pagina) => {
        if (!huboErrorDeNegocio(pagina)) form.reset()
      },
    })
  }
}

const eliminarUsuario = (u) => {
  if (confirm(`¿Estás seguro de eliminar al usuario "${nombreDe(u)}"?`)) {
    router.delete(route('admin.usuarios.destroy', u.id), { preserveScroll: true })
  }
}

/* ---------- Ayudas visuales ---------- */
const total = computed(() => props.usuarios?.total || props.usuarios?.data?.length || 0)

const nombreDe = (u) => u?.nombre || u?.email || 'Sin nombre'

const iniciales = (texto) => {
  const partes = String(texto || '?').trim().split(/\s+/)
  return ((partes[0]?.[0] || '') + (partes[1]?.[0] || '')).toUpperCase() || '?'
}

const claseRol = (nombre) => {
  const r = String(nombre || '').toLowerCase()
  if (r === 'admin' || r === 'superadmin') return 'bg-principal text-white border-principal'
  if (r === 'prestador') return 'bg-acento/20 text-principal border-acento/60'
  if (r === 'cliente') return 'bg-niebla text-principal border-principal/15'
  return 'bg-slate-100 text-grafito border-slate-300'
}

const iconos = {
  usuarios: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
  volver: 'M11 17l-5-5m0 0l5-5m-5 5h12',
  mas: 'M12 4v16m8-8H4',
  editar: 'M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z',
  basura: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
}
</script>

<template>
  <Head title="Gestión de Usuarios y Roles" />

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
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.usuarios" />
              </svg>
            </div>
            <div>
              <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Usuarios y Roles</h1>
              <p class="text-sm text-niebla/80 mt-1">Crea cuentas y asigna roles a los miembros de la plataforma.</p>
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
          :class="usuarioEditando ? 'border-acento/70' : 'border-slate-200/90'"
        >
          <div class="flex items-center gap-3">
            <div
              class="w-11 h-11 rounded-xl flex items-center justify-center"
              :class="usuarioEditando ? 'bg-acento text-principal' : 'bg-niebla text-principal'"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="usuarioEditando ? iconos.editar : iconos.mas" />
              </svg>
            </div>
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">
                {{ usuarioEditando ? 'Editar usuario' : 'Nuevo usuario' }}
              </h2>
              <p class="text-xs text-gris-medio">
                {{ usuarioEditando ? 'Modificando a ' + nombreDe(usuarioEditando) : 'Completa los datos de la cuenta' }}
              </p>
            </div>
          </div>

          <form @submit.prevent="guardarUsuario" class="space-y-4">
            <div>
              <label class="block text-principal font-bold text-xs uppercase tracking-wider mb-1.5">Nombre</label>
              <input
                v-model="form.nombre"
                type="text"
                required
                placeholder="Ej. Juan Pérez"
                class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
              />
              <span v-if="form.errors.nombre" class="block mt-1 text-xs font-semibold text-red-600">{{ form.errors.nombre }}</span>
            </div>

            <div>
              <label class="block text-principal font-bold text-xs uppercase tracking-wider mb-1.5">Correo electrónico</label>
              <input
                v-model="form.email"
                type="email"
                required
                placeholder="ejemplo@correo.com"
                class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
              />
              <span v-if="form.errors.email" class="block mt-1 text-xs font-semibold text-red-600">{{ form.errors.email }}</span>
            </div>

            <div>
              <label class="block text-principal font-bold text-xs uppercase tracking-wider mb-1.5">
                {{ usuarioEditando ? 'Contraseña (opcional)' : 'Contraseña' }}
              </label>
              <input
                v-model="form.password"
                type="password"
                :required="!usuarioEditando"
                :placeholder="usuarioEditando ? 'Déjala vacía para no cambiarla' : 'Mínimo 8 caracteres'"
                class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
              />
              <span v-if="form.errors.password" class="block mt-1 text-xs font-semibold text-red-600">{{ form.errors.password }}</span>
            </div>

            <div>
              <label class="block text-principal font-bold text-xs uppercase tracking-wider mb-1.5">Rol asignado</label>
              <select
                v-model="form.role"
                required
                :disabled="editandoMiUsuario"
                class="block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 capitalize disabled:bg-slate-100 disabled:text-slate-500"
              >
                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
              </select>
              <span v-if="editandoMiUsuario" class="block mt-1 text-xs text-gris-medio">No puedes cambiar tu propio rol.</span>
              <span v-if="form.errors.role" class="block mt-1 text-xs font-semibold text-red-600">{{ form.errors.role }}</span>
            </div>

            <div class="flex gap-3 pt-2">
              <button
                type="submit"
                :disabled="form.processing"
                class="flex-1 py-2.5 px-4 bg-principal text-white text-sm font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
              >
                {{ usuarioEditando ? 'Actualizar' : 'Guardar usuario' }}
              </button>
              <button
                v-if="usuarioEditando"
                type="button"
                @click="cancelarEdicion"
                class="px-4 py-2.5 bg-white hover:bg-slate-50 text-grafito text-sm font-bold rounded-xl border-2 border-slate-200 transition active:scale-95"
              >
                Cancelar
              </button>
            </div>
          </form>
        </div>

        <!-- ============ LISTADO DE USUARIOS ============ -->
        <div class="bg-white rounded-3xl border-2 border-slate-200/90 shadow-md overflow-hidden lg:col-span-2">
          <div class="p-6 flex items-center justify-between">
            <div>
              <h2 class="font-extrabold text-lg text-principal tracking-tight">Usuarios registrados</h2>
              <p class="text-xs text-gris-medio mt-0.5">Cuentas activas en la plataforma</p>
            </div>
            <span class="min-w-8 h-8 px-3 rounded-full bg-niebla text-principal text-sm font-extrabold flex items-center justify-center border border-principal/15">
              {{ total }}
            </span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-grafito">
              <thead class="bg-niebla/60 text-xs uppercase tracking-wider font-bold text-principal">
                <tr>
                  <th class="px-6 py-3">Usuario</th>
                  <th class="px-6 py-3">Rol</th>
                  <th class="px-6 py-3 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="u in usuarios.data" :key="u.id" class="hover:bg-niebla/30 transition-colors duration-150">
                  <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                      <div class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-br from-principal to-[#1d4a8a] text-white font-extrabold text-xs flex items-center justify-center shadow-sm">
                        {{ iniciales(nombreDe(u)) }}
                      </div>
                      <div class="min-w-0">
                        <p class="font-bold text-principal truncate">
                          {{ nombreDe(u) }}
                          <span v-if="esMiUsuario(u)" class="ml-1 text-[10px] font-bold uppercase tracking-wider text-acento">(tú)</span>
                        </p>
                        <p class="text-xs text-gris-medio truncate">{{ u.email }}</p>
                      </div>
                    </div>
                  </td>

                  <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-1.5">
                      <span
                        v-for="r in u.roles"
                        :key="r.id"
                        class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider border"
                        :class="claseRol(r.name)"
                      >
                        {{ r.name }}
                      </span>
                      <span v-if="!u.roles || !u.roles.length" class="text-xs text-gris-medio">Sin rol</span>
                    </div>
                  </td>

                  <td class="px-6 py-4">
                    <div class="flex justify-end gap-2">
                      <button
                        :disabled="!puedeGestionar(u)"
                        :title="!puedeGestionar(u) ? 'Solo un superadministrador puede modificar esta cuenta' : ''"
                        @click="abrirEdicion(u)"
                        class="disabled:opacity-40 disabled:cursor-not-allowed inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-niebla text-principal text-xs font-bold rounded-xl border-2 border-slate-200 hover:border-principal/40 transition active:scale-95"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.editar" />
                        </svg>
                        Editar
                      </button>
                      <button
                        v-if="!esMiUsuario(u) && puedeGestionar(u)"
                        @click="eliminarUsuario(u)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-red-50 text-red-600 text-xs font-bold rounded-xl border-2 border-red-200 hover:border-red-300 transition active:scale-95"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.basura" />
                        </svg>
                        Eliminar
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-if="!usuarios.data.length">
                  <td colspan="3" class="px-6 py-10 text-center text-sm text-gris-medio">No hay usuarios registrados.</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginación (el backend entrega 10 por página) -->
          <nav v-if="usuarios.last_page > 1" class="flex flex-wrap items-center justify-center gap-1.5 p-4 border-t border-slate-100" aria-label="Paginación">
            <template v-for="(enlace, i) in usuarios.links" :key="i">
              <Link
                v-if="enlace.url"
                :href="enlace.url"
                preserve-scroll
                class="min-w-9 px-3 py-1.5 rounded-lg text-xs font-bold text-center border transition"
                :class="enlace.active ? 'bg-principal text-white border-principal' : 'bg-white text-principal border-slate-200 hover:bg-niebla'"
                v-html="enlace.label"
              />
              <span
                v-else
                class="min-w-9 px-3 py-1.5 rounded-lg text-xs font-bold text-center border border-slate-100 text-slate-300"
                v-html="enlace.label"
              />
            </template>
          </nav>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>