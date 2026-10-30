<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    perfil: Object,
});

const form = useForm({
    descripcion: props.perfil?.descripcion || '',
    ubicacion_aproximada: props.perfil?.ubicacion_aproximada || '',
});

const submit = () => {
    form.post(route('prestador.perfil.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Mi Perfil Profesional" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Mi Perfil de Prestador
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg max-w-xl">
                    <section>
                        <header>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Información Profesional
                            </h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Describe tu experiencia y zona de trabajo para que los clientes te encuentren.
                            </p>
                        </header>

                        <form @submit.prevent="submit" class="mt-6 space-y-6">
                            <div>
                                <InputLabel for="ubicacion" value="Ubicación / Zona de Cobertura" />
                                <TextInput
                                    id="ubicacion"
                                    type="text"
                                    class="mt-1 block w-full"
                                    v-model="form.ubicacion_aproximada"
                                    placeholder="Ej: Buenos Aires, Zona Norte"
                                />
                                <InputError class="mt-2" :message="form.errors.ubicacion_aproximada" />
                            </div>

                            <div>
                                <InputLabel for="descripcion" value="Descripción / Bio Profesional" />
                                <textarea
                                    id="descripcion"
                                    rows="4"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    v-model="form.descripcion"
                                    placeholder="Cuenta tu experiencia, especialidades y años en el rubro..."
                                ></textarea>
                                <InputError class="mt-2" :message="form.errors.descripcion" />
                            </div>

                            <div class="flex items-center gap-4">
                                <PrimaryButton :disabled="form.processing">Guardar Cambios</PrimaryButton>
                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-if="form.recentlySuccessful" class="text-sm text-green-600 dark:text-green-400">
                                        Guardado correctamente.
                                    </p>
                                </Transition>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>