<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * Mapa de OpenStreetMap con Leaflet.
 * - Modo lectura: muestra un punto y, si se indica, el radio de búsqueda.
 * - Modo editable: se puede arrastrar el marcador o tocar el mapa para fijar la ubicación.
 *
 * Leaflet se descarga solo cuando se usa un mapa (carga diferida), así las demás
 * pantallas no pesan más. Las teselas vienen de tile.openstreetmap.org, por lo que
 * la atribución a OpenStreetMap es obligatoria y se muestra en el mapa.
 */
const props = defineProps({
  lat: { type: Number, default: null },
  lng: { type: Number, default: null },
  radioKm: { type: Number, default: null },
  editable: { type: Boolean, default: false },
  altura: { type: String, default: '260px' },
})

const emit = defineEmits(['cambiar'])

const contenedor = ref(null)
const fallo = ref(false)

// Centro por defecto cuando aún no hay punto: Puebla, México.
const CENTRO = [19.0414, -98.2063]

let L = null
let mapa = null
let marcador = null
let circulo = null

const tienePunto = () => Number.isFinite(props.lat) && Number.isFinite(props.lng)

const iconoPunto = () =>
  L.divIcon({
    className: '',
    iconSize: [28, 28],
    iconAnchor: [14, 26],
    html:
      '<span style="display:block;width:22px;height:22px;border-radius:50% 50% 50% 0;' +
      'background:#14305D;border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.45);' +
      'transform:rotate(-45deg)"></span>',
  })

const emitir = (latlng) => {
  emit('cambiar', {
    lat: Number(latlng.lat.toFixed(6)),
    lng: Number(latlng.lng.toFixed(6)),
  })
}

const dibujar = () => {
  if (!mapa) return

  if (!tienePunto()) {
    marcador?.remove()
    circulo?.remove()
    marcador = circulo = null
    mapa.setView(CENTRO, 11)
    return
  }

  const punto = [props.lat, props.lng]

  // Si el marcador ya está en ese punto (p. ej. lo arrastró el usuario) no se recentra el mapa.
  const yaEstaAhi = marcador && marcador.getLatLng().distanceTo(punto) < 1

  if (!marcador) {
    marcador = L.marker(punto, { draggable: props.editable, icon: iconoPunto(), keyboard: false }).addTo(mapa)
    if (props.editable) marcador.on('dragend', () => emitir(marcador.getLatLng()))
  } else if (!yaEstaAhi) {
    marcador.setLatLng(punto)
  }

  circulo?.remove()
  circulo = null

  if (props.radioKm > 0) {
    circulo = L.circle(punto, {
      radius: props.radioKm * 1000,
      color: '#14305D',
      weight: 2,
      fillColor: '#14305D',
      fillOpacity: 0.08,
    }).addTo(mapa)
  }

  if (!yaEstaAhi) {
    if (circulo) mapa.fitBounds(circulo.getBounds(), { padding: [12, 12] })
    else mapa.setView(punto, 14)
  }
}

onMounted(async () => {
  try {
    const modulo = await import('leaflet')
    await import('leaflet/dist/leaflet.css')
    L = modulo.default ?? modulo
  } catch {
    fallo.value = true // sin conexión la primera vez: se muestra un aviso en lugar del mapa
    return
  }

  mapa = L.map(contenedor.value, { scrollWheelZoom: false }).setView(CENTRO, 11)

  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
  }).addTo(mapa)

  if (props.editable) mapa.on('click', (e) => emitir(e.latlng))

  dibujar()

  // El contenedor puede montarse dentro de un bloque que aún no tiene su tamaño final.
  setTimeout(() => mapa?.invalidateSize(), 250)
})

watch(() => [props.lat, props.lng, props.radioKm], dibujar)

onBeforeUnmount(() => {
  mapa?.remove()
  mapa = marcador = circulo = null
})
</script>

<template>
  <div class="isolate overflow-hidden rounded-xl border border-slate-200">
    <div v-show="!fallo" ref="contenedor" class="w-full" :style="{ height: altura }" role="img" aria-label="Mapa de ubicación"></div>

    <p v-if="fallo" class="p-4 text-center text-xs text-slate-500">
      No se pudo cargar el mapa. Revisa tu conexión e inténtalo de nuevo.
    </p>

    <p v-else-if="editable" class="bg-slate-50 px-3 py-1.5 text-center text-[11px] text-slate-500">
      Toca el mapa o arrastra el marcador para ajustar tu ubicación.
    </p>
  </div>
</template>
