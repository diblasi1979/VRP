<template>
  <div class="map-wrapper">
    <div ref="mapRef" class="leaflet-map" />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Fix Leaflet default icon path para Vite
import iconUrl from 'leaflet/dist/images/marker-icon.png'
import iconRetinaUrl from 'leaflet/dist/images/marker-icon-2x.png'
import shadowUrl from 'leaflet/dist/images/marker-shadow.png'

delete L.Icon.Default.prototype._getIconUrl
L.Icon.Default.mergeOptions({ iconUrl, iconRetinaUrl, shadowUrl })

// -----------------------------------------------------------------------
// Props
// -----------------------------------------------------------------------
const props = defineProps({
  /** Array de objetos Order (con lat, lng, address, status) */
  orders: { type: Array, default: () => [] },
  /** Array de objetos Route (con vehicle, stops[].order) */
  routes: { type: Array, default: () => [] },
  /** Coordenadas del depósito [lat, lng] */
  depot: { type: Array, default: () => [40.4654, -3.6891] },
})

// -----------------------------------------------------------------------
// Paleta de colores por ruta
// -----------------------------------------------------------------------
const ROUTE_COLORS = [
  '#2563eb', '#dc2626', '#16a34a', '#d97706',
  '#7c3aed', '#0891b2', '#be185d', '#0d9488',
]

// -----------------------------------------------------------------------
// Estado interno
// -----------------------------------------------------------------------
const mapRef = ref(null)
let map = null
let depotMarker = null
const orderLayers = []
const routeLayers = []

// -----------------------------------------------------------------------
// Inicializar mapa
// -----------------------------------------------------------------------
onMounted(() => {
  map = L.map(mapRef.value, {
    center: props.depot,
    zoom: 11,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/">OpenStreetMap</a>',
    maxZoom: 19,
  }).addTo(map)

  renderDepot()
  renderOrders()
  renderRoutes()
})

onUnmounted(() => {
  if (map) { map.remove(); map = null }
})

// -----------------------------------------------------------------------
// Re-renderizar cuando cambian los datos
// -----------------------------------------------------------------------
watch(() => props.orders, () => { clearOrders(); renderOrders() }, { deep: true })
watch(() => props.routes, () => { clearRoutes(); renderRoutes() }, { deep: true })

// -----------------------------------------------------------------------
// Renderizado
// -----------------------------------------------------------------------

function renderDepot() {
  const icon = L.divIcon({
    className: '',
    html: '<div style="background:#1e293b;color:#fff;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;font-size:16px;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4)">🏭</div>',
    iconSize: [32, 32],
    iconAnchor: [16, 16],
  })
  depotMarker = L.marker(props.depot, { icon })
    .bindPopup('<strong>Depósito</strong>')
    .addTo(map)
}

function renderOrders() {
  props.orders.forEach((order) => {
    const color = order.status === 'assigned' ? '#2563eb' : order.status === 'delivered' ? '#16a34a' : '#d97706'
    const icon = L.divIcon({
      className: '',
      html: `<div style="background:${color};color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35)">${order.id}</div>`,
      iconSize: [26, 26],
      iconAnchor: [13, 13],
    })
    const marker = L.marker([order.lat, order.lng], { icon })
      .bindPopup(`
        <strong>#${order.id} — ${order.address}</strong><br/>
        Peso: ${order.weight} kg<br/>
        Ventana: ${order.time_window_start} – ${order.time_window_end}<br/>
        Estado: <em>${order.status}</em>
      `)
      .addTo(map)
    orderLayers.push(marker)
  })
}

function renderRoutes() {
  props.routes.forEach((route, idx) => {
    const color = ROUTE_COLORS[idx % ROUTE_COLORS.length]

    // Construir polilínea: depósito → paradas ordenadas → depósito
    const coords = [props.depot]
    route.stops.forEach((stop) => {
      if (stop.order) coords.push([stop.order.lat, stop.order.lng])
    })
    coords.push(props.depot)

    const polyline = L.polyline(coords, {
      color,
      weight: 4,
      opacity: 0.8,
      dashArray: null,
    }).addTo(map)

    polyline.bindPopup(
      `<strong>${route.vehicle?.name ?? 'Vehículo'}</strong><br/>
       Paradas: ${route.stops.length}<br/>
       Distancia: ${route.total_distance ? (route.total_distance / 1000).toFixed(1) + ' km' : '—'}<br/>
       Duración: ${route.total_duration ? Math.round(route.total_duration / 60) + ' min' : '—'}`
    )

    routeLayers.push(polyline)
  })

  // Auto-zoom si hay rutas
  if (routeLayers.length > 0) {
    const group = L.featureGroup(routeLayers)
    map.fitBounds(group.getBounds().pad(0.15))
  }
}

function clearOrders() {
  orderLayers.forEach((l) => l.remove())
  orderLayers.length = 0
}

function clearRoutes() {
  routeLayers.forEach((l) => l.remove())
  routeLayers.length = 0
}
</script>

<style scoped>
.map-wrapper {
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: var(--shadow);
  height: 100%;
  min-height: 420px;
}
.leaflet-map { height: 100%; width: 100%; }
</style>
