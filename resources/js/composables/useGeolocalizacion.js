import { ref } from 'vue'

/**
 * Lee la ubicación del dispositivo con la API del navegador.
 * Requiere HTTPS (o localhost) y que el usuario conceda el permiso.
 *
 * Uso:
 *   const { cargando, error, obtener } = useGeolocalizacion()
 *   const pos = await obtener()   // { lat, lng } o null si falló (el motivo queda en `error`)
 */
export function useGeolocalizacion() {
  const cargando = ref(false)
  const error = ref(null)

  const mensajeDe = (err) => {
    switch (err?.code) {
      case 1: // PERMISSION_DENIED
        return 'No diste permiso para usar tu ubicación. Puedes escribir tu dirección en su lugar.'
      case 2: // POSITION_UNAVAILABLE
        return 'No pudimos determinar tu ubicación en este momento.'
      case 3: // TIMEOUT
        return 'Tardó demasiado en obtener tu ubicación. Intenta de nuevo.'
      default:
        return 'No pudimos obtener tu ubicación.'
    }
  }

  const obtener = () =>
    new Promise((resolve) => {
      error.value = null

      if (typeof navigator === 'undefined' || !('geolocation' in navigator)) {
        error.value = 'Tu navegador no soporta geolocalización.'
        return resolve(null)
      }

      cargando.value = true
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          cargando.value = false
          resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude })
        },
        (err) => {
          cargando.value = false
          error.value = mensajeDe(err)
          resolve(null)
        },
        { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 }
      )
    })

  return { cargando, error, obtener }
}

/** ~110 m de precisión: suficiente para cercanía y más discreto en la URL. */
export const redondear = (n) => Math.round(Number(n) * 1000) / 1000
