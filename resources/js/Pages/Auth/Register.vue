<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'cliente',
    rol: 'cliente',
    tipo_usuario: 'cliente',
});

const seleccionarRol = (rol) => {
    form.role = rol;
    form.rol = rol;
    form.tipo_usuario = rol;
};

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout maxWidthClass="sm:max-w-md md:max-w-2xl lg:max-w-3xl">
        <Head title="Crear cuenta - OficiosYa" />

        <div class="mb-4 text-center">
            <h2 class="text-xl sm:text-2xl font-extrabold text-principal tracking-tight">Crea tu cuenta</h2>
            <p class="text-xs text-gris-medio mt-0.5">Elige cómo deseas usar la plataforma y completa tus datos</p>
        </div>

        <form @submit.prevent="submit" class="space-y-3.5">
            <!-- 1. Selector de Tipo de Usuario (Ancho completo) -->
            <div>
                <label class="block text-principal font-bold text-xs uppercase tracking-wider mb-1.5">
                    ¿Cómo deseas usar OficiosYa?
                </label>
                
                <div class="grid grid-cols-2 gap-3">
                    <!-- Opción: Cliente -->
                    <button
                        type="button"
                        @click="seleccionarRol('cliente')"
                        class="p-3 rounded-2xl border-2 text-left transition-all duration-200 flex flex-col justify-between relative group cursor-pointer"
                        :class="form.role === 'cliente' 
                            ? 'border-principal bg-principal text-white shadow-md' 
                            : 'border-slate-200 bg-white text-grafito hover:border-slate-300'"
                    >
                        <div class="flex items-center justify-between w-full mb-1.5">
                            <div 
                                class="w-7 h-7 rounded-lg flex items-center justify-center transition-colors"
                                :class="form.role === 'cliente' ? 'bg-white/15 text-acento' : 'bg-niebla text-principal'"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </div>

                            <span 
                                class="w-4 h-4 rounded-full flex items-center justify-center border text-[10px]"
                                :class="form.role === 'cliente' ? 'bg-acento border-acento text-principal font-black' : 'border-slate-300'"
                            >
                                <span v-if="form.role === 'cliente'">✓</span>
                            </span>
                        </div>

                        <div>
                            <span class="block font-bold text-xs leading-tight" :class="form.role === 'cliente' ? 'text-white' : 'text-principal'">
                                Quiero contratar
                            </span>
                            <span class="block text-[11px] mt-0.5" :class="form.role === 'cliente' ? 'text-niebla/80' : 'text-gris-medio'">
                                Busco profesionales
                            </span>
                        </div>
                    </button>

                    <!-- Opción: Prestador -->
                    <button
                        type="button"
                        @click="seleccionarRol('prestador')"
                        class="p-3 rounded-2xl border-2 text-left transition-all duration-200 flex flex-col justify-between relative group cursor-pointer"
                        :class="form.role === 'prestador' 
                            ? 'border-principal bg-principal text-white shadow-md' 
                            : 'border-slate-200 bg-white text-grafito hover:border-slate-300'"
                    >
                        <div class="flex items-center justify-between w-full mb-1.5">
                            <div 
                                class="w-8 h-8 rounded-xl flex items-center justify-center transition-colors"
                                :class="form.role === 'prestador' ? 'bg-white/15 text-acento' : 'bg-niebla text-principal'"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>

                            <span 
                                class="w-4 h-4 rounded-full flex items-center justify-center border text-[10px]"
                                :class="form.role === 'prestador' ? 'bg-acento border-acento text-principal font-black' : 'border-slate-300'"
                            >
                                <span v-if="form.role === 'prestador'">✓</span>
                            </span>
                        </div>

                        <div>
                            <span class="block font-bold text-xs leading-tight" :class="form.role === 'prestador' ? 'text-white' : 'text-principal'">
                                Ofrecer oficios
                            </span>
                            <span class="block text-[11px] mt-0.5" :class="form.role === 'prestador' ? 'text-niebla/80' : 'text-gris-medio'">
                                Soy técnico / profesional
                            </span>
                        </div>
                    </button>
                </div>
                <InputError class="mt-1 text-xs" :message="form.errors.role || form.errors.rol" />
            </div>

            <!-- 2. Campos en dos columnas en Desktop (1 columna en móvil) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3">
                <!-- Nombre Completo -->
                <div>
                    <InputLabel for="name" value="Nombre Completo" class="text-principal font-bold text-xs uppercase tracking-wider" />
                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                        placeholder="Ej. Juan Pérez"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.name" />
                </div>

                <!-- Correo Electrónico -->
                <div>
                    <InputLabel for="email" value="Correo Electrónico" class="text-principal font-bold text-xs uppercase tracking-wider" />
                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
                        v-model="form.email"
                        required
                        autocomplete="username"
                        placeholder="ejemplo@correo.com"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.email" />
                </div>

                <!-- Contraseña -->
                <div>
                    <InputLabel for="password" value="Contraseña" class="text-principal font-bold text-xs uppercase tracking-wider" />
                    <TextInput
                        id="password"
                        type="password"
                        class="mt-1 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
                        v-model="form.password"
                        required
                        autocomplete="new-password"
                        placeholder="Mínimo 8 caracteres"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.password" />
                </div>

                <!-- Confirmar Contraseña -->
                <div>
                    <InputLabel for="password_confirmation" value="Confirmar Contraseña" class="text-principal font-bold text-xs uppercase tracking-wider" />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        class="mt-1 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Repite tu contraseña"
                    />
                    <InputError class="mt-1 text-xs" :message="form.errors.password_confirmation" />
                </div>
            </div>

            <!-- Botón Principal a todo lo ancho -->
            <div class="pt-2">
                <button
                    class="w-full py-2.5 px-4 bg-principal text-white font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95 text-sm flex justify-center items-center gap-2 cursor-pointer"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    :disabled="form.processing"
                >
                    Registrarse
                </button>
            </div>

            <!-- Enlace a Login -->
            <div class="mt-3 pt-3 border-t border-slate-100 text-center text-xs text-gris-medio">
                ¿Ya tienes una cuenta?
                <Link :href="route('login')" class="font-bold text-principal hover:text-acento ms-1 transition-colors">
                    Inicia sesión
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>