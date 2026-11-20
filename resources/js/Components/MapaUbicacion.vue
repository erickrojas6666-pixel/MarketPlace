<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

/**
 * Mapa 3D con MapLibre GL.
 * - Modo lectura: muestra un punto y, si se indica, el radio de búsqueda con efecto radar.
 * - Modo editable: se puede arrastrar el marcador o tocar el mapa para fijar la ubicación.
 * - Botones Mapa / Satélite, 3D, brújula (muestra una N roja hacia el norte) y volver a mi ubicación.
 *
 * MapLibre se descarga solo cuando se usa un mapa (carga diferida). Su worker
 * (maplibre-gl-worker.mjs) se indica de forma explícita para que Vite lo encuentre.
 * Atribuciones (obligatorias, se muestran en el mapa):
 * - Mapa: OpenFreeMap © OpenMapTiles, datos de OpenStreetMap
 * - Satélite: Esri, Maxar, Earthstar Geographics
 */
const props = defineProps({
  lat: { type: Number, default: null },
  lng: { type: Number, default: null },
  radioKm: { type: Number, default: null },
  editable: { type: Boolean, default: false },
  altura: { type: String, default: '420px' },
})

const emit = defineEmits(['cambiar'])

const contenedor = ref(null)
const fallo = ref(false)
const listo = ref(false)
const satelite = ref(false)
const tresD = ref(true)
const rumbo = ref(0)
const mostrandoNorte = ref(false)
const perdido = ref(false)

const hayPunto = computed(() => Number.isFinite(props.lat) && Number.isFinite(props.lng))

const ESTILO = 'https://tiles.openfreemap.org/styles/liberty'
const INCLINACION = 58
const INCLINACION_MAXIMA = 78
const SEGUNDOS_NORTE = 5

// Los edificios se exageran visualmente para que se note el volumen.
const FACTOR_ALTURA = 2.2

// Centro por defecto cuando aún no hay punto: Puebla, México. Formato [longitud, latitud].
const CENTRO = [-98.2063, 19.0414]

const VACIO = { type: 'FeatureCollection', features: [] }

// Ajustes del efecto radar.
const SEGUNDOS_POR_VUELTA = 4
const PASOS_ESTELA = 18
const GRADOS_POR_PASO = 3.5

let maplibregl = null
let mapa = null
let marcador = null
let cargado = false
let primeraVez = true
let ultimoRadio = null
let radar = null // { lat, lng, radioKm } mientras haya círculo
let animacion = null
let ultimoCuadro = 0
let textosOriginales = null
let textosCambiados = false
let temporizadorNorte = null

const reducirMovimiento =
  typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

const tienePunto = () => Number.isFinite(props.lat) && Number.isFinite(props.lng)

// Pin de mapa amarillo (estilo Google Maps) con centro azul marino y pulso en el suelo.
const crearElementoMarcador = () => {
  const el = document.createElement('div')
  el.className = 'mapa-ou-icono'
  el.innerHTML =
    '<span class="mapa-ou-pulso"></span>' +
    '<svg viewBox="0 0 36 48" aria-hidden="true">' +
    '<path d="M18 1.5C9 1.5 1.5 8.9 1.5 18c0 12 16.5 28.5 16.5 28.5S34.5 30 34.5 18C34.5 8.9 27 1.5 18 1.5z" ' +
    'fill="#EDB10C" stroke="#ffffff" stroke-width="2.5" stroke-linejoin="round"/>' +
    '<circle cx="18" cy="18" r="7" fill="#14305D"/>' +
    '</svg>'
  return el
}

// Punto a cierta distancia (km) y dirección (grados, 0 = norte, en sentido del reloj).
const puntoEn = (lat, lng, radioKm, grados) => {
  const a = (grados * Math.PI) / 180
  return [
    lng + (radioKm * Math.sin(a)) / (111.32 * Math.cos((lat * Math.PI) / 180)),
    lat + (radioKm * Math.cos(a)) / 110.574,
  ]
}

// Círculo (polígono) a partir de un centro y un radio en km.
const circuloGeoJSON = (lat, lng, radioKm, pasos = 72) => {
  const anillo = []
  for (let i = 0; i <= pasos; i++) {
    anillo.push(puntoEn(lat, lng, radioKm, (i / pasos) * 360))
  }
  return { type: 'Feature', geometry: { type: 'Polygon', coordinates: [anillo] } }
}

const limitesDe = (figura) => {
  const anillo = figura.geometry.coordinates[0]
  const lngs = anillo.map((c) => c[0])
  const lats = anillo.map((c) => c[1])
  return [
    [Math.min(...lngs), Math.min(...lats)],
    [Math.max(...lngs), Math.max(...lats)],
  ]
}

// Línea del radar + estela que se desvanece detrás de ella.
const figurasRadar = (grados) => {
  if (!radar) return VACIO
  const { lat, lng, radioKm } = radar
  const centro = [lng, lat]
  const piezas = []

  for (let i = 0; i < PASOS_ESTELA; i++) {
    const desde = grados - (i + 1) * GRADOS_POR_PASO
    const hasta = grados - i * GRADOS_POR_PASO
    piezas.push({
      type: 'Feature',
      properties: { op: 0.42 * Math.pow(1 - i / PASOS_ESTELA, 2) },
      geometry: {
        type: 'Polygon',
        coordinates: [[centro, puntoEn(lat, lng, radioKm, desde), puntoEn(lat, lng, radioKm, hasta), centro]],
      },
    })
  }

  piezas.push({
    type: 'Feature',
    properties: {},
    geometry: { type: 'LineString', coordinates: [centro, puntoEn(lat, lng, radioKm, grados)] },
  })

  return { type: 'FeatureCollection', features: piezas }
}

const pintarRadar = (tiempo) => {
  animacion = requestAnimationFrame(pintarRadar)
  if (tiempo - ultimoCuadro < 33) return // unos 30 cuadros por segundo es suficiente
  ultimoCuadro = tiempo
  const grados = (((tiempo / 1000) / SEGUNDOS_POR_VUELTA) * 360) % 360
  mapa?.getSource('radar')?.setData(figurasRadar(grados))
}

const detenerRadar = () => {
  if (animacion) cancelAnimationFrame(animacion)
  animacion = null
}

const iniciarRadar = () => {
  if (!mapa || !cargado || animacion) return
  if (reducirMovimiento) {
    // Sin animación: se dibuja una sola vez en posición fija.
    mapa.getSource('radar')?.setData(figurasRadar(40))
    return
  }
  animacion = requestAnimationFrame(pintarRadar)
}

const emitir = (lngLat) => {
  emit('cambiar', {
    lat: Number(lngLat.lat.toFixed(6)),
    lng: Number(lngLat.lng.toFixed(6)),
  })
}

// Indica si el punto del usuario quedó fuera de la pantalla (para resaltar el botón de regreso).
const revisarSiEstaPerdido = () => {
  if (!mapa || !cargado || !tienePunto()) {
    perdido.value = false
    return
  }
  perdido.value = !mapa.getBounds().contains([props.lng, props.lat])
}

const dibujar = () => {
  if (!mapa || !cargado) return

  const fuente = mapa.getSource('cobertura')

  if (!tienePunto()) {
    marcador?.remove()
    marcador = null
    fuente?.setData(VACIO)
    detenerRadar()
    radar = null
    mapa.getSource('radar')?.setData(VACIO)
    ultimoRadio = null
    perdido.value = false
    mapa.jumpTo({ center: CENTRO, zoom: 10 })
    return
  }

  const punto = [props.lng, props.lat]

  // Si el marcador ya está en ese punto (p. ej. lo arrastró el usuario) no se recentra el mapa.
  const yaEstaAhi = marcador && marcador.getLngLat().distanceTo({ lng: props.lng, lat: props.lat }) < 1
  const radioCambio = (props.radioKm || 0) !== (ultimoRadio || 0)

  if (!marcador) {
    marcador = new maplibregl.Marker({
      element: crearElementoMarcador(),
      anchor: 'bottom',
      draggable: props.editable,
    })
      .setLngLat(punto)
      .addTo(mapa)
    if (props.editable) marcador.on('dragend', () => emitir(marcador.getLngLat()))
  } else if (!yaEstaAhi) {
    marcador.setLngLat(punto)
  }

  let limites = null
  if (props.radioKm > 0) {
    const figura = circuloGeoJSON(props.lat, props.lng, props.radioKm)
    fuente?.setData(figura)
    limites = limitesDe(figura)
    radar = { lat: props.lat, lng: props.lng, radioKm: props.radioKm }
    iniciarRadar()
  } else {
    fuente?.setData(VACIO)
    detenerRadar()
    radar = null
    mapa.getSource('radar')?.setData(VACIO)
  }

  if (!yaEstaAhi || radioCambio) {
    const animar = !primeraVez // La primera vez se coloca directo, sin animación.
    if (limites) {
      mapa.fitBounds(limites, { padding: 70, maxZoom: 16, duration: 900, animate: animar })
    } else if (animar) {
      mapa.flyTo({ center: punto, zoom: 15, duration: 900 })
    } else {
      mapa.jumpTo({ center: punto, zoom: 15 })
    }
    primeraVez = false
  }

  ultimoRadio = props.radioKm
}

// Botón "volver a mi ubicación": regresa al punto (y a su círculo de cobertura si lo hay).
const volverAUbicacion = () => {
  if (!mapa || !tienePunto()) return
  if (props.radioKm > 0) {
    const figura = circuloGeoJSON(props.lat, props.lng, props.radioKm)
    mapa.fitBounds(limitesDe(figura), { padding: 70, maxZoom: 16, duration: 900 })
  } else {
    mapa.flyTo({ center: [props.lng, props.lat], zoom: 15, duration: 900 })
  }
}

// Sobre la foto de satélite los nombres se ven en blanco con contorno oscuro.
const ajustarTextos = (sobreSatelite) => {
  if (!mapa || !cargado) return
  try {
    if (!textosOriginales) {
      textosOriginales = mapa
        .getStyle()
        .layers.filter((l) => l.type === 'symbol' && l.layout && l.layout['text-field'])
        .map((l) => ({
          id: l.id,
          color: mapa.getPaintProperty(l.id, 'text-color'),
          halo: mapa.getPaintProperty(l.id, 'text-halo-color'),
          ancho: mapa.getPaintProperty(l.id, 'text-halo-width'),
        }))
    }

    if (sobreSatelite) {
      for (const t of textosOriginales) {
        mapa.setPaintProperty(t.id, 'text-color', '#ffffff')
        mapa.setPaintProperty(t.id, 'text-halo-color', 'rgba(10, 20, 40, 0.9)')
        mapa.setPaintProperty(t.id, 'text-halo-width', 1.5)
      }
      textosCambiados = true
    } else if (textosCambiados) {
      for (const t of textosOriginales) {
        mapa.setPaintProperty(t.id, 'text-color', t.color)
        mapa.setPaintProperty(t.id, 'text-halo-color', t.halo)
        mapa.setPaintProperty(t.id, 'text-halo-width', t.ancho)
      }
      textosCambiados = false
    }
  } catch {
    // Si algo falla, los nombres se quedan como estaban.
  }
}

const aplicarVista = () => {
  if (!mapa || !cargado) return
  mapa.setLayoutProperty('satelite', 'visibility', satelite.value ? 'visible' : 'none')
  if (mapa.getLayer('edificios-ou')) {
    mapa.setPaintProperty('edificios-ou', 'fill-extrusion-opacity', satelite.value ? 0.82 : 0.92)
  }
  ajustarTextos(satelite.value)
}

const cambiarVista = (esSatelite) => {
  satelite.value = esSatelite
  aplicarVista()
}

const alternar3D = () => {
  if (!mapa) return
  const activar = mapa.getPitch() <= 5
  tresD.value = activar
  mapa.easeTo({ pitch: activar ? INCLINACION : 0, duration: 600 })
}

// Brújula: muestra una N grande hacia donde está el norte; se desvanece sola a los pocos segundos.
const mostrarNorte = () => {
  if (!mapa) return
  rumbo.value = mapa.getBearing()
  mostrandoNorte.value = true
  clearTimeout(temporizadorNorte)
  temporizadorNorte = setTimeout(() => (mostrandoNorte.value = false), SEGUNDOS_NORTE * 1000)
}

// Posición en pantalla (sobre un círculo alrededor del centro del mapa) en la dirección del norte.
const puntoNorte = (distancia) => {
  const caja = contenedor.value
  const ancho = caja?.clientWidth || 0
  const alto = caja?.clientHeight || 0
  const a = (-rumbo.value * Math.PI) / 180
  return { x: ancho / 2 + distancia * Math.sin(a), y: alto / 2 - distancia * Math.cos(a) }
}

const radioNorte = () => {
  const caja = contenedor.value
  const medio = Math.min(caja?.clientWidth || 0, caja?.clientHeight || 0) / 2
  return Math.max(60, medio - 70)
}

const estiloLetraNorte = () => {
  const p = puntoNorte(radioNorte())
  return { left: `${p.x}px`, top: `${p.y}px` }
}

const estiloFlechaNorte = () => {
  const p = puntoNorte(radioNorte() - 64)
  return {
    left: `${p.x}px`,
    top: `${p.y}px`,
    transform: `translate(-50%, -50%) rotate(${-rumbo.value}deg)`,
  }
}

const traducirControles = () => {
  const poner = (selector, texto) => {
    const boton = contenedor.value?.querySelector(selector)
    if (!boton) return
    boton.setAttribute('title', texto)
    boton.setAttribute('aria-label', texto)
  }
  poner('.maplibregl-ctrl-zoom-in', 'Acercar')
  poner('.maplibregl-ctrl-zoom-out', 'Alejar')
  poner('.maplibregl-ctrl-compass', 'Norte: clic para ver hacia dónde está el norte')
}

// La brújula original ya no reinicia el mapa: solo muestra la N roja.
const conectarBrujula = () => {
  const boton = contenedor.value?.querySelector('.maplibregl-ctrl-compass')
  const grupo = boton?.parentElement
  if (!grupo) return
  grupo.addEventListener(
    'click',
    (e) => {
      if (!e.target.closest('.maplibregl-ctrl-compass')) return
      e.stopPropagation()
      e.preventDefault()
      mostrarNorte()
    },
    true,
  )
}

// Edificios en 3D propios, más visibles que los del estilo original.
const agregarEdificios = (capas, antesDe) => {
  try {
    const base =
      capas.find((l) => l.type === 'fill-extrusion') ||
      capas.find((l) => l.type === 'fill' && l['source-layer'] === 'building')
    if (!base?.source) return

    const alto = ['coalesce', ['get', 'render_height'], 0]

    mapa.addLayer(
      {
        id: 'edificios-ou',
        type: 'fill-extrusion',
        source: base.source,
        'source-layer': 'building',
        minzoom: 13,
        filter: ['!=', ['get', 'hide_3d'], true],
        paint: {
          'fill-extrusion-color': ['interpolate', ['linear'], alto, 0, '#8fa9cf', 40, '#4f77ad', 120, '#14305D'],
          'fill-extrusion-height': ['*', ['max', alto, 6], FACTOR_ALTURA],
          'fill-extrusion-base': ['*', ['coalesce', ['get', 'render_min_height'], 0], FACTOR_ALTURA],
          'fill-extrusion-opacity': 0.92,
          'fill-extrusion-vertical-gradient': true,
        },
      },
      antesDe,
    )

    // Se ocultan los edificios 3D originales para no duplicarlos.
    for (const l of capas) {
      if (l.type === 'fill-extrusion') mapa.setLayoutProperty(l.id, 'visibility', 'none')
    }
  } catch {
    // Si algo falla, se quedan los edificios del estilo original.
  }
}

onMounted(async () => {
  try {
    const modulo = await import('maplibre-gl')
    const trabajador = await import('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url')
    await import('maplibre-gl/dist/maplibre-gl.css')
    maplibregl = modulo.default ?? modulo
    const fijarWorker = modulo.setWorkerUrl ?? maplibregl.setWorkerUrl
    fijarWorker?.(trabajador.default)
  } catch {
    fallo.value = true // sin conexión la primera vez: se muestra un aviso en lugar del mapa
    return
  }

  try {
    mapa = new maplibregl.Map({
      container: contenedor.value,
      style: ESTILO,
      center: CENTRO,
      zoom: 10,
      pitch: INCLINACION,
      maxPitch: INCLINACION_MAXIMA,
      attributionControl: false,
    })
  } catch {
    fallo.value = true // el navegador no soporta WebGL
    return
  }

  mapa.scrollZoom.disable()
  mapa.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-left')
  mapa.addControl(new maplibregl.AttributionControl({ compact: true }), 'bottom-right')
  traducirControles()
  conectarBrujula()

  // El botón 3D refleja la inclinación real, la N sigue el giro real y se detecta si el usuario se pierde.
  mapa.on('pitchend', () => {
    tresD.value = mapa.getPitch() > 5
  })
  mapa.on('rotate', () => {
    rumbo.value = mapa.getBearing()
  })
  mapa.on('moveend', revisarSiEstaPerdido)

  mapa.on('load', () => {
    if (!mapa) return

    // Las capas nuevas se colocan debajo de los nombres (etiquetas) para que sigan legibles.
    const capas = mapa.getStyle().layers
    const antesDe = capas.find((l) => l.type === 'symbol')?.id

    // Capa de satélite (oculta al inicio).
    mapa.addSource('satelite', {
      type: 'raster',
      tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
      tileSize: 256,
      maxzoom: 19,
      attribution: 'Tiles &copy; Esri &mdash; Maxar, Earthstar Geographics',
    })
    mapa.addLayer(
      { id: 'satelite', type: 'raster', source: 'satelite', layout: { visibility: 'none' } },
      antesDe,
    )

    agregarEdificios(capas, antesDe)

    // Círculo de cobertura: relleno azul translúcido con borde azul marino.
    mapa.addSource('cobertura', { type: 'geojson', data: VACIO })
    mapa.addLayer(
      {
        id: 'cobertura-relleno',
        type: 'fill',
        source: 'cobertura',
        paint: { 'fill-color': '#1d4a8a', 'fill-opacity': 0.18 },
      },
      antesDe,
    )
    mapa.addLayer(
      {
        id: 'cobertura-borde',
        type: 'line',
        source: 'cobertura',
        paint: { 'line-color': '#14305D', 'line-width': 2 },
      },
      antesDe,
    )

    // Radar: estela que se desvanece y línea que gira desde el centro.
    mapa.addSource('radar', { type: 'geojson', data: VACIO })
    mapa.addLayer(
      {
        id: 'radar-estela',
        type: 'fill',
        source: 'radar',
        filter: ['==', ['geometry-type'], 'Polygon'],
        paint: { 'fill-color': '#1d4a8a', 'fill-opacity': ['get', 'op'] },
      },
      antesDe,
    )
    mapa.addLayer(
      {
        id: 'radar-linea',
        type: 'line',
        source: 'radar',
        filter: ['==', ['geometry-type'], 'LineString'],
        paint: { 'line-color': '#14305D', 'line-width': 2.5, 'line-opacity': 0.9 },
      },
      antesDe,
    )

    cargado = true
    listo.value = true
    traducirControles()
    aplicarVista()
    dibujar()
    revisarSiEstaPerdido()
  })

  if (props.editable) mapa.on('click', (e) => emitir(e.lngLat))

  // El contenedor puede montarse dentro de un bloque que aún no tiene su tamaño final.
  setTimeout(() => mapa?.resize(), 250)
})

watch(() => [props.lat, props.lng, props.radioKm], dibujar)

onBeforeUnmount(() => {
  detenerRadar()
  clearTimeout(temporizadorNorte)
  mapa?.remove()
  mapa = marcador = radar = textosOriginales = null
  cargado = false
  textosCambiados = false
})
</script>

<template>
  <div class="mapa-ou isolate overflow-hidden rounded-3xl border-2 border-slate-200/90 bg-white shadow-md">
    <div class="relative">
      <div v-show="!fallo" ref="contenedor" class="w-full" :style="{ height: altura }" role="img" aria-label="Mapa de ubicación"></div>

      <!-- N roja translúcida: indica hacia dónde está el norte, sigue el giro del mapa y se desvanece sola. -->
      <div
        v-if="listo && !fallo"
        class="mapa-ou-norte pointer-events-none absolute inset-0 z-[5] overflow-hidden"
        :class="mostrandoNorte ? 'mapa-ou-norte-activo' : ''"
        aria-hidden="true"
      >
        <span class="mapa-ou-flecha-norte" :style="estiloFlechaNorte()">
          <svg viewBox="0 0 46 46" class="h-full w-full">
            <polygon points="23,3 40,41 23,32 6,41" fill="rgba(220, 38, 38, 0.55)" />
          </svg>
        </span>
        <span class="mapa-ou-letra-norte" :style="estiloLetraNorte()">N</span>
      </div>

      <div v-if="listo && !fallo" class="absolute right-3 top-3 z-10 flex gap-2">
        <div class="flex overflow-hidden rounded-xl border-2 border-slate-200 bg-white shadow-md">
          <button
            type="button"
            class="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider transition-colors duration-200"
            :class="!satelite ? 'bg-principal text-white' : 'bg-white text-principal hover:bg-niebla/60'"
            @click="cambiarVista(false)"
          >
            Mapa
          </button>
          <button
            type="button"
            class="px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider transition-colors duration-200"
            :class="satelite ? 'bg-principal text-white' : 'bg-white text-principal hover:bg-niebla/60'"
            @click="cambiarVista(true)"
          >
            Satélite
          </button>
        </div>

        <button
          type="button"
          title="Inclinar o aplanar el mapa"
          class="rounded-xl border-2 px-3 py-1.5 text-[11px] font-bold uppercase tracking-wider shadow-md transition-colors duration-200"
          :class="tresD ? 'border-principal bg-principal text-white' : 'border-slate-200 bg-white text-principal hover:bg-niebla/60'"
          @click="alternar3D"
        >
          3D
        </button>
      </div>

      <!-- Volver a mi ubicación: se resalta en amarillo cuando el punto quedó fuera de la pantalla. -->
      <button
        v-if="listo && !fallo && hayPunto"
        type="button"
        title="Volver a mi ubicación"
        aria-label="Volver a mi ubicación"
        class="absolute bottom-10 right-3 z-10 flex h-10 w-10 items-center justify-center rounded-xl border-2 shadow-md transition-all duration-200 active:scale-95"
        :class="
          perdido
            ? 'border-acento bg-acento text-principal hover:bg-yellow-400'
            : 'border-slate-200 bg-white text-principal hover:bg-niebla/60'
        "
        @click="volverAUbicacion"
      >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
          <circle cx="12" cy="12" r="3.5" />
          <circle cx="12" cy="12" r="7.5" />
          <path stroke-linecap="round" d="M12 2v3M12 19v3M2 12h3M19 12h3" />
        </svg>
      </button>
    </div>

    <p v-if="fallo" class="p-6 text-center text-sm font-semibold text-gris-medio">
      No se pudo cargar el mapa. Revisa tu conexión e inténtalo de nuevo.
    </p>

    <p
      v-else-if="editable"
      class="border-t border-slate-200 bg-niebla/40 px-3 py-2 text-center text-xs font-semibold text-gris-medio"
    >
      Toca el mapa o arrastra el marcador para ajustar tu ubicación.
    </p>
  </div>
</template>

<style>
/* Estilos del mapa. Van sin "scoped" porque MapLibre crea estos elementos fuera de Vue. */
.mapa-ou .maplibregl-map {
  font-family: inherit;
  background: #d8e4f2;
}

.mapa-ou .maplibregl-ctrl-group {
  border: 2px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
}

.mapa-ou .maplibregl-ctrl-group button {
  width: 32px;
  height: 32px;
}

.mapa-ou .maplibregl-ctrl-group button:hover {
  background-color: #d8e4f2;
}

.mapa-ou .maplibregl-ctrl-attribution {
  font-size: 10px;
}

/* Brújula original con la aguja en rojo (norte) y azul marino (sur). */
.mapa-ou .maplibregl-ctrl button.maplibregl-ctrl-compass .maplibregl-ctrl-icon {
  background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' width='29' height='29' viewBox='0 0 29 29'%3E%3Cpath d='M10.5 14l4-8 4 8h-8z' fill='%23dc2626'/%3E%3Cpath d='M10.5 16l4 8 4-8h-8z' fill='%2314305d'/%3E%3C/svg%3E") !important;
}

/* N roja translúcida del norte: aparece al presionar la brújula y se desvanece sola. */
.mapa-ou-norte {
  opacity: 0;
  transition: opacity 1.4s ease;
}

.mapa-ou-norte-activo {
  opacity: 1;
  transition-duration: 0.25s;
}

.mapa-ou-letra-norte {
  position: absolute;
  transform: translate(-50%, -50%);
  font-size: 110px;
  line-height: 1;
  font-weight: 900;
  color: rgba(220, 38, 38, 0.55);
  text-shadow: 0 0 18px rgba(255, 255, 255, 0.55);
}

.mapa-ou-flecha-norte {
  position: absolute;
  width: 46px;
  height: 46px;
  filter: drop-shadow(0 0 6px rgba(255, 255, 255, 0.55));
}

/* Marcador: pin amarillo estilo Google Maps con pulso en el suelo. */
.mapa-ou-icono {
  width: 36px;
  height: 48px;
  cursor: grab;
}

.mapa-ou-icono svg {
  position: absolute;
  top: 0;
  left: 0;
  width: 36px;
  height: 48px;
  filter: drop-shadow(0 3px 3px rgba(0, 0, 0, 0.4));
}

.mapa-ou-icono .mapa-ou-pulso {
  position: absolute;
  left: 50%;
  bottom: -6px;
  width: 30px;
  height: 12px;
  margin-left: -15px;
  border-radius: 9999px;
  background: rgba(237, 177, 12, 0.55);
  animation: mapa-ou-pulso 1.8s ease-out infinite;
}

@keyframes mapa-ou-pulso {
  0% {
    transform: scale(0.4);
    opacity: 0.9;
  }
  100% {
    transform: scale(1.9);
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .mapa-ou-icono .mapa-ou-pulso {
    animation: none;
    opacity: 0.4;
  }
}
</style>