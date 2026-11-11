<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const user = usePage().props.auth.user;

const form = useForm({
    nombre: user.nombre,
    email: user.email,
});

const claseInput =
    'mt-1.5 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400';
const claseLabel = 'text-principal font-bold text-xs uppercase tracking-wider';

const iconos = {
    usuario: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    check: 'M5 13l4 4L19 7',
    alerta: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
};
</script>

<template>
    <section class="space-y-6">
        <header class="flex items-center gap-3">
            <div class="w-11 h-11 shrink-0 rounded-xl bg-niebla text-principal flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.usuario" />
                </svg>
            </div>
            <div>
                <h2 class="font-extrabold text-lg text-principal tracking-tight">
                    Información de la cuenta
                </h2>
                <p class="text-xs text-gris-medio">
                    Actualiza tu nombre y tu correo electrónico.
                </p>
            </div>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.update'))"
            class="space-y-5"
        >
            <div>
                <InputLabel for="nombre" value="Nombre" :class="claseLabel" />

                <TextInput
                    id="nombre"
                    type="text"
                    :class="claseInput"
                    v-model="form.nombre"
                    required
                    autofocus
                    autocomplete="name"
                />

                <InputError class="mt-2 text-xs" :message="form.errors.nombre" />
            </div>

            <div>
                <InputLabel for="email" value="Correo electrónico" :class="claseLabel" />

                <TextInput
                    id="email"
                    type="email"
                    :class="claseInput"
                    v-model="form.email"
                    required
                    autocomplete="username"
                />

                <InputError class="mt-2 text-xs" :message="form.errors.email" />
            </div>

            <div
                v-if="mustVerifyEmail && user.email_verified_at === null"
                class="p-4 rounded-2xl bg-amber-50 border-2 border-amber-200 text-amber-800 flex items-start gap-3"
            >
                <div class="w-9 h-9 shrink-0 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.alerta" />
                    </svg>
                </div>
                <div class="text-sm">
                    <p class="font-extrabold">Tu correo no está verificado.</p>
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="mt-1 text-sm font-bold underline hover:text-amber-900 focus:outline-none focus:ring-2 focus:ring-acento rounded"
                    >
                        Haz clic aquí para reenviar el correo de verificación.
                    </Link>

                    <p
                        v-show="status === 'verification-link-sent'"
                        class="mt-2 font-semibold text-green-700"
                    >
                        Te enviamos un nuevo enlace de verificación a tu correo.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2.5 bg-principal text-white text-sm font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                >
                    Guardar cambios
                </button>

                <Transition
                    enter-active-class="transition ease-in-out"
                    enter-from-class="opacity-0"
                    leave-active-class="transition ease-in-out"
                    leave-to-class="opacity-0"
                >
                    <p
                        v-if="form.recentlySuccessful"
                        class="inline-flex items-center gap-1.5 text-sm font-semibold text-green-700"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.check" />
                        </svg>
                        Guardado.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>