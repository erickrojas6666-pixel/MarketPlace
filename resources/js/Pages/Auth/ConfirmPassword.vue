<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};

const iconos = {
    escudo: 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirmar contraseña - OficiosYa" />

        <div class="mb-6 text-center space-y-3">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-niebla border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.escudo" />
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-extrabold text-principal tracking-tight">Área segura</h2>
                <p class="text-xs text-gris-medio mt-1 leading-relaxed">
                    Esta es una zona protegida de la aplicación. Confirma tu contraseña antes de continuar.
                </p>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div>
                <InputLabel for="password" value="Contraseña" class="text-principal font-bold text-xs uppercase tracking-wider" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    autofocus
                    placeholder="••••••••"
                />
                <InputError class="mt-2 text-xs" :message="form.errors.password" />
            </div>

            <div class="pt-2">
                <button
                    class="w-full py-3 px-4 bg-principal text-white font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95 text-sm flex justify-center items-center gap-2"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span>Confirmar</span>
                </button>
            </div>
        </form>
    </GuestLayout>
</template>