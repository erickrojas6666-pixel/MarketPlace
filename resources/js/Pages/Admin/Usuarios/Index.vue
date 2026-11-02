<script setup>
import { ref } from 'vue'
import { Head, useForm, router, Link } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  usuarios: Object,
  roles: { type: Array, default: () => [] },
})

const usuarioEditando = ref(null)

const form = useForm({
  name: '',
  email: '',
  password: '',
  role: props.roles[0] || 'admin',
})

const abrirEdicion = (u) => {
  usuarioEditando.value = u
  form.name = u.name || u.nombre
  form.email = u.email
  form.password = ''
  form.role = u.roles && u.roles.length ? u.roles[0].name : ''
}

const cancelarEdicion = () => {
  usuarioEditando.value = null
  form.reset()
}

const guardarUsuario = () => {
  if (usuarioEditando.value) {
    form.put(route('admin.usuarios.update', usuarioEditando.value.id), {
      preserveScroll: true,
      onSuccess: () => cancelarEdicion(),
    })
  } else {
    form.post(route('admin.usuarios.store'), {
      preserveScroll: true,
      onSuccess: () => form.reset(),
    })
  }
}

const eliminarUsuario = (u) => {
  if (confirm(`¿Estás seguro de eliminar al usuario "${u.name || u.email}"?`)) {
    router.delete(route('admin.usuarios.destroy', u.id), { preserveScroll: true })
  }
}
</script>

<template>
  <Head title="Gestión de Usuarios y Roles" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-7xl mx-auto px-4 space-y-6">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold dark:text-white">Gestión de Usuarios y Roles</h1>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Crea y asigna roles administrativos a los miembros del equipo.</p>
        </div>
        <Link :href="route('admin.dashboard')" class="px-3 py-1.5 bg-gray-600 text-white text-xs font-bold rounded shadow">
          ← Volver al Dashboard
        </Link>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- FORMULARIO CREAR / EDITAR -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4 md:col-span-1 h-fit">
          <h2 class="font-semibold text-lg dark:text-white">
            {{ usuarioEditando ? 'Editar Usuario' : 'Nuevo Usuario' }}
          </h2>

          <form @submit.prevent="guardarUsuario" class="space-y-3">
            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Nombre</label>
              <input v-model="form.name" type="text" required class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white" />
              <span v-if="form.errors.name" class="text-xs text-red-500">{{ form.errors.name }}</span>
            </div>

            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Correo Electrónico</label>
              <input v-model="form.email" type="email" required class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white" />
              <span v-if="form.errors.email" class="text-xs text-red-500">{{ form.errors.email }}</span>
            </div>

            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">
                {{ usuarioEditando ? 'Contraseña (opcional)' : 'Contraseña' }}
              </label>
              <input v-model="form.password" type="password" :required="!usuarioEditando" class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white" />
              <span v-if="form.errors.password" class="text-xs text-red-500">{{ form.errors.password }}</span>
            </div>

            <div>
              <label class="block text-xs font-bold dark:text-gray-200 mb-1">Rol Asignado</label>
              <select v-model="form.role" required class="w-full text-sm rounded border-gray-300 dark:bg-gray-700 dark:text-white capitalize">
                <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
              </select>
              <span v-if="form.errors.role" class="text-xs text-red-500">{{ form.errors.role }}</span>
            </div>

            <div class="flex gap-2 pt-2">
              <button type="submit" :disabled="form.processing" class="flex-1 py-2 bg-indigo-600 text-white text-xs font-bold rounded shadow">
                {{ usuarioEditando ? 'Actualizar' : 'Guardar Usuario' }}
              </button>
              <button v-if="usuarioEditando" type="button" @click="cancelarEdicion" class="px-3 py-2 bg-gray-200 text-gray-700 text-xs font-bold rounded">
                Cancelar
              </button>
            </div>
          </form>
        </div>

        <!-- LISTADO DE USUARIOS -->
        <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow space-y-4 md:col-span-2">
          <h2 class="font-semibold text-lg dark:text-white">Usuarios Registrados ({{ usuarios.total || usuarios.data.length }})</h2>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600 dark:text-gray-300">
              <thead class="bg-gray-100 dark:bg-gray-700 text-xs uppercase font-bold text-gray-700 dark:text-gray-200">
                <tr>
                  <th class="p-3">Usuario</th>
                  <th class="p-3">Rol</th>
                  <th class="p-3 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y dark:divide-gray-700">
                <tr v-for="u in usuarios.data" :key="u.id" class="hover:bg-gray-50 dark:hover:bg-gray-750">
                  <td class="p-3">
                    <p class="font-bold text-gray-900 dark:text-white">{{ u.name || u.nombre }}</p>
                    <p class="text-xs text-gray-500">{{ u.email }}</p>
                  </td>
                  <td class="p-3">
                    <span 
                      v-for="r in u.roles" 
                      :key="r.id"
                      class="px-2 py-0.5 rounded text-xs font-bold uppercase bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300"
                    >
                      {{ r.name }}
                    </span>
                  </td>
                  <td class="p-3 text-right space-x-2">
                    <button @click="abrirEdicion(u)" class="px-2.5 py-1 bg-yellow-500 text-white text-xs font-bold rounded">
                      Editar
                    </button>
                    <button @click="eliminarUsuario(u)" class="px-2.5 py-1 bg-red-600 text-white text-xs font-bold rounded">
                      Eliminar
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </AuthenticatedLayout>
</template>