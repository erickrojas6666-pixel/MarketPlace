<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.clearErrors();
    form.reset();
};

const claseInput =
    'mt-1.5 block w-full rounded-xl border-slate-300 focus:border-red-500 focus:ring-red-500 text-sm shadow-sm py-2 px-3 placeholder:text-slate-400';

const iconos = {
    alerta: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    basura: 'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16',
};
</script>

<template>
    <section class="space-y-6">
        <header class="flex items-center gap-3">
            <div class="w-11 h-11 shrink-0 rounded-xl bg-red-100 text-red-600 flex items-center justify-center">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.basura" />
                </svg>
            </div>
            <div>
                <h2 class="font-extrabold text-lg text-principal tracking-tight">
                    Eliminar cuenta
                </h2>
                <p class="text-xs text-gris-medio">
                    Esta acción es permanente y no se puede deshacer.
                </p>
            </div>
        </header>

        <p class="text-sm text-gris-medio leading-relaxed max-w-2xl">
            Una vez que tu cuenta sea eliminada, todos sus recursos y datos se borrarán
            de forma permanente. Antes de eliminarla, descarga cualquier dato o
            información que quieras conservar.
        </p>

        <button
            type="button"
            @click="confirmUserDeletion"
            class="inline-flex items-center gap-2 px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-xl shadow-md transition-all duration-200 active:scale-95"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.basura" />
            </svg>
            Eliminar cuenta
        </button>

        <Modal :show="confirmingUserDeletion" @close="closeModal" max-width="md">
            <div class="p-7 space-y-5 text-center">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-red-100 text-red-600 flex items-center justify-center">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.alerta" />
                    </svg>
                </div>

                <div class="space-y-2">
                    <h2 class="text-xl font-extrabold text-principal tracking-tight">
                        ¿Seguro que quieres eliminar tu cuenta?
                    </h2>

                    <p class="text-sm text-gris-medio leading-relaxed">
                        Una vez eliminada, todos sus recursos y datos se borrarán de forma
                        permanente. Escribe tu contraseña para confirmar que quieres
                        eliminarla definitivamente.
                    </p>
                </div>

                <div class="text-left">
                    <InputLabel
                        for="password"
                        value="Contraseña"
                        class="sr-only"
                    />

                    <TextInput
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        :class="claseInput"
                        placeholder="Contraseña"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2 text-xs" />
                </div>

                <div class="flex gap-3 pt-2">
                    <button
                        type="button"
                        @click="closeModal"
                        class="flex-1 py-2.5 bg-white hover:bg-slate-50 text-grafito text-sm font-bold rounded-xl border-2 border-slate-200 transition active:scale-95"
                    >
                        Cancelar
                    </button>

                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="deleteUser"
                        class="flex-1 py-2.5 bg-red-600 hover:bg-red-700 text-white text-sm font-bold rounded-xl shadow-md transition active:scale-95"
                        :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                    >
                        Sí, eliminar
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>