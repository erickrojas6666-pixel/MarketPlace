<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    canLogin: Boolean,
    canRegister: Boolean,
});

const handleImageError = (e) => {
    if (!e.target.dataset.tried) {
        e.target.dataset.tried = 'true';
        e.target.src = '/images/logo.jpg';
    }
};

/* ---------- Menú hamburguesa (móvil) ---------- */
const menuAbierto = ref(false);

/* ---------- Carrusel del hero ---------- */
// Agrega aquí las rutas de las imágenes (están en public/images/carrusel).
const IMAGENES_CARRUSEL = [
    '/images/carrusel/1.jpg',
    '/images/carrusel/2.jpg',
    '/images/carrusel/3.jpg',
];
const INTERVALO_MS = 5000;

const imagenes = ref([]);
const indiceActual = ref(0);
let temporizador = null;

// Mezcla la lista al azar (Fisher-Yates): cada carga de la página tiene un orden distinto.
const mezclar = (lista) => {
    const copia = [...lista];
    for (let i = copia.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copia[i], copia[j]] = [copia[j], copia[i]];
    }
    return copia;
};

// Comprueba que la imagen exista, para que una ruta mal escrita no deje un hueco en el carrusel.
const existe = (src) =>
    new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(true);
        img.onerror = () => resolve(false);
        img.src = src;
    });

onMounted(async () => {
    const resultados = await Promise.all(IMAGENES_CARRUSEL.map(existe));
    imagenes.value = mezclar(IMAGENES_CARRUSEL.filter((_, i) => resultados[i]));

    if (imagenes.value.length > 1) {
        temporizador = setInterval(() => {
            indiceActual.value = (indiceActual.value + 1) % imagenes.value.length;
        }, INTERVALO_MS);
    }
});

onBeforeUnmount(() => {
    clearInterval(temporizador);
});

const categorias = [
    {
        id: 1,
        nombre: 'Plomería y Gas',
        slug: 'plomeria-y-gas',
        descripcion: 'Reparación de fugas, cañerías y artefactos de gas.',
        icono: 'plomeria'
    },
    {
        id: 2,
        nombre: 'Electricidad',
        slug: 'electricidad',
        descripcion: 'Cortocircuitos, cableado, tableros e instalaciones.',
        icono: 'electricidad'
    },
    {
        id: 3,
        nombre: 'Cerrajería',
        slug: 'cerrajeria',
        descripcion: 'Aperturas de emergencia, cerraduras y duplicados.',
        icono: 'cerrajeria'
    },
    {
        id: 4,
        nombre: 'Pintura',
        slug: 'pintura',
        descripcion: 'Pintura de interiores, exteriores y acabados finos.',
        icono: 'pintura'
    },
    {
        id: 5,
        nombre: 'Jardinería',
        slug: 'jardineria',
        descripcion: 'Poda, mantenimiento y limpieza de áreas verdes.',
        icono: 'jardineria'
    }
];
</script>

<template>
    <Head title="OficiosYa - Encuentra expertos cerca de ti" />

    <div class="min-h-screen bg-niebla text-grafito flex flex-col justify-between font-sans">
        <!-- 1. Header con logo ampliado y legible -->
        <header class="bg-principal border-b border-principal/20 sticky top-0 z-50 shadow-md">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-24 flex items-center justify-between">
                <!-- Contenedor del Logo con escala aumentada -->
                <Link href="/" class="flex items-center">
                    <div class="bg-white px-4 py-2 rounded-2xl shadow-md flex items-center border border-white/30 transition-transform duration-200 hover:scale-105">
                        <img 
                            src="/images/logo.png" 
                            @error="handleImageError"
                            alt="OficiosYa Logo" 
                            class="h-11 sm:h-13 w-auto object-contain"
                        />
                    </div>
                </Link>

                <!-- Acciones (Escritorio) -->
                <nav class="hidden sm:flex items-center gap-3 sm:gap-4">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="px-4 py-2 text-sm font-bold text-white hover:text-acento transition-colors duration-200"
                    >
                        Mi Panel
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="px-4 py-2 text-sm font-semibold text-white/90 hover:text-white transition-colors duration-200"
                        >
                            Iniciar sesión
                        </Link>

                        <Link
                            :href="route('register')"
                            class="px-6 py-3 text-sm font-bold text-principal bg-acento hover:bg-yellow-400 rounded-xl shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5"
                        >
                            Registrarse
                        </Link>
                    </template>
                </nav>

                <!-- Hamburguesa (Móvil) -->
                <button
                    type="button"
                    @click="menuAbierto = !menuAbierto"
                    class="sm:hidden inline-flex items-center justify-center rounded-xl p-2 text-white hover:bg-white/10 focus:outline-none transition"
                    :aria-expanded="menuAbierto"
                    aria-label="Abrir menú"
                >
                    <svg class="h-7 w-7" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path
                            v-if="!menuAbierto"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"
                        />
                        <path
                            v-else
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12"
                        />
                    </svg>
                </button>
            </div>

            <!-- Menú desplegable (Móvil) -->
            <div v-show="menuAbierto" class="sm:hidden bg-white border-t border-slate-200 shadow-lg">
                <div class="px-4 py-4 space-y-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="block w-full text-center px-4 py-3 text-sm font-bold text-white bg-principal hover:bg-slate-800 rounded-xl shadow-md transition-all duration-200"
                    >
                        Mi Panel
                    </Link>

                    <template v-else>
                        <Link
                            :href="route('login')"
                            class="block w-full text-center px-4 py-3 text-sm font-bold text-principal bg-white hover:bg-niebla rounded-xl border-2 border-slate-200 transition-all duration-200"
                        >
                            Iniciar sesión
                        </Link>

                        <Link
                            :href="route('register')"
                            class="block w-full text-center px-4 py-3 text-sm font-bold text-principal bg-acento hover:bg-yellow-400 rounded-xl shadow-md transition-all duration-200"
                        >
                            Registrarse
                        </Link>
                    </template>
                </div>
            </div>
        </header>

        <!-- 2. Hero Section con carrusel de imágenes -->
        <section class="relative overflow-hidden flex items-center min-h-[560px] sm:min-h-[680px] py-20 px-4 sm:px-6 lg:px-8 border-b border-slate-300 shadow-sm bg-gradient-to-br from-principal via-principal to-[#1d4a8a]">
            <!-- Carrusel (sin botones, cambia solo y en orden aleatorio) -->
            <div class="absolute inset-0" aria-hidden="true">
                <img
                    v-for="(src, i) in imagenes"
                    :key="src"
                    :src="src"
                    alt=""
                    class="absolute inset-0 w-full h-full object-cover transition-opacity duration-1000 ease-in-out motion-reduce:transition-none"
                    :class="i === indiceActual ? 'opacity-100' : 'opacity-0'"
                />

                <!-- Capa oscura para que el texto siempre se lea -->
                <div class="absolute inset-0 bg-gradient-to-b from-principal/75 via-principal/60 to-principal/85"></div>
            </div>

            <div class="max-w-4xl mx-auto text-center space-y-6 relative z-10 w-full">
                <span class="inline-flex items-center gap-2 py-1.5 px-4 rounded-full bg-white/10 backdrop-blur-sm text-niebla text-xs font-bold tracking-wider uppercase border border-white/20">
                    <span class="w-2 h-2 rounded-full bg-acento animate-pulse"></span>
                    Servicios profesionales a domicilio
                </span>

                <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight leading-tight drop-shadow-lg">
                    El oficio que necesitas, <br class="hidden sm:inline" />
                    <span class="text-acento">justo a tiempo.</span>
                </h1>

                <p class="text-base sm:text-lg text-niebla/90 max-w-2xl mx-auto font-normal drop-shadow">
                    Conectamos personas y empresas con prestadores calificados y verificados en tu zona.
                </p>

                <!-- Barra de Búsqueda -->
                <div class="mt-8 pt-2">
                    <div class="flex flex-col sm:flex-row gap-2 max-w-2xl mx-auto bg-white p-2.5 rounded-2xl shadow-xl border border-slate-200">
                        <div class="flex-1 flex items-center px-3 gap-2">
                            <svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            <input 
                                type="text" 
                                placeholder="¿Qué necesitas reparar? (ej. Fuga de agua, cortocircuito...)" 
                                class="w-full border-0 focus:ring-0 text-grafito placeholder-gray-400 text-sm outline-none bg-transparent"
                            />
                        </div>
                        <button class="bg-principal text-white px-7 py-3 rounded-xl font-bold hover:bg-opacity-95 transition-all text-sm shadow-md hover:shadow-lg active:scale-95">
                            Buscar especialista
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. Categorías con íconos vectoriales rediseñados y representativos -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-10 gap-4">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-principal tracking-tight">
                        Categorías principales
                    </h2>
                    <p class="text-sm text-gris-medio mt-1">
                        Selecciona el tipo de trabajo que requieres para ver prestadores disponibles.
                    </p>
                </div>
                <span class="text-xs font-bold text-principal uppercase tracking-wider bg-white px-3.5 py-2 rounded-xl border border-slate-300 shadow-sm w-fit">
                    5 Áreas de servicio
                </span>
            </div>

            <!-- Grid de Tarjetas -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-6">
                <div 
                    v-for="cat in categorias" 
                    :key="cat.id"
                    class="bg-white rounded-2xl p-6 border-2 border-slate-200/90 shadow-md hover:shadow-2xl hover:border-principal/40 hover:-translate-y-2 transition-all duration-300 flex flex-col justify-between group cursor-pointer"
                >
                    <div class="space-y-5">
                        <!-- Contenedor del ícono -->
                        <div class="w-16 h-16 rounded-2xl bg-niebla border border-principal/15 flex items-center justify-center text-principal group-hover:bg-principal group-hover:text-acento transition-colors duration-300 shadow-sm">
                            
                            <!-- 1. PLOMERÍA (Llave inglesa ajustable + tubería de fluidos) -->
                            <svg v-if="cat.icono === 'plomeria'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18a3 3 0 0 1-3-3c0-1.5 3-4.5 3-4.5s3 3 3 4.5a3 3 0 0 1-3 3z" />
                            </svg>

                            <!-- 2. ELECTRICIDAD (Rayo limpio de alto voltaje) -->
                            <svg v-else-if="cat.icono === 'electricidad'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                            </svg>

                            <!-- 3. CERRAJERÍA (Llave dentada clásica de perfil completo) -->
                            <svg v-else-if="cat.icono === 'cerrajeria'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <circle cx="7.5" cy="15.5" r="4.5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="m10.7 12.3 8.8-8.8a1.5 1.5 0 0 1 2.1 0v0a1.5 1.5 0 0 1 0 2.1l-2.1 2.1 1.5 1.5-1.5 1.5-1.5-1.5-1.5 1.5-2.1-2.1" />
                            </svg>

                            <!-- 4. PINTURA (Rodillo de pintura con mango y rodillo real) -->
                            <svg v-else-if="cat.icono === 'pintura'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <rect x="3" y="3" width="16" height="6" rx="2" stroke-linecap="round" stroke-linejoin="round" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 6h2a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-9a2 2 0 0 0-2 2v3" />
                                <rect x="9" y="17" width="2" height="5" rx="1" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>

                            <!-- 5. JARDINERÍA (Brote de planta con hojas y tallo vivo) -->
                            <svg v-else-if="cat.icono === 'jardineria'" class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 22v-9" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 13a6 6 0 0 0-6-6H3a6 6 0 0 1 6 6v0a6 6 0 0 0 3 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 13a6 6 0 0 1 6-6h3a6 6 0 0 0-6 6v0a6 6 0 0 1-3 0z" />
                            </svg>

                        </div>

                        <div>
                            <h3 class="font-extrabold text-base text-principal group-hover:text-acento transition-colors duration-200">
                                {{ cat.nombre }}
                            </h3>
                            <p class="text-xs text-gris-medio mt-1.5 leading-relaxed">
                                {{ cat.descripcion }}
                            </p>
                        </div>
                    </div>

                    <!-- Enlace inferior -->
                    <div class="mt-5 pt-3.5 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-principal group-hover:text-acento">
                        <span>Ver servicios</span>
                        <span class="transform group-hover:translate-x-1.5 transition-transform duration-200">→</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. Footer con logo visible -->
        <footer class="bg-principal border-t border-white/10 py-8 px-4 text-center text-xs text-niebla/70">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="bg-white px-3 py-1.5 rounded-xl shadow-sm">
                        <img 
                            src="/images/logo.png" 
                            @error="handleImageError"
                            alt="OficiosYa" 
                            class="h-7 w-auto object-contain" 
                        />
                    </div>
                    <span>&copy; 2026 OficiosYa. Todos los derechos reservados.</span>
                </div>
                <div class="flex gap-6 font-medium text-niebla/80">
                    <a href="#" class="hover:text-acento transition-colors">Términos del servicio</a>
                    <a href="#" class="hover:text-acento transition-colors">Aviso de privacidad</a>
                    <a href="#" class="hover:text-acento transition-colors">Soporte</a>
                </div>
            </div>
        </footer>
    </div>
</template>