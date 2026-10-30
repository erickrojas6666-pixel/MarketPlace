<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    categorias: Array,
    servicios: Array,
});
</script>

<template>
    <Head title="Catálogo de Servicios - OficiosYa" />

    <!-- Si el usuario está autenticado usa el Layout de Breeze, si no, usa una estructura limpia -->
    <component :is="$page.props.auth.user ? AuthenticatedLayout : 'div'">
        
        <!-- Header / Bar de Navegación para usuarios invitados (no logueados) -->
        <header v-if="!$page.props.auth.user" class="bg-white dark:bg-gray-800 shadow">
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

        <!-- Contenido Principal -->
        <div class="py-8 bg-gray-100 dark:bg-gray-900 min-h-screen">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
                
                <!-- Hero / Buscador -->
                <div class="bg-indigo-700 dark:bg-indigo-900 rounded-2xl p-8 text-white shadow-lg text-center">
                    <h2 class="text-3xl font-extrabold sm:text-4xl">
                        Encuentra a tu profesional de confianza
                    </h2>
                    <p class="mt-2 text-indigo-100">
                        Plomeros, electricistas, cerrajeros y más profesionales listos para atenderte en tu zona.
                    </p>
                </div>

                <!-- Categorías -->
                <section>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">
                        Categorías Destacadas
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                        <div 
                            v-for="categoria in categorias" 
                            :key="categoria.id" 
                            class="bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm hover:shadow-md transition border border-gray-200 dark:border-gray-700 cursor-pointer text-center"
                        >
                            <span class="block font-bold text-indigo-600 dark:text-indigo-400 text-sm sm:text-base">
                                {{ categoria.nombre }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">
                                {{ categoria.descripcion }}
                            </span>
                        </div>
                    </div>
                </section>

                <!-- Listado de Servicios -->
                <section>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">
                        Servicios Recientes
                    </h3>
                    
                    <div v-if="servicios.length === 0" class="bg-white dark:bg-gray-800 p-8 rounded-xl text-center text-gray-500 dark:text-gray-400 shadow-sm">
                        No hay servicios publicados disponibles en este momento.
                    </div>

                    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div 
                            v-for="servicio in servicios" 
                            :key="servicio.id" 
                            class="bg-white dark:bg-gray-800 rounded-xl shadow-sm hover:shadow-md transition border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col justify-between"
                        >
                            <div class="p-6">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-semibold uppercase tracking-wider text-indigo-500 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-950 px-2.5 py-0.5 rounded-full">
                                        {{ servicio.categoria?.nombre || 'General' }}
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
                                    Por: <strong class="text-gray-700 dark:text-gray-300">{{ servicio.perfiles_prestador?.user?.nombre || 'Oficial' }}</strong>
                                </span>
                                <button class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition shadow-sm">
                                    Ver Solicitud
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

            </div>
        </div>
    </component>
</template>