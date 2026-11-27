<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import axios from 'axios'
import Modal from '@/Components/Modal.vue'
import { accionesPendientes, alSincronizar, encolar, mostrarAviso } from '@/offline/sync'
import { chatAbierto, noLeidos, quitarNoLeidos, refrescarNoLeidos } from '@/composables/useChat'

/**
 * Chat de un pedido (RF-22). Solo texto, solo cliente y prestador.
 *
 * - Recibir: por Web Push. Cada mensaje nuevo dispara un push; el Service Worker avisa a esta
 *   ventana y aquí se pide solo "lo posterior al último id". No se consulta al servidor cada pocos
 *   segundos. Como red de seguridad (push caído, sin permiso o sin soporte) se consulta con poca
 *   frecuencia: cada 60 s con push y entre 8 y 30 s sin él (más espaciado si la charla está quieta).
 *   Oculta o sin red, se pausa y se retoma al volver.
 * - Enviar: si hay red va directo; si no (o si la respuesta se pierde) pasa a la cola offline
 *   con la MISMA Idempotency-Key, así el servidor nunca duplica el mensaje (PWA-06).
 * - Privacidad: los mensajes no se guardan en el dispositivo. Solo la cola de envío, que se
 *   borra al cerrar sesión (ver offline/sync.js).
 */
const props = defineProps({
  pedidoId: { type: Number, required: true },
  contraparte: { type: String, default: '' },
  abrirInicial: { type: Boolean, default: false },
})

const TIPO_COLA = 'chat.mensaje'
const RED_DE_SEGURIDAD_MS = 60000 // con push: solo por si un aviso no llega
const SIN_PUSH_MIN_MS = 8000 // sin push: arranca aquí…
const SIN_PUSH_MAX_MS = 30000 // …y se espacia hasta aquí si no hay mensajes nuevos

const abierto = ref(false)
const cargado = ref(false)
const cargando = ref(false)
const error = ref('')
const mensajes = ref([])
const leidosHasta = ref(null)
const puedeEscribir = ref(true)
const pushActivo = ref(false) // el servidor puede enviar push Y este dispositivo está suscrito
const maximo = ref(1000)
const texto = ref('')
const enviando = ref([]) // { uuid, contenido }: en camino ahora mismo
const enCola = ref([]) // acciones de la cola offline de ESTE pedido
const sinRed = ref(typeof navigator !== 'undefined' ? !navigator.onLine : false)
const lista = ref(null)

const sinLeer = computed(() => noLeidos[props.pedidoId] ?? 0)
const ultimoId = computed(() => (mensajes.value.length ? mensajes.value[mensajes.value.length - 1].id : 0))
const restantes = computed(() => maximo.value - texto.value.length)

/** Mensajes que aún no confirmó el servidor, en el orden en que se escribieron. */
const pendientes = computed(() => [
  ...enCola.value.map((a) => ({ clave: a.uuid, contenido: a.datos?.contenido ?? '', enCola: true })),
  ...enviando.value.map((e) => ({ clave: e.uuid, contenido: e.contenido, enCola: false })),
])

// Aviso suave (no bloquea): evitar pasar teléfonos o correos antes de tiempo.
const pareceContacto = computed(() => /(\+?\d[\d\s().-]{7,}\d)|([\w.+-]+@[\w-]+\.[\w.-]+)/.test(texto.value))

const marca = (iso) => {
  if (!iso) return ''
  const f = new Date(iso)
  const hora = f.toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
  return f.toDateString() === new Date().toDateString()
    ? hora
    : `${f.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })} ${hora}`
}

const visto = (m) => m.mio && (m.leido || (leidosHasta.value !== null && m.id <= leidosHasta.value))

// --- Scroll ----------------------------------------------------------------------

const estaAlFinal = () => {
  const el = lista.value
  return !el || el.scrollHeight - el.scrollTop - el.clientHeight < 80
}

const irAlFinal = async () => {
  await nextTick()
  if (lista.value) lista.value.scrollTop = lista.value.scrollHeight
}

// --- Carga y consultas ---------------------------------------------------------------

const agregar = (nuevos) => {
  const ya = new Set(mensajes.value.map((m) => m.id))
  const reales = nuevos.filter((m) => !ya.has(m.id))
  if (!reales.length) return false
  mensajes.value = [...mensajes.value, ...reales]
  return true
}

const marcarLeidos = async () => {
  try {
    await axios.post(route('mensajes.leidos', props.pedidoId), {}, { timeout: 8000 })
    quitarNoLeidos(props.pedidoId)
  } catch {
    /* se reintenta cuando llegue el siguiente mensaje */
  }
}

/** ¿Este dispositivo tiene permiso y una suscripción push vigente? */
const dispositivoConPush = async () => {
  try {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || Notification.permission !== 'granted') {
      return false
    }
    const registro = await navigator.serviceWorker.ready
    return !!(await registro.pushManager.getSubscription())
  } catch {
    return false
  }
}

const cargar = async () => {
  const params = cargado.value ? { despues: ultimoId.value } : {}
  const { data } = await axios.get(route('mensajes.index', props.pedidoId), { params, timeout: 10000 })
  const alFinal = estaAlFinal()

  puedeEscribir.value = data.puede_escribir
  maximo.value = data.maximo
  leidosHasta.value = data.leidos_hasta
  pushActivo.value = data.push && (await dispositivoConPush())

  let hayNuevos
  if (!cargado.value) {
    mensajes.value = data.mensajes
    cargado.value = true
    hayNuevos = true
  } else {
    hayNuevos = agregar(data.mensajes)
  }

  if (hayNuevos && alFinal) irAlFinal()
  if (data.mensajes.some((m) => !m.mio && !m.leido)) marcarLeidos()

  return data.mensajes.length > 0
}

let temporizador = null
let aviso = null
let activo = false
let fallos = 0
let quietos = 0 // consultas seguidas sin mensajes nuevos
let montado = true

const espera = () => {
  // Si falla la red o el servidor: retroceso 8, 16, 32, 60 s.
  if (fallos > 0) return Math.min(60000, SIN_PUSH_MIN_MS * 2 ** Math.min(fallos, 3))
  if (pushActivo.value) return RED_DE_SEGURIDAD_MS
  return Math.min(SIN_PUSH_MAX_MS, SIN_PUSH_MIN_MS + quietos * 4000)
}

const programar = () => {
  clearTimeout(temporizador)
  if (activo) temporizador = setTimeout(sondear, espera())
}

async function sondear() {
  if (!activo) return

  // Pestaña oculta o sin red: no se gasta batería ni datos; se retoma al volver.
  if (document.visibilityState === 'hidden' || !navigator.onLine) {
    programar()
    return
  }

  try {
    const hubo = await cargar()
    quietos = hubo ? 0 : quietos + 1
    fallos = 0
    error.value = ''
  } catch (e) {
    fallos += 1
    if (e.response?.status === 404 || e.response?.status === 403) detener() // ya no es parte del pedido
  }

  programar()
}

const alVolverAVer = () => {
  if (activo && document.visibilityState === 'visible') {
    clearTimeout(temporizador)
    sondear()
  }
}

// El Service Worker avisa de un mensaje nuevo (llegó un push): se consulta ahora mismo.
const alAvisoDelSW = (evento) => {
  const datos = evento.data
  if (datos?.tipo !== 'chat:nuevo' || datos.pedidoId !== props.pedidoId || !activo) return

  fallos = 0
  quietos = 0
  clearTimeout(aviso)
  aviso = setTimeout(() => {
    clearTimeout(temporizador)
    sondear()
  }, 250) // varios avisos seguidos se agrupan en una consulta
}

const cambioDeRed = () => {
  sinRed.value = !navigator.onLine
  if (!sinRed.value) alVolverAVer()
}

function detener() {
  activo = false
  clearTimeout(temporizador)
  clearTimeout(aviso)
  document.removeEventListener('visibilitychange', alVolverAVer)
  if (chatAbierto.value === props.pedidoId) chatAbierto.value = null
}

const cargarPendientes = async () => {
  const url = route('mensajes.store', props.pedidoId)
  enCola.value = (await accionesPendientes(TIPO_COLA)).filter((a) => a.url === url)
}

// --- Abrir y cerrar ---------------------------------------------------------------

const abrir = async () => {
  abierto.value = true
  error.value = ''
  cargado.value = false
  mensajes.value = []
  cargando.value = true

  await cargarPendientes()

  try {
    await cargar()
    await irAlFinal()
  } catch (e) {
    error.value = e.response
      ? 'No pudimos cargar la conversación.'
      : 'Sin conexión: no podemos mostrar la conversación, pero lo que escribas se enviará al volver la red.'
  } finally {
    cargando.value = false
  }

  fallos = 0
  quietos = 0
  activo = true
  chatAbierto.value = props.pedidoId
  document.addEventListener('visibilitychange', alVolverAVer)
  programar()
}

const cerrar = () => {
  abierto.value = false
  detener()
  refrescarNoLeidos()
}

// --- Enviar ---------------------------------------------------------------------

const nuevoUuid = () =>
  typeof crypto !== 'undefined' && crypto.randomUUID
    ? crypto.randomUUID()
    : `${Date.now()}-${Math.random().toString(16).slice(2)}-${Math.random().toString(16).slice(2)}`

const dejarEnCola = async (uuid, url, contenido) => {
  const guardado = await encolar({
    tipo: TIPO_COLA,
    url,
    metodo: 'post',
    datos: { contenido },
    descripcion: 'Mensaje del chat',
    uuid,
  })

  if (!guardado) {
    texto.value = texto.value || contenido
    error.value = 'No pudimos guardar tu mensaje. Inicia sesión de nuevo e inténtalo otra vez.'
    return
  }

  await cargarPendientes()
  mostrarAviso('info', 'Sin conexión: tu mensaje se enviará cuando vuelva la red.')
  irAlFinal()
}

const enviar = async () => {
  const contenido = texto.value.trim()
  if (!contenido || contenido.length > maximo.value || !puedeEscribir.value) return

  texto.value = ''
  error.value = ''

  const uuid = nuevoUuid()
  const url = route('mensajes.store', props.pedidoId)

  if (!navigator.onLine) {
    await dejarEnCola(uuid, url, contenido)
    return
  }

  enviando.value.push({ uuid, contenido })
  irAlFinal()

  try {
    const { data } = await axios.post(url, { contenido }, {
      timeout: 10000,
      headers: { 'Idempotency-Key': uuid, Accept: 'application/json' },
    })
    agregar([data.mensaje])
  } catch (e) {
    if (!e.response) {
      // Sin red, o la respuesta se perdió: se reintenta con la misma clave y no se duplica.
      await dejarEnCola(uuid, url, contenido)
    } else {
      if (!texto.value) texto.value = contenido // no se pierde lo escrito
      error.value = e.response.data?.errors?.contenido?.[0] || e.response.data?.message || 'No pudimos enviar el mensaje.'
      if (e.response.status === 403) puedeEscribir.value = false
    }
  } finally {
    enviando.value = enviando.value.filter((m) => m.uuid !== uuid)
    irAlFinal()
  }
}

// --- Ciclo de vida ----------------------------------------------------------------

onMounted(() => {
  window.addEventListener('online', cambioDeRed)
  window.addEventListener('offline', cambioDeRed)
  navigator.serviceWorker?.addEventListener('message', alAvisoDelSW)

  // Cuando la cola envía algo, se actualiza la conversación (si está abierta) y los pendientes.
  alSincronizar(async () => {
    if (!montado) return
    await cargarPendientes()
    if (activo) sondear()
  })

  cargarPendientes()
  if (props.abrirInicial) abrir()
})

onBeforeUnmount(() => {
  montado = false
  detener()
  window.removeEventListener('online', cambioDeRed)
  window.removeEventListener('offline', cambioDeRed)
  navigator.serviceWorker?.removeEventListener('message', alAvisoDelSW)
})
</script>

<template>
  <button
    type="button"
    @click="abrir"
    class="relative inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold border-2 border-principal/20 bg-white text-principal hover:bg-niebla transition active:scale-95"
    :aria-label="sinLeer > 0 ? `Mensajes del pedido ${pedidoId}, ${sinLeer} sin leer` : `Mensajes del pedido ${pedidoId}`"
  >
    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
      <path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6l2.6-3H18a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14z" />
    </svg>
    Mensajes
    <span
      v-if="sinLeer > 0"
      class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-600 text-white text-[10px] font-extrabold flex items-center justify-center"
    >
      {{ sinLeer > 99 ? '99+' : sinLeer }}
    </span>
  </button>

  <Modal :show="abierto" max-width="lg" @close="cerrar">
    <div class="flex flex-col">
      <!-- Encabezado -->
      <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-slate-200">
        <div class="min-w-0">
          <p class="text-[11px] font-bold uppercase tracking-wider text-gris-medio">Pedido #{{ pedidoId }}</p>
          <h2 class="text-base font-extrabold text-principal truncate">{{ contraparte || 'Conversación' }}</h2>
        </div>
        <button type="button" @click="cerrar" class="p-2 rounded-lg text-gris-medio hover:bg-slate-100" aria-label="Cerrar conversación">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <p v-if="sinRed" class="px-5 py-2 text-xs font-semibold bg-amber-50 text-amber-900 border-b border-amber-200" role="status">
        Sin conexión. Lo que escribas se enviará cuando vuelva la red.
      </p>
      <p v-else-if="cargado && !pushActivo" class="px-5 py-2 text-[11px] text-gris-medio bg-niebla/60 border-b border-slate-200">
        Los mensajes nuevos pueden tardar unos segundos. Activa las notificaciones para recibirlos al instante.
      </p>

      <!-- Mensajes -->
      <div ref="lista" class="h-[50vh] overflow-y-auto px-4 py-4 space-y-2 bg-niebla/40" role="log" aria-live="polite" aria-label="Mensajes">
        <p v-if="cargando" class="text-center text-xs text-gris-medio py-6">Cargando conversación…</p>

        <p v-else-if="!mensajes.length && !pendientes.length && !error" class="text-center text-xs text-gris-medio py-10">
          Todavía no hay mensajes. Escribe el primero para coordinar el servicio.
        </p>

        <div v-for="m in mensajes" :key="m.id" class="flex" :class="m.mio ? 'justify-end' : 'justify-start'">
          <div
            class="max-w-[85%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
            :class="m.mio ? 'bg-principal text-white rounded-br-md' : 'bg-white text-grafito border border-slate-200 rounded-bl-md'"
          >
            <p class="whitespace-pre-wrap break-words">{{ m.contenido }}</p>
            <p class="mt-0.5 text-[10px] text-right" :class="m.mio ? 'text-white/70' : 'text-gris-medio'">
              {{ marca(m.created_at) }}<template v-if="visto(m)"> · Visto</template>
            </p>
          </div>
        </div>

        <!-- Aún sin confirmar por el servidor -->
        <div v-for="p in pendientes" :key="p.clave" class="flex justify-end">
          <div class="max-w-[85%] rounded-2xl rounded-br-md px-3.5 py-2 text-sm bg-principal/60 text-white">
            <p class="whitespace-pre-wrap break-words">{{ p.contenido }}</p>
            <p class="mt-0.5 text-[10px] text-right text-white/80">
              {{ p.enCola ? 'Pendiente de enviar (sin conexión)' : 'Enviando…' }}
            </p>
          </div>
        </div>
      </div>

      <!-- Escribir -->
      <div class="px-4 py-3 border-t border-slate-200 space-y-2">
        <p v-if="error" class="text-xs font-semibold text-red-600" role="alert">{{ error }}</p>

        <p v-if="!puedeEscribir" class="text-xs text-gris-medio text-center py-2">
          Esta conversación está cerrada: el pedido ya terminó. Puedes releer lo que se dijeron.
        </p>

        <template v-else>
          <p v-if="pareceContacto" class="text-[11px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
            Por tu seguridad, mantén la conversación y el pago dentro de OficiosYa. Evita compartir teléfonos o correos.
          </p>

          <div class="flex items-end gap-2">
            <label for="texto-chat" class="sr-only">Escribe un mensaje</label>
            <textarea
              id="texto-chat"
              v-model="texto"
              rows="2"
              :maxlength="maximo"
              class="flex-1 resize-none rounded-xl border-2 border-slate-200 focus:border-principal focus:ring-principal text-sm"
              placeholder="Escribe un mensaje…"
              @keydown.enter.exact.prevent="enviar"
            ></textarea>
            <button
              type="button"
              :disabled="!texto.trim()"
              @click="enviar"
              class="px-4 py-2.5 rounded-xl text-sm font-bold bg-principal text-white hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed"
            >
              Enviar
            </button>
          </div>

          <p class="text-[11px] text-gris-medio text-right" :class="{ 'text-red-600 font-bold': restantes < 50 }">
            {{ restantes }} caracteres restantes · Enter envía, Mayús+Enter salta de línea
          </p>
        </template>
      </div>
    </div>
  </Modal>
</template>
