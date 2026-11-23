<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePwa } from '../composables/usePwa'
import { celebrando, iniciarCelebracion } from '../composables/useInstalacion'
import InstalacionExitosa from './InstalacionExitosa.vue'

const props = defineProps({
  // 'tarjeta' (landing) | 'panel' (menú lateral móvil) | 'menu' (menú de usuario en escritorio)
  variante: { type: String, default: 'tarjeta' },
})

const { puedeInstalar, mostrarGuiaIOS, instalar } = usePwa()

// Prepara (una sola vez en toda la página) el mensaje de "Gracias por instalarnos".
onMounted(() => iniciarCelebracion(InstalacionExitosa))

const esTarjeta = computed(() => props.variante === 'tarjeta')

// Solo aparece en el navegador. Si la app ya está instalada (o el navegador no permite
// instalarla), puedeInstalar y mostrarGuiaIOS son falsos y no se muestra nada.
// En el menú de escritorio solo aparece cuando se puede instalar con un clic.
const visible = computed(() =>
  props.variante === 'menu' ? puedeInstalar.value : puedeInstalar.value || mostrarGuiaIOS.value,
)

const verPasos = ref(false)
const instalando = ref(false) // el usuario aceptó instalar y la tarjeta espera para explotar
const explotando = ref(false) // la tarjeta está haciendo su explosión de confeti
const piezas = ref([])
const tarjetaEl = ref(null)

// La tarjeta se queda en pantalla mientras se instala y mientras explota.
const mostrarTarjeta = computed(
  () => esTarjeta.value && (visible.value || instalando.value || explotando.value),
)

let temporizadorExplosion = null
let temporizadorSeguridad = null

const presionar = async () => {
  if (!puedeInstalar.value) {
    verPasos.value = !verPasos.value
    return
  }

  if (esTarjeta.value) instalando.value = true

  const aceptado = await instalar()
  if (!aceptado) {
    instalando.value = false
    return
  }

  // La celebración la dispara useInstalacion: espera a que el navegador termine de instalar
  // y a que se cierren sus cuadros. Este seguro solo limpia la tarjeta si nunca llegara el aviso.
  clearTimeout(temporizadorSeguridad)
  temporizadorSeguridad = setTimeout(() => {
    if (!explotando.value && !celebrando.value) instalando.value = false
  }, 300000)
}

const COLORES = ['#EDB10C', '#14305D', '#22C55E', '#38BDF8', '#F97316']

const explotar = () => {
  const alcance = Math.min(window.innerWidth / 2 - 24, 520)

  piezas.value = Array.from({ length: 36 }, (_, i) => {
    const angulo = Math.random() * Math.PI * 2
    const fuerza = alcance * (0.35 + Math.random() * 0.65)
    return {
      id: i,
      dx: Math.round(Math.cos(angulo) * fuerza),
      dy: Math.round(Math.sin(angulo) * fuerza * 0.55 + 40),
      rot: Math.round(Math.random() * 720 - 360),
      color: COLORES[i % COLORES.length],
      ancho: 6 + Math.round(Math.random() * 8),
      redonda: i % 3 === 0,
      retraso: Math.round(Math.random() * 120),
    }
  })

  explotando.value = true
  clearTimeout(temporizadorExplosion)
  temporizadorExplosion = setTimeout(() => {
    explotando.value = false
    instalando.value = false
    piezas.value = []
  }, 1300)
}

// Cuando empieza la celebración y la tarjeta está en pantalla, explota.
// flush: 'sync' para actuar antes de que Vue quite la tarjeta de la página.
watch(
  celebrando,
  (activa) => {
    if (!activa || !esTarjeta.value || !tarjetaEl.value) return
    explotar()
  },
  { flush: 'sync' },
)

onBeforeUnmount(() => {
  clearTimeout(temporizadorExplosion)
  clearTimeout(temporizadorSeguridad)
})

const iconos = {
  telefono: ['M12 18h.01', 'M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'],
  descarga: ['M12 4v12', 'M8 12l4 4 4-4', 'M5 20h14'],
  listo: ['M5 13l4 4L19 7'],
}
// El primer ícono se repite al final para que el bucle no tenga saltos.
const secuencia = ['telefono', 'descarga', 'listo', 'telefono']
const pasos = [
  'Toca el botón Compartir de Safari.',
  'Elige «Añadir a pantalla de inicio».',
  'Confirma con «Añadir».',
]
</script>

<template>
  <!-- ============ TARJETA (landing) ============ -->
  <div v-if="mostrarTarjeta" ref="tarjetaEl" class="relative mt-10">
    <section
      class="bg-white rounded-3xl p-6 border-2 border-slate-200/90 shadow-md"
      :class="{
        'tarjeta-latido': !instalando && !explotando,
        'tarjeta-explota': explotando,
      }"
    >
      <div class="flex flex-col sm:flex-row sm:items-center gap-5">
        <!-- Ícono animado: celular → descarga → palomita -->
        <div class="w-14 h-14 shrink-0 rounded-2xl bg-principal text-acento overflow-hidden shadow-md" aria-hidden="true">
          <div class="carrusel-iconos">
            <div
              v-for="(nombre, i) in secuencia"
              :key="i"
              class="w-14 h-14 flex items-center justify-center"
            >
              <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path v-for="(d, j) in iconos[nombre]" :key="j" stroke-linecap="round" stroke-linejoin="round" :d="d" />
              </svg>
            </div>
          </div>
        </div>

        <div class="flex-1 min-w-0">
          <h2 class="font-extrabold text-lg text-principal tracking-tight">Lleva OficiosYa contigo</h2>
          <p class="text-sm text-gris-medio mt-1">
            Instálala gratis en tu celular o computadora y ábrela como una app, sin buscarla en el navegador.
          </p>
        </div>

        <button
          type="button"
          :disabled="instalando"
          class="shrink-0 px-6 py-3 text-sm font-bold text-principal bg-acento hover:bg-yellow-400 rounded-xl shadow-md transition-all duration-200 hover:shadow-lg hover:-translate-y-0.5 active:scale-95 disabled:opacity-70"
          @click="presionar"
        >
          {{ instalando ? 'Instalando…' : puedeInstalar ? 'Instalar app' : verPasos ? 'Ocultar pasos' : 'Cómo instalar' }}
        </button>
      </div>

      <ol v-if="verPasos && !puedeInstalar && !instalando" class="mt-5 space-y-2">
        <li
          v-for="(paso, i) in pasos"
          :key="i"
          class="flex items-center gap-3 p-4 bg-niebla/40 rounded-2xl border border-slate-200 text-sm text-grafito"
        >
          <span class="w-7 h-7 shrink-0 rounded-full bg-principal text-acento text-xs font-extrabold flex items-center justify-center">
            {{ i + 1 }}
          </span>
          {{ paso }}
        </li>
      </ol>
    </section>

    <!-- Confeti de la explosión -->
    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
      <span
        v-for="p in piezas"
        :key="p.id"
        class="confeti-pieza"
        :class="p.redonda ? 'rounded-full' : 'rounded-sm'"
        :style="{
          '--dx': p.dx + 'px',
          '--dy': p.dy + 'px',
          '--rot': p.rot + 'deg',
          backgroundColor: p.color,
          width: p.ancho + 'px',
          height: (p.redonda ? p.ancho : Math.round(p.ancho * 1.8)) + 'px',
          animationDelay: p.retraso + 'ms',
        }"
      ></span>
    </div>
  </div>

  <!-- ============ OPCIÓN DEL PANEL LATERAL (móvil) ============ -->
  <div v-if="visible && variante === 'panel'" class="mt-5">
    <p class="px-1 mb-2 text-[11px] font-extrabold uppercase tracking-wider text-gris-medio">Aplicación</p>

    <button
      type="button"
      class="group flex w-full items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3 py-2.5 text-left text-sm font-bold text-principal shadow-sm transition-all duration-200 hover:border-principal/40 hover:shadow-md hover:-translate-y-0.5 active:bg-niebla active:scale-[0.98]"
      @click="presionar"
    >
      <span class="w-9 h-9 shrink-0 rounded-lg flex items-center justify-center bg-niebla text-principal transition-colors duration-200 group-hover:bg-principal group-hover:text-acento">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
          <path v-for="(d, i) in iconos.telefono" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
        </svg>
      </span>
      {{ puedeInstalar ? 'Instalar app' : 'Cómo instalar la app' }}
    </button>

    <ol v-if="verPasos && !puedeInstalar" class="mt-2 space-y-1.5">
      <li
        v-for="(paso, i) in pasos"
        :key="i"
        class="flex items-center gap-2.5 rounded-xl bg-white border border-slate-200 px-3 py-2 text-xs text-grafito"
      >
        <span class="w-5 h-5 shrink-0 rounded-full bg-principal text-acento text-[10px] font-extrabold flex items-center justify-center">
          {{ i + 1 }}
        </span>
        {{ paso }}
      </li>
    </ol>
  </div>

  <!-- ============ OPCIÓN DEL MENÚ DE USUARIO (escritorio) ============ -->
  <button
    v-if="visible && variante === 'menu'"
    type="button"
    class="group flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm font-semibold whitespace-nowrap text-grafito transition-colors duration-200 hover:bg-niebla hover:text-principal active:bg-principal/10"
    @click="presionar"
  >
    <span class="w-7 h-7 shrink-0 rounded-md bg-niebla text-principal flex items-center justify-center transition-colors duration-200 group-hover:bg-principal group-hover:text-acento">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path v-for="(d, i) in iconos.telefono" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="d" />
      </svg>
    </span>
    Instalar app
  </button>
</template>

<style scoped>
/* Columna de íconos que sube sola: celular → descarga → palomita (4 casillas, la última repite el celular). */
.carrusel-iconos {
  animation: carrusel-iconos 6s cubic-bezier(0.7, 0, 0.3, 1) infinite;
}

@keyframes carrusel-iconos {
  0%,
  24% {
    transform: translateY(0);
  }
  33%,
  57% {
    transform: translateY(-25%);
  }
  66%,
  90% {
    transform: translateY(-50%);
  }
  100% {
    transform: translateY(-75%);
  }
}

/* Latido de toda la tarjeta: doble pulso, borde dorado y un aro que se expande. */
.tarjeta-latido {
  animation: latido-tarjeta 2s ease-in-out infinite;
}

.tarjeta-latido:hover {
  animation: none;
}

@keyframes latido-tarjeta {
  0%,
  100% {
    transform: scale(1);
    border-color: rgb(226 232 240 / 0.9);
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 0 0 0 rgba(237, 177, 12, 0.55);
  }
  15% {
    transform: scale(1.015);
    border-color: #edb10c;
  }
  30% {
    transform: scale(1);
    border-color: rgb(226 232 240 / 0.9);
  }
  45% {
    transform: scale(1.015);
    border-color: #edb10c;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 0 0 18px rgba(237, 177, 12, 0);
  }
  75% {
    transform: scale(1);
    border-color: rgb(226 232 240 / 0.9);
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 0 0 0 rgba(237, 177, 12, 0);
  }
}

/* Explosión de la tarjeta */
.tarjeta-explota {
  animation: explotar-tarjeta 0.9s ease-in forwards;
}

@keyframes explotar-tarjeta {
  0% {
    transform: scale(1);
    opacity: 1;
    filter: blur(0);
  }
  20% {
    transform: scale(1.04);
    opacity: 1;
  }
  100% {
    transform: scale(0.7);
    opacity: 0;
    filter: blur(8px);
  }
}

.confeti-pieza {
  position: absolute;
  left: 50%;
  top: 50%;
  animation: confeti 1.2s cubic-bezier(0.15, 0.7, 0.3, 1) forwards;
}

@keyframes confeti {
  0% {
    transform: translate(0, 0) rotate(0deg) scale(1);
    opacity: 1;
  }
  70% {
    opacity: 1;
  }
  100% {
    transform: translate(var(--dx), var(--dy)) rotate(var(--rot)) scale(0.6);
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .carrusel-iconos,
  .tarjeta-latido {
    animation: none;
  }
}
</style>