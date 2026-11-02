<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    metrics: Object,
    prestadoresPendientesLista: Array,
    ultimosPedidos: Array,
});
</script>

<template>
    <Head title="Panel Administrativo" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Panel de Administración - OficiosYa
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Métricas Rápidas -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                        <p class="text-sm text-gray-500">Total Usuarios</p>
                        <p class="text-3xl font-bold dark:text-white">{{ metrics.totalUsuarios }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                        <p class="text-sm text-gray-500">Prestadores Pendientes</p>
                        <p class="text-3xl font-bold text-amber-500">{{ metrics.prestadoresPendientes }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                        <p class="text-sm text-gray-500">Servicios Activos</p>
                        <p class="text-3xl font-bold dark:text-white">{{ metrics.totalServicios }}</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                        <p class="text-sm text-gray-500">Total Pedidos</p>
                        <p class="text-3xl font-bold dark:text-white">{{ metrics.totalPedidos }}</p>
                    </div>
                </div>

                <!-- Lista de Verificaciones Pendientes -->
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow">
                    <h3 class="text-lg font-bold mb-4 dark:text-white">Prestadores por Verificar</h3>
                    <div v-if="prestadoresPendientesLista.length === 0" class="text-gray-500">
                        No hay solicitudes pendientes de verificación.
                    </div>
                    <ul v-else class="divide-y divide-gray-200 dark:divide-gray-700">
                        <li v-for="item in prestadoresPendientesLista" :key="item.id" class="py-3 flex justify-between items-center">
                            <div>
                                <p class="font-semibold dark:text-white">{{ item.user?.nombre }}</p>
                                <p class="text-xs text-gray-500">{{ item.user?.email }}</p>
                            </div>
                            <span class="px-3 py-1 text-xs rounded-full bg-amber-100 text-amber-800">Pendiente</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>