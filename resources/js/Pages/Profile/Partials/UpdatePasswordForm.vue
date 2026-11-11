<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};

const claseInput =
    'mt-1.5 block w-full rounded-xl border-slate-300 focus:border-principal focus:ring-principal text-sm shadow-sm py-2 px-3 placeholder:text-slate-400';
const claseLabel = 'text-principal font-bold text-xs uppercase tracking-wider';

const iconos = {
    candado: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
    check: 'M5 13l4 4L19 7',
};
</script>

<template>
    <section class="space-y-6">
        <header class="flex items-center gap-3">
            <div class="w-11 h-11 shrink-0 rounded-xl bg-niebla text-principal flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.candado" />
                </svg>
            </div>
            <div>
                <h2 class="font-extrabold text-lg text-principal tracking-tight">
                    Actualizar contraseña
                </h2>
                <p class="text-xs text-gris-medio">
                    Usa una contraseña larga y aleatoria para mantener tu cuenta segura.
                </p>
            </div>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-5">
            <div>
                <InputLabel for="current_password" value="Contraseña actual" :class="claseLabel" />

                <TextInput
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    :class="claseInput"
                    autocomplete="current-password"
                />

                <InputError
                    :message="form.errors.current_password"
                    class="mt-2 text-xs"
                />
            </div>

            <div>
                <InputLabel for="password" value="Nueva contraseña" :class="claseLabel" />

                <TextInput
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    :class="claseInput"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-2 text-xs" />
            </div>

            <div>
                <InputLabel
                    for="password_confirmation"
                    value="Confirmar contraseña"
                    :class="claseLabel"
                />

                <TextInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    :class="claseInput"
                    autocomplete="new-password"
                />

                <InputError
                    :message="form.errors.password_confirmation"
                    class="mt-2 text-xs"
                />
            </div>

            <div class="flex items-center gap-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2.5 bg-principal text-white text-sm font-bold rounded-xl shadow-md hover:bg-slate-800 transition-all duration-200 active:scale-95"
                    :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                >
                    Guardar contraseña
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
                        Guardada.
                    </p>
                </Transition>
            </div>
        </form>
    </section>
</template>