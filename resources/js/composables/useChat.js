import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import axios from 'axios'

/**
 * Contador de mensajes sin leer por pedido (RF-22).
 *
 * Estado compartido: el dashboard lo actualiza cuando el Service Worker avisa de un mensaje
 * nuevo (push), al volver a la pestaña y, como red de seguridad, cada minuto. No guarda nada en
 * el dispositivo (los mensajes son privados y no se cachean).
 */
export const noLeidos = reactive({}) // { [pedidoId]: cantidad }
export const totalNoLeidos = ref(0)

/** Pedido cuya conversación está abierta ahora (null si ninguna). Lo fija ChatPedido. */
export const chatAbierto = ref(null)

const recalcular = () => {
  totalNoLeidos.value = Object.values(noLeidos).reduce((suma, n) => suma + n, 0)
}

const aplicar = (mapa = {}) => {
  Object.keys(noLeidos).forEach((id) => {
    if (!(id in mapa)) delete noLeidos[id]
  })
  Object.entries(mapa).forEach(([id, n]) => {
    noLeidos[id] = Number(n)
  })
  recalcular()
}

/** Pide al servidor los contadores. Sin conexión conserva los últimos conocidos. */
export async function refrescarNoLeidos() {
  if (typeof navigator !== 'undefined' && navigator.onLine === false) return

  try {
    const { data } = await axios.get(route('chat.noLeidos'), { timeout: 8000 })
    aplicar(data.no_leidos)
  } catch {
    /* sin red o sesión vencida: se reintenta en el siguiente aviso o ciclo */
  }
}

/** La persona acaba de leer esa conversación. */
export function quitarNoLeidos(pedidoId) {
  delete noLeidos[pedidoId]
  recalcular()
}

/**
 * Llamar una vez en la página que muestra los pedidos. Consulta al montar, cuando el Service
 * Worker avisa de un mensaje (push), al volver a la pestaña o a la red y, por si el push falla,
 * cada `cadaMs` mientras la pestaña esté visible.
 */
export function usarNoLeidos(cadaMs = 60000) {
  let temporizador = null
  let pendiente = null

  const alVolver = () => {
    if (document.visibilityState === 'visible') refrescarNoLeidos()
  }

  // Varios mensajes seguidos generan varios avisos: se consulta una sola vez.
  const alAvisoDelSW = (evento) => {
    const aviso = evento.data
    if (aviso?.tipo !== 'chat:nuevo') return

    // Si esa conversación está abierta, ella misma la lee y la marca como leída.
    if (aviso.pedidoId === chatAbierto.value) return

    clearTimeout(pendiente)
    pendiente = setTimeout(refrescarNoLeidos, 400)
  }

  onMounted(() => {
    refrescarNoLeidos()
    temporizador = setInterval(alVolver, cadaMs)
    document.addEventListener('visibilitychange', alVolver)
    window.addEventListener('online', alVolver)
    navigator.serviceWorker?.addEventListener('message', alAvisoDelSW)
  })

  onBeforeUnmount(() => {
    clearInterval(temporizador)
    clearTimeout(pendiente)
    document.removeEventListener('visibilitychange', alVolver)
    window.removeEventListener('online', alVolver)
    navigator.serviceWorker?.removeEventListener('message', alAvisoDelSW)
  })
}
