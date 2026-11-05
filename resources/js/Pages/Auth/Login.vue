<script setup>
import Checkbox from '@/Components/Checkbox.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Iniciar sesión - OficiosYa" />

        <div class="mb-6 text-center">
            <h2 class="text-2xl font-extrabold text-principal tracking-tight">Bienvenido de nuevo</h2>
            <p class="text-xs text-gris-medio mt-1">Ingresa a tu cuenta para gestionar tus solicitudes</p>
        </div>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600 bg-green-50 p-3 rounded-xl border border-green-200">
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

            <div>
                <InputLabel for="password" value="Contraseña" class="text-principal font-bold text-xs uppercase tracking-wider" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1.5 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                    placeholder="••••••••"
                />
                <InputError class="mt-2 text-xs" :message="form.errors.password" />
            </div>

            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center cursor-pointer">
                    <Checkbox name="remember" v-model:checked="form.remember" class="rounded border-slate-300 text-principal focus:ring-principal" />
                    <span class="ms-2 text-xs text-gris-medio font-medium">Recordarme</span>
                </label>

                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-xs text-principal hover:text-acento font-semibold transition-colors"
                >
                    ¿Olvidaste tu contraseña?
                </Link>
            </div>

            <div class="pt-2">
                <button
                    class="w-full py-3 px-4 bg-principal text-white font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95 text-sm flex justify-center items-center gap-2"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    <span>Iniciar sesión</span>
                </button>
            </div>

            <div class="mt-6 pt-4 border-t border-slate-100 text-center text-xs text-gris-medio">
                ¿No tienes cuenta?
                <Link :href="route('register')" class="font-bold text-principal hover:text-acento ms-1 transition-colors">
                    Regístrate aquí
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>