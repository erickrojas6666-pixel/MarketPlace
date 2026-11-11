<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};

const iconos = {
    sobre: 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    volver: 'M11 17l-5-5m0 0l5-5m-5 5h12',
};
</script>

<template>
    <GuestLayout>
        <Head title="Recuperar contraseña - OficiosYa" />

        <div class="mb-6 text-center space-y-3">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-niebla border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.sobre" />
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-extrabold text-principal tracking-tight">¿Olvidaste tu contraseña?</h2>
                <p class="text-xs text-gris-medio mt-1 leading-relaxed">
                    No te preocupes. Escribe tu correo electrónico y te enviaremos un enlace para que elijas una nueva.
                </p>
            </div>
        </div>

        <div
            v-if="status"
            class="mb-4 text-sm font-medium text-green-700 bg-green-50 p-3 rounded-xl border border-green-200"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="email" value="Correo electrónico" class="text-principal font-bold text-xs uppercase tracking-wider" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1.5 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    placeholder="ejemplo@correo.com"
                />
                <InputError class="mt-2 text-xs" :message="form.errors.email" />
            </div>

            <div class="pt-2">
                <button
                    class="w-full py-3 px-4 bg-principal text-white font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95 text-sm flex justify-center items-center gap-2"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span>Enviar enlace de recuperación</span>
                </button>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-gris-medio">
                <Link
                    :href="route('login')"
                    class="inline-flex items-center gap-1.5 font-bold text-principal hover:text-acento transition-colors"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.volver" />
                    </svg>
                    Volver a iniciar sesión
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>