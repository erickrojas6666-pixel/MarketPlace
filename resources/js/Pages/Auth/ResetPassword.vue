<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const iconos = {
    llave: 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z',
};
</script>

<template>
    <GuestLayout>
        <Head title="Nueva contraseña - OficiosYa" />

        <div class="mb-6 text-center space-y-3">
            <div class="w-14 h-14 mx-auto rounded-2xl bg-niebla border border-principal/15 text-principal flex items-center justify-center shadow-sm">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.llave" />
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-extrabold text-principal tracking-tight">Crea una nueva contraseña</h2>
                <p class="text-xs text-gris-medio mt-1">Elige una contraseña segura para volver a entrar a tu cuenta.</p>
            </div>
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

            <div>
                <InputLabel for="password" value="Nueva contraseña" class="text-principal font-bold text-xs uppercase tracking-wider" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    placeholder="Mínimo 8 caracteres"
                />
                <InputError class="mt-2 text-xs" :message="form.errors.password" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar contraseña"
                    class="text-principal font-bold text-xs uppercase tracking-wider"
                />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1.5 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Repite tu contraseña"
                />
                <InputError class="mt-2 text-xs" :message="form.errors.password_confirmation" />
            </div>

            <div class="pt-2">
                <button
                    class="w-full py-3 px-4 bg-principal text-white font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95 text-sm flex justify-center items-center gap-2"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span>Restablecer contraseña</span>
                </button>
            </div>
        </form>
    </GuestLayout>
</template>