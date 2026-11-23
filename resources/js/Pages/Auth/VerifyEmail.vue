<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);

const iconos = {
    sobreCheck: 'M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76',
};
</script>

<template>
    <GuestLayout>
        <Head title="Verifica tu correo - OficiosYa" />

        <div class="mb-6 text-center space-y-3">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-niebla border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.sobreCheck" />
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-extrabold text-principal tracking-tight">Verifica tu correo</h2>
                <p class="text-xs text-gris-medio mt-1 leading-relaxed">
                    ¡Gracias por registrarte! Antes de empezar, verifica tu correo electrónico haciendo clic en el
                    enlace que acabamos de enviarte. Si no lo recibiste, con gusto te enviamos otro.
                </p>
            </div>
        </div>

        <div
            v-if="verificationLinkSent"
            class="mb-4 text-sm font-medium text-green-700 bg-green-50 p-3 rounded-xl border border-green-200"
        >
            Te enviamos un nuevo enlace de verificación al correo que usaste al registrarte.
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <button
                class="w-full py-3 px-4 bg-principal text-white font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95 text-sm flex justify-center items-center gap-2"
                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                :disabled="form.processing"
            >
                <span>Reenviar correo de verificación</span>
            </button>

            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="w-full py-3 px-4 bg-white hover:bg-slate-50 text-grafito font-bold rounded-xl border-2 border-slate-200 transition-all duration-200 active:scale-95 text-sm"
            >
                Cerrar sesión
            </Link>
        </form>
    </GuestLayout>
</template>