<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    maxWidthClass: {
        type: String,
        default: 'sm:max-w-md',
    },
});

const handleImageError = (e) => {
    if (!e.target.dataset.tried) {
        e.target.dataset.tried = 'true';
        e.target.src = '/images/logo.jpg';
    }
};
</script>

<template>
    <div class="min-h-screen bg-niebla flex flex-col justify-center items-center px-4 sm:px-6 lg:px-8 py-6 font-sans text-grafito">
        <!-- Logo -->
        <div class="mb-4">
            <Link href="/" class="flex items-center">
                <div class="bg-white px-4 py-2 rounded-2xl shadow-md border border-slate-200/80 hover:scale-105 transition-transform duration-200">
                    <img 
                        src="/images/logo.png" 
                        @error="handleImageError"
                        alt="OficiosYa Logo" 
                        class="h-10 w-auto object-contain"
                    />
                </div>
            </Link>
        </div>

        <!-- Tarjeta del formulario con ancho adaptable -->
        <div :class="['w-full bg-white p-6 sm:p-8 rounded-3xl shadow-xl border-2 border-slate-200/90 relative transition-all duration-200', maxWidthClass]">
            <slot />
        </div>

        <!-- Enlace inferior -->
        <div class="mt-4 text-center text-xs font-semibold text-gris-medio">
            <Link href="/" class="hover:text-principal transition-colors">
                ← Volver al inicio
            </Link>
        </div>
    </div>
</template>