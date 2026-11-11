<script setup>
import { computed } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const page = usePage();

// Lo calcula HandleInertiaRequests a partir de los roles.
const esPrestador = computed(() => !!page.props.auth?.user?.esPrestador);

const iconos = {
    usuario: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    maletin: 'M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
    volver: 'M11 17l-5-5m0 0l5-5m-5 5h12',
    flecha: 'M13 7l5 5m0 0l-5 5m5-5H6',
};
</script>

<template>
    <Head title="Ajustes de cuenta" />

    <AuthenticatedLayout>
        <div class="py-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            <!-- ============ BANNER ============ -->
            <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-principal via-principal to-[#1d4a8a] p-6 sm:p-8 shadow-xl shadow-principal/20">
                <div class="absolute -top-16 -right-16 w-64 h-64 rounded-full bg-acento/20 blur-2xl"></div>
                <div class="absolute -bottom-24 left-1/3 w-72 h-72 rounded-full bg-white/5"></div>

                <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 shrink-0 rounded-2xl bg-white/10 border border-white/15 text-acento flex items-center justify-center">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.usuario" />
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Ajustes de cuenta</h1>
                            <p class="text-sm text-niebla/80 mt-1">Administra tus datos, tu contraseña y la seguridad de tu cuenta.</p>
                        </div>
                    </div>

                    <Link
                        :href="route('dashboard')"
                        class="inline-flex items-center gap-2 self-start md:self-auto px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white text-sm font-bold rounded-xl border border-white/20 transition-all duration-200 active:scale-95"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.volver" />
                        </svg>
                        Volver al panel
                    </Link>
                </div>
            </section>

            <!-- ============ ACCESO AL PERFIL PROFESIONAL (solo prestadores) ============ -->
            <div
                v-if="esPrestador"
                class="bg-white rounded-3xl p-6 border-2 border-acento/60 shadow-md flex flex-col sm:flex-row sm:items-center justify-between gap-4"
            >
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 shrink-0 rounded-2xl bg-acento/20 border border-acento/50 text-principal flex items-center justify-center">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.maletin" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-extrabold text-lg text-principal tracking-tight">Perfil de prestador y servicios</h3>
                        <p class="text-sm text-gris-medio mt-0.5">
                            Gestiona tu biografía, ubicación y publica tus servicios profesionales.
                        </p>
                    </div>
                </div>

                <Link
                    :href="route('prestador.perfil.edit')"
                    class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-acento hover:bg-yellow-400 text-principal text-sm font-bold rounded-xl shadow-md hover:-translate-y-0.5 hover:shadow-lg transition-all duration-200"
                >
                    Ir a mi perfil profesional
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" :d="iconos.flecha" />
                    </svg>
                </Link>
            </div>

            <!-- ============ FORMULARIOS ============ -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border-2 border-slate-200/90 shadow-md">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </div>

                <div class="bg-white rounded-3xl p-6 sm:p-8 border-2 border-slate-200/90 shadow-md">
                    <UpdatePasswordForm />
                </div>
            </div>

            <!-- Zona de peligro -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border-2 border-red-200 shadow-md">
                <DeleteUserForm />
            </div>
        </div>
    </AuthenticatedLayout>
</template>