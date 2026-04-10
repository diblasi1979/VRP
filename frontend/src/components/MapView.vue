<template>
  <div class="map-wrapper">

    <!-- ── Leyenda flotante ──────────────────────────────────────────── -->
    <transition name="legend-fade">
      <div v-if="routes.length > 0" class="map-legend">
        <div class="map-legend__header" @click="expanded = !expanded">
          <span>📍 Rutas optimizadas ({{ routes.length }})</span>
          <span class="map-legend__chevron">{{ expanded ? '▲' : '▼' }}</span>
        </div>
        <div v-show="expanded" class="map-legend__body">
          <div
            v-for="(route, idx) in routes"
            :key="route.id"
            :class="['legend-row', {
              'legend-row--active': focused === idx,
              'legend-row--dim':    focused !== null && focused !== idx,
            }]"
            @click="focusRoute(idx)"
          >
            <span class="legend-swatch" :style="{ background: ROUTE_COLORS[idx % ROUTE_COLORS.length] }" />
            <div class="legend-info">
              <span class="legend-name">{{ route.vehicle?.name ?? `Ruta ${idx + 1}` }}</span>
              <span class="legend-meta">
                {{ route.stops.length }} paradas
                {{ route.total_distance ? '· ' + (route.total_distance / 1000).toFixed(1) + ' km' : '' }}
                {{ route.total_duration ? '· ' + formatDuration(route.total_duration) : '' }}
              </span>
            </div>
            <span v-if="focused === idx" class="legend-active-dot" />
          </div>
          <button v-if="focused !== null" class="legend-reset" @click.stop="focusRoute(null)">
            ← Ver todas las rutas
          </button>
        </div>
      </div>
    </transition>

    <!-- ── Mapa ─────────────────────────────────────────────────────── -->
    <div ref="mapRef" class="leaflet-map" />

    <!-- ── Hint cuando no hay rutas ─────────────────────────────────── -->
    <transition name="legend-fade">
      <div v-if="orders.length > 0 && routes.length === 0" class="map-hint">
        Pulsa <strong>🚀 Optimizar Rutas</strong> para ver el recorrido
      </div>
    </transition>

  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Fix Leaflet default icon path para Vite
import iconUrl from 'leaflet/dist/images/marker-icon.png'
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png'
import shadowUrl from 'leaflet/dist/images/marker-shadow.png'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl })

// ─── Props ──────────────────────────────────────────────────────────────────
const props = defineProps({
  /** Array de objetos Order (con lat, lng, address, status) */
  orders: { type: Array, default: () => [] },
  /** Array de objetos Route (con vehicle, stops[].order) */
  routes: { type: Array, default: () => [] },
  /** Coordenadas del depósito [lat, lng] */
  depot:  { type: Array, default: () => [-34.6345, -58.4012] },
})

// ─── Paleta de colores (sincronizada con RouteList y Dashboard) ──────────────
const ROUTE_COLORS = [
  '#2563eb', '#dc2626', '#16a34a', '#d97706',
  '#7c3aed', '#0891b2', '#be185d', '#0d9488',
]

// ─── Estado reactivo ─────────────────────────────────────────────────────────
const mapRef   = ref(null)
const expanded = ref(true)
const focused  = ref(null)   // índice de ruta filtrada, null = todas

let map = null
let depotMarker = null   // referencia al marcador del depósito

// Grupos por ruta: [{ group: L.LayerGroup, idx: number }]
const routeGroups = []
// Marcadores de pedidos: { [orderId]: L.Marker }
const orderMarkers = {}

// ─── Ciclo de vida ───────────────────────────────────────────────────────────
onMounted(async () => {
  // nextTick garantiza que el div ya tiene dimensiones reales en el DOM
  await nextTick()

  map = L.map(mapRef.value, { center: props.depot, zoom: 11 })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/" target="_blank">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map)

  renderDepot()
  renderAll()

  // Fuerza a Leaflet a recalcular el viewport tras el primer paint
  setTimeout(() => map?.invalidateSize(), 200)
})

onUnmounted(() => { if (map) { map.remove(); map = null } })

// ─── Watch combinado — reacciona a cambios en pedidos O rutas ────────────────
watch(
  () => [props.orders, props.routes],
  async () => {
    await nextTick()
    clearAll()
    renderAll()
    map?.invalidateSize()
  },
  { deep: true }
)

// ─── Depósito ────────────────────────────────────────────────────────────────
function renderDepot() {
  if (depotMarker) return   // ya está en el mapa, no duplicar

  const html = `<div style="
    background:#1e293b;color:#fff;
    width:38px;height:38px;border-radius:50%;
    display:flex;align-items:center;justify-content:center;
    font-size:18px;border:3px solid #fff;
    box-shadow:0 3px 10px rgba(0,0,0,.55);">🏭</div>`

  depotMarker = L.marker(props.depot, {
    icon: L.divIcon({ className: '', html, iconSize: [38, 38], iconAnchor: [19, 19] }),
    zIndexOffset: 2000,
  })
    .bindPopup('<strong>Depósito</strong><br/>Punto de inicio y fin de todas las rutas')
    .addTo(map)
}

// ─── Render principal ────────────────────────────────────────────────────────
function renderAll() {
  renderOrderMarkers()
  renderRouteGroups()
}

// ─── Marcadores de pedidos ───────────────────────────────────────────────────
function renderOrderMarkers() {
  const idx = buildRouteIndex()

  props.orders.forEach((order) => {
    const assigned = idx[order.id]
    let bg, label, ring

    if (assigned) {
      bg    = assigned.color
      label = assigned.seq       // número de secuencia en la ruta
      ring  = '#fff'
    } else if (order.status === 'delivered') {
      bg = '#16a34a'; label = '✓'; ring = '#fff'
    } else {
      bg = '#94a3b8'; label = order.id; ring = '#cbd5e1'
    }

    const html = `<div style="
      background:${bg};color:#fff;
      width:30px;height:30px;border-radius:50%;
      display:flex;align-items:center;justify-content:center;
      font-size:12px;font-weight:700;
      border:2.5px solid ${ring};
      box-shadow:0 2px 6px rgba(0,0,0,.4);
      transition:opacity .2s,transform .2s;">${label}</div>`

    const marker = L.marker([order.lat, order.lng], {
      icon: L.divIcon({ className: '', html, iconSize: [30, 30], iconAnchor: [15, 15] }),
      zIndexOffset: 500,
    }).bindPopup(`
      <div style="min-width:210px;line-height:1.7">
        <strong>#${order.id}</strong> — ${order.address}<br/>
        ⚖ ${order.weight} kg &nbsp;|&nbsp; 🪟 ${order.time_window_start}–${order.time_window_end}<br/>
        ${assigned
          ? `🚚 <strong>${assigned.routeName}</strong> · Parada #${assigned.seq}`
          : `Estado: <em>${order.status}</em>`
        }
      </div>
    `).addTo(map)

    orderMarkers[order.id] = marker
  })
}

// ─── Grupos de ruta (polilíneas + flechas de dirección) ─────────────────────
function renderRouteGroups() {
  props.routes.forEach((route, idx) => {
    const color = ROUTE_COLORS[idx % ROUTE_COLORS.length]
    const group = L.layerGroup().addTo(map)

    // Coordenadas: depósito → paradas en secuencia → depósito
    const coords = [props.depot]
    route.stops.forEach((stop) => {
      if (stop.order) coords.push([stop.order.lat, stop.order.lng])
    })
    coords.push(props.depot)

    if (coords.length < 2) return

    // Polilínea sombra blanca (legibilidad sobre tiles claros y oscuros)
    L.polyline(coords, { color: '#fff', weight: 10, opacity: 0.55 }).addTo(group)

    // Polilínea principal coloreada
    L.polyline(coords, { color, weight: 5, opacity: 0.92 })
      .bindPopup(`
        <strong>${route.vehicle?.name ?? 'Vehículo'}</strong><br/>
        🛑 ${route.stops.length} paradas<br/>
        📍 ${route.total_distance ? (route.total_distance / 1000).toFixed(1) + ' km' : '—'}<br/>
        ⏱ ${route.total_duration ? formatDuration(route.total_duration) : '—'}
      `)
      .addTo(group)

    // Flechas SVG rotadas en cada segmento (dirección de recorrido)
    for (let i = 0; i < coords.length - 1; i++) {
      const from = coords[i]
      const to   = coords[i + 1]
      if (from[0] === to[0] && from[1] === to[1]) continue

      const pos   = interpolate(from, to, 0.54)
      const angle = calcBearing(from, to)

      const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 14" width="10" height="14">
        <polygon points="5,0 10,14 5,9.5 0,14" fill="${color}" stroke="#fff" stroke-width="1.2"/>
      </svg>`

      L.marker(pos, {
        icon: L.divIcon({
          className: '',
          html: `<div style="transform:rotate(${angle}deg);transform-origin:center;line-height:0;pointer-events:none">${svg}</div>`,
          iconSize: [10, 14],
          iconAnchor: [5, 7],
        }),
        interactive: false,
        keyboard: false,
      }).addTo(group)
    }

    routeGroups.push({ group, idx, color })
  })

  // Auto-zoom al conjunto de todas las rutas tras renderizar
  if (routeGroups.length > 0) zoomToRoutes(null)
}

// ─── Foco / filtro por leyenda ───────────────────────────────────────────────
function focusRoute(idxClicked) {
  // Click en la misma ruta o en null → quitar filtro
  focused.value = (idxClicked === null || focused.value === idxClicked) ? null : idxClicked
  applyFocus()
}

function formatDuration(totalSeconds) {
  const totalMinutes = Math.max(0, Math.round(totalSeconds / 60))
  const days = Math.floor(totalMinutes / 1440)
  const remainingMinutesAfterDays = totalMinutes % 1440
  const hours = Math.floor(remainingMinutesAfterDays / 60)
  const minutes = remainingMinutesAfterDays % 60

  const parts = []

  if (days > 0) {
    parts.push(`${days} ${days === 1 ? 'día' : 'días'}`)
  }

  if (hours > 0 || days > 0) {
    parts.push(`${hours} ${hours === 1 ? 'hora' : 'horas'}`)
  }

  parts.push(`${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}`)

  return parts.join(', ')
}

function applyFocus() {
  // Mostrar u ocultar cada grupo de ruta
  routeGroups.forEach(({ group, idx }) => {
    const show = focused.value === null || idx === focused.value
    if (show && !map.hasLayer(group)) map.addLayer(group)
    if (!show && map.hasLayer(group))  map.removeLayer(group)
  })

  // Ajustar opacidad y filtro de los marcadores
  const routeIdx = buildRouteIndex()
  props.orders.forEach((order) => {
    const el = orderMarkers[order.id]?.getElement()
    if (!el) return
    const assigned  = routeIdx[order.id]
    const highlight = focused.value === null || (assigned && assigned.routeIdx === focused.value)
    el.style.opacity = highlight ? '1'             : '0.15'
    el.style.filter  = highlight ? 'none'          : 'grayscale(90%)'
    el.style.transform = highlight && focused.value !== null ? 'scale(1.15)' : 'scale(1)'
  })

  // Zoom al conjunto de rutas visibles
  zoomToRoutes(focused.value)
}

function zoomToRoutes(routeIdx) {
  const targetRoutes = routeIdx !== null
    ? [props.routes[routeIdx]].filter(Boolean)
    : props.routes

  const coords = targetRoutes.flatMap((r) =>
    r.stops.filter((s) => s.order).map((s) => [s.order.lat, s.order.lng])
  )
  coords.push(props.depot)

  if (coords.length > 1) {
    map.fitBounds(L.latLngBounds(coords).pad(0.14))
  }
}

// ─── Limpiar capas de rutas y pedidos (el depósito se mantiene) ─────────────
function clearAll() {
  routeGroups.forEach(({ group }) => { group.clearLayers(); if (map.hasLayer(group)) map.removeLayer(group) })
  routeGroups.length = 0
  Object.values(orderMarkers).forEach((m) => m.remove())
  for (const k in orderMarkers) delete orderMarkers[k]
  focused.value = null
}

// ─── Helpers ────────────────────────────────────────────────────────────────

/** Construye un índice orderId → { seq, color, routeName, routeIdx } */
function buildRouteIndex() {
  const index = {}
  props.routes.forEach((route, routeIdx) => {
    route.stops.forEach((stop) => {
      const orderId = stop.order_id ?? stop.order?.id
      if (orderId !== undefined) {
        index[orderId] = {
          seq:       stop.stop_sequence,
          color:     ROUTE_COLORS[routeIdx % ROUTE_COLORS.length],
          routeName: route.vehicle?.name ?? `Ruta ${routeIdx + 1}`,
          routeIdx,
        }
      }
    })
  })
  return index
}

/** Rumbo verdadero en grados (0=Norte, 90=Este) entre dos coordenadas [lat,lng] */
function calcBearing(from, to) {
  const toRad = (d) => (d * Math.PI) / 180
  const φ1 = toRad(from[0])
  const φ2 = toRad(to[0])
  const Δλ = toRad(to[1] - from[1])
  const y   = Math.sin(Δλ) * Math.cos(φ2)
  const x   = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(Δλ)
  return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360
}

/** Punto interpolado entre dos coordenadas a fracción t ∈ [0,1] */
function interpolate(from, to, t) {
  return [from[0] + (to[0] - from[0]) * t, from[1] + (to[1] - from[1]) * t]
}
</script>

<style scoped>
/* ─── Contenedor ─────────────────────────────────────────────────── */
.map-wrapper {
  position: relative;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  /* NO overflow:hidden — la eliminamos para que Leaflet no quede recortado */
}

.leaflet-map {
  /* Altura fija explícita: Leaflet no funciona bien con height:100%
     dentro de flex/grid sin altura definida en el ancestro. */
  height: 560px;
  width: 100%;
  border-radius: var(--radius);
}

/* ─── Leyenda flotante ───────────────────────────────────────────── */
.map-legend {
  position: absolute;
  top: 12px;
  right: 12px;
  z-index: 1000;
  background: rgba(255, 255, 255, 0.96);
  border-radius: 10px;
  box-shadow: 0 2px 14px rgba(0,0,0,.18);
  min-width: 220px;
  max-width: 280px;
  overflow: hidden;
  backdrop-filter: blur(4px);
}

.map-legend__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: .55rem .85rem;
  background: #f8fafc;
  border-bottom: 1px solid #e2e8f0;
  cursor: pointer;
  font-weight: 700;
  font-size: .8rem;
  color: var(--color-text);
  user-select: none;
}

.map-legend__chevron { font-size: .65rem; color: var(--color-muted); }

.map-legend__body { padding: .4rem 0 .5rem; }

/* ─── Filas de ruta en leyenda ───────────────────────────────────── */
.legend-row {
  display: flex;
  align-items: center;
  gap: .55rem;
  padding: .4rem .85rem;
  cursor: pointer;
  transition: background .12s, opacity .15s;
}

.legend-row:hover      { background: #f1f5f9; }
.legend-row--active    { background: #eff6ff; }
.legend-row--dim       { opacity: .32; }

.legend-swatch {
  width: 13px; height: 13px;
  border-radius: 50%;
  flex-shrink: 0;
  box-shadow: 0 0 0 2px rgba(0,0,0,.1);
}

.legend-info   { flex: 1; line-height: 1.4; min-width: 0; }
.legend-name   { display: block; font-weight: 600; font-size: .82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.legend-meta   { display: block; font-size: .72rem; color: var(--color-muted); }

.legend-active-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--color-primary); flex-shrink: 0;
}

.legend-reset {
  display: block;
  width: calc(100% - 1.7rem);
  margin: .4rem .85rem 0;
  padding: .3rem .6rem;
  font-size: .75rem; font-weight: 600;
  color: var(--color-primary);
  background: #eff6ff;
  border: 1px solid #bfdbfe;
  border-radius: 6px;
  cursor: pointer;
  text-align: center;
  transition: background .12s;
}
.legend-reset:hover { background: #dbeafe; }

/* ─── Hint inicial ───────────────────────────────────────────────── */
.map-hint {
  position: absolute;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 1000;
  background: rgba(255,255,255,.94);
  border: 1px solid #e2e8f0;
  border-radius: 999px;
  padding: .45rem 1.25rem;
  font-size: .8rem;
  color: var(--color-muted);
  white-space: nowrap;
  pointer-events: none;
  box-shadow: 0 2px 8px rgba(0,0,0,.1);
}

/* ─── Transición ─────────────────────────────────────────────────── */
.legend-fade-enter-active,
.legend-fade-leave-active { transition: opacity .25s, transform .2s; }
.legend-fade-enter-from,
.legend-fade-leave-to     { opacity: 0; transform: translateY(-6px); }
</style>
