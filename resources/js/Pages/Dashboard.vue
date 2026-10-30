<script setup>
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  perfil: Object,
  servicios: { type: Array, default: () => [] },
  pedidos: { type: Array, default: () => [] },
  categorias: { type: Array, default: () => [] },
  misPedidos: { type: Array, default: () => [] },
})

const user = computed(() => usePage().props.auth?.user)
</script>

<template>
  <Head title="Dashboard" />

  <AuthenticatedLayout>
    <div class="py-8 max-w-5xl mx-auto px-4">

      <!-- PRESTADOR -->
      <div v-if="user?.esPrestador">
        <h1 class="text-2xl font-bold mb-4">Panel de prestador</h1>

        <h2 class="font-semibold mt-6 mb-2">Mis servicios ({{ servicios.length }})</h2>
        <p v-if="!servicios.length" class="text-gray-500">Aún no has publicado servicios.</p>
        <ul v-else class="space-y-2">
          <li v-for="s in servicios" :key="s.id" class="p-3 bg-white rounded shadow">
            {{ s.titulo ?? s.nombre ?? ('Servicio #' + s.id) }}
          </li>
        </ul>

        <h2 class="font-semibold mt-6 mb-2">Pedidos recibidos ({{ pedidos.length }})</h2>
        <p v-if="!pedidos.length" class="text-gray-500">No tienes pedidos todavía.</p>
      </div>

      <!-- CLIENTE -->
      <div v-else>
        <h1 class="text-2xl font-bold mb-4">Bienvenido</h1>

        <h2 class="font-semibold mt-6 mb-2">Mis pedidos ({{ misPedidos.length }})</h2>
        <p v-if="!misPedidos.length" class="text-gray-500">Aún no has solicitado servicios.</p>

        <h2 class="font-semibold mt-6 mb-2">Categorías</h2>
        <div class="flex flex-wrap gap-2">
          <span v-for="c in categorias" :key="c.id" class="px-3 py-1 bg-gray-100 rounded">
            {{ c.nombre }}
          </span>
        </div>
      </div>

    </div>
  </AuthenticatedLayout>
</template>