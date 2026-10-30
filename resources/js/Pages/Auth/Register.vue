<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    nombre: '',
    email: '',
    password: '',
    password_confirmation: '',
    tipo_usuario: 'cliente', // Opción por defecto
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Registro - OficiosYa" />

        <form @submit.prevent="submit">
            <!-- Selección de Tipo de Usuario -->
            <div class="mb-4">
                <InputLabel value="¿Cómo deseas usar OficiosYa?" />
                <div class="grid grid-cols-2 gap-4 mt-2">
                    <label 
                        class="flex flex-col items-center justify-center p-3 border rounded-lg cursor-pointer transition"
                        :class="form.tipo_usuario === 'cliente' ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400'"
                    >
                        <input type="radio" v-model="form.tipo_usuario" value="cliente" class="sr-only" />
                        <span>Quiero contratar</span>
                        <span class="text-xs font-normal text-gray-500 mt-1">Busco profesionales</span>
                    </label>

                    <label 
                        class="flex flex-col items-center justify-center p-3 border rounded-lg cursor-pointer transition"
                        :class="form.tipo_usuario === 'prestador' ? 'border-indigo-600 bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 font-bold' : 'border-gray-300 dark:border-gray-700 text-gray-600 dark:text-gray-400'"
                    >
                        <input type="radio" v-model="form.tipo_usuario" value="prestador" class="sr-only" />
                        <span>Quiero ofrecer oficios</span>
                        <span class="text-xs font-normal text-gray-500 mt-1">Soy profesional / técnico</span>
                    </label>
                </div>
                <InputError class="mt-2" :message="form.errors.tipo_usuario" />
            </div>

            <!-- Nombre -->
            <div>
                <InputLabel for="nombre" value="Nombre Completo" />
                <TextInput
                    id="nombre"
                    type="text"
                    class="mt-1 block w-full"
                    v-model="form.nombre"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-2" :message="form.errors.nombre" />
            </div>

            <!-- Email -->
            <div class="mt-4">
                <InputLabel for="email" value="Correo Electrónico" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <!-- Contraseña -->
            <div class="mt-4">
                <InputLabel for="password" value="Contraseña" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <!-- Confirmar Contraseña -->
            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirmar Contraseña" />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <Link
                    :href="route('login')"
                    class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                    ¿Ya tienes cuenta?
                </Link>

                <PrimaryButton class="ms-4" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Registrarse
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>