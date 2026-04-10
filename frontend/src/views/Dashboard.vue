<template>
  <div class="dashboard">
    <!-- ── Barra de acciones ─────────────────────────────────────────── -->
    <div class="action-bar">
      <div class="action-bar__left">
        <h1 class="action-bar__title">Dashboard de Rutas</h1>
        <p class="action-bar__sub">
          {{ orders.length }} pedidos ·
          {{ vehicles.length }} vehículos ·
          {{ routes.length }} rutas optimizadas ·
          {{ completedRoutes.length }} rutas históricas ·
          {{ totalDistanceKm.toFixed(1) }} km totales
        </p>
      </div>
      <div class="action-bar__buttons">
        <button
          class="btn-primary"
          :disabled="loading"
          @click="handleOptimize"
        >
          {{ loading ? '⏳ Optimizando…' : '🚀 Optimizar Rutas' }}
        </button>
        <button
          class="btn-secondary"
          :disabled="loading || routes.length === 0"
          @click="handleClear"
        >
          🗑 Limpiar Rutas
        </button>
        <button class="btn-secondary" :disabled="loading" @click="loadAll">
          🔄 Recargar
        </button>
      </div>
    </div>

    <!-- ── Alerta ────────────────────────────────────────────────────── -->
    <transition name="fade">
      <div v-if="alert" :class="['alert', `alert--${alert.type}`]" @click="alert = null">
        {{ alert.message }}
        <span class="alert__close">✕</span>
      </div>
    </transition>

    <!-- ── Grid principal ────────────────────────────────────────────── -->
    <div class="main-grid">
      <!-- Mapa -->
      <section class="card map-section">
        <h2 class="card__title">🗺 Mapa</h2>
        <MapView
          class="map-view"
          :orders="orders"
          :routes="routes"
          :depot="[-34.6345, -58.4012]"
        />
      </section>

      <!-- Panel derecho -->
      <aside class="side-panel">
        <!-- Vehículos -->
        <section class="card">
          <h2 class="card__title">
            🚚 Vehículos
            <button class="btn-add" title="Agregar vehículo" @click="showVehicleModal = true">+</button>
          </h2>
          <ul class="vehicle-list">
            <li v-for="v in vehicles" :key="v.id" class="vehicle-item">
              <span
                class="vehicle-dot"
                :style="{ background: vehicleColor[v.id] ?? '#94a3b8' }"
              />
              <span class="vehicle-info">
                <span class="vehicle-name">{{ v.name }}</span>
                <span v-if="v.start_address" class="vehicle-origin">📍 {{ v.start_address }}</span>
              </span>
              <span class="vehicle-cap">{{ v.capacity }} kg</span>
            </li>
            <li v-if="vehicles.length === 0" class="list-empty">Sin vehículos cargados.</li>
          </ul>
        </section>

        <!-- Pedidos -->
        <section class="card">
          <h2 class="card__title">📦 Pedidos</h2>
          <OrderList :orders="orders" />
        </section>
      </aside>
    </div>

    <!-- ── Rutas ─────────────────────────────────────────────────────── -->
    <section class="card routes-section">
      <h2 class="card__title">📋 Rutas Generadas</h2>
      <div v-if="routes.length > 0" class="route-summary-list">
        <div v-for="route in routes" :key="route.id" class="route-summary-item">
          <span class="route-summary-item__name">{{ route.vehicle?.name ?? `Vehículo #${route.vehicle_id}` }}</span>
          <strong class="route-summary-item__value">{{ formatRouteDistance(route) }}</strong>
        </div>
      </div>
      <RouteList :routes="routes" :allow-delivery="true" :loading-order-ids="loadingOrderIds" @mark-delivered="handleMarkDelivered" />
    </section>

    <section class="card routes-section">
      <h2 class="card__title">🗂 Historial de Rutas Completadas</h2>
      <div v-if="completedRoutes.length > 0" class="route-summary-list">
        <div v-for="route in completedRoutes" :key="route.id" class="route-summary-item">
          <span class="route-summary-item__name">
            {{ route.vehicle?.name ?? `Vehículo #${route.vehicle_id}` }}
            <small v-if="route.completed_at" class="route-summary-item__date">
              {{ new Date(route.completed_at).toLocaleString('es-AR') }}
            </small>
          </span>
          <strong class="route-summary-item__value">{{ formatRouteDistance(route) }}</strong>
        </div>
      </div>
      <RouteList :routes="completedRoutes" />
    </section>
    <!-- ── Modal: agregar vehículo ────────────────────────────────────────────── -->
    <transition name="fade">
      <div v-if="showVehicleModal" class="modal-overlay" @click.self="showVehicleModal = false">
        <div class="modal">
          <h3 class="modal__title">🚚 Agregar Vehículo</h3>
          <form class="modal__form" @submit.prevent="handleAddVehicle">
            <label class="modal__label">
              Nombre *
              <input v-model="vForm.name" required maxlength="100" placeholder="Furgón BA-04" />
            </label>
            <label class="modal__label">
              Dirección de origen
              <input v-model="vForm.start_address" placeholder="Av. Caseros 2900, Parque Patricios, CABA" />
            </label>
            <div class="modal__row">
              <label class="modal__label">
                Capacidad (kg) *
                <input v-model.number="vForm.capacity" type="number" min="1" step="0.01" required placeholder="500" />
              </label>
            </div>
            <div class="modal__row">
              <label class="modal__label">
                Latitud *
                <input v-model.number="vForm.start_lat" type="number" step="any" required placeholder="-34.6345" />
              </label>
              <label class="modal__label">
                Longitud *
                <input v-model.number="vForm.start_lng" type="number" step="any" required placeholder="-58.4012" />
              </label>
            </div>
            <div class="modal__actions">
              <button type="button" class="btn-secondary" @click="showVehicleModal = false">Cancelar</button>
              <button type="submit" class="btn-primary" :disabled="loading">Guardar</button>
            </div>
          </form>
        </div>
      </div>
    </transition>  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import MapView   from '../components/MapView.vue'
import RouteList from '../components/RouteList.vue'
import OrderList from '../components/OrderList.vue'
import vrpApi    from '../api/vrp.js'

// Paleta sincronizada con MapView y RouteList
const ROUTE_PALETTE = [
  '#2563eb', '#dc2626', '#16a34a', '#d97706',
  '#7c3aed', '#0891b2', '#be185d', '#0d9488',
]

// -----------------------------------------------------------------------
// Estado
// -----------------------------------------------------------------------
const orders   = ref([])
const vehicles = ref([])
const routes   = ref([])
const completedRoutes = ref([])
const loading  = ref(false)
const alert    = ref(null)
const loadingOrderIds = ref([])

const showVehicleModal = ref(false)
const vForm = ref({ name: '', start_address: '', capacity: '', start_lat: '', start_lng: '' })

// -----------------------------------------------------------------------
// Carga inicial
// -----------------------------------------------------------------------
onMounted(loadAll)

async function loadAll() {
  try {
    const [ordersRes, vehiclesRes, routesRes, completedRoutesRes] = await Promise.all([
      vrpApi.getOrders(),
      vrpApi.getVehicles(),
      vrpApi.getRoutes('active'),
      vrpApi.getRoutes('completed'),
    ])
    orders.value   = ordersRes.data.data
    vehicles.value = vehiclesRes.data.data
    routes.value   = routesRes.data.data
    completedRoutes.value = completedRoutesRes.data.data
  } catch (err) {
    showAlert('error', 'No se pudo conectar con el backend. ¿Está Laravel corriendo en :8000?')
    console.error(err)
  }
}

// -----------------------------------------------------------------------
// Agregar vehículo
// -----------------------------------------------------------------------
async function handleAddVehicle() {
  loading.value = true
  try {
    await vrpApi.createVehicle(vForm.value)
    showVehicleModal.value = false
    vForm.value = { name: '', start_address: '', capacity: '', start_lat: '', start_lng: '' }
    await loadAll()
    showAlert('success', 'Vehículo agregado correctamente.')
  } catch (err) {
    const msg = err.response?.data?.message ?? 'Error al guardar el vehículo.'
    showAlert('error', msg)
  } finally {
    loading.value = false
  }
}

// -----------------------------------------------------------------------
// Optimizar
// -----------------------------------------------------------------------
async function handleOptimize() {
  loading.value = true
  alert.value   = null
  try {
    const res = await vrpApi.optimizeRoutes()
    showAlert('success', res.data.message)
    await loadAll()
  } catch (err) {
    const msg = err.response?.data?.message ?? 'Error al optimizar rutas.'
    showAlert('error', msg)
  } finally {
    loading.value = false
  }
}

// -----------------------------------------------------------------------
// Limpiar rutas
// -----------------------------------------------------------------------
async function handleClear() {
  if (!confirm('¿Eliminar todas las rutas y volver los pedidos a "pending"?')) return
  loading.value = true
  try {
    await vrpApi.clearRoutes()
    showAlert('success', 'Rutas eliminadas. Pedidos restablecidos.')
    await loadAll()
  } catch (err) {
    showAlert('error', 'Error al eliminar rutas.')
  } finally {
    loading.value = false
  }
}

async function handleMarkDelivered(order) {
  if (!order?.id) {
    return
  }

  loadingOrderIds.value = [...loadingOrderIds.value, order.id]

  try {
    await vrpApi.updateOrderStatus(order.id, 'delivered')
    showAlert('success', `Pedido #${order.id} marcado como entregado.`)
    await loadAll()
  } catch (err) {
    showAlert('error', err.response?.data?.message ?? 'No se pudo marcar la parada como entregada.')
  } finally {
    loadingOrderIds.value = loadingOrderIds.value.filter((id) => id !== order.id)
  }
}

// -----------------------------------------------------------------------
// Color de ruta por vehículo
// -----------------------------------------------------------------------
const vehicleColor = computed(() => {
  const map = {}
  routes.value.forEach((r, idx) => {
    map[r.vehicle_id] = ROUTE_PALETTE[idx % ROUTE_PALETTE.length]
  })
  return map
})

const totalDistanceKm = computed(() => {
  return routes.value.reduce((sum, route) => sum + getRouteDistanceKm(route), 0)
})

function getRouteDistanceKm(route) {
  if (route.total_distance_km !== null && route.total_distance_km !== undefined) {
    return Number(route.total_distance_km)
  }

  if (route.total_distance !== null && route.total_distance !== undefined) {
    return route.total_distance / 1000
  }

  return 0
}

function formatRouteDistance(route) {
  return `${getRouteDistanceKm(route).toFixed(1)} km`
}

// -----------------------------------------------------------------------
// Alertas
// -----------------------------------------------------------------------
function showAlert(type, message) {
  alert.value = { type, message }
  setTimeout(() => { alert.value = null }, 6000)
}
</script>

<style scoped>
.dashboard {
  max-width: 1400px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

/* ── Barra de acciones ── */
.action-bar {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  flex-wrap: wrap;
  gap: .75rem;
}
.action-bar__title { font-size: 1.4rem; font-weight: 700; }
.action-bar__sub   { font-size: .85rem; color: var(--color-muted); margin-top: .1rem; }
.action-bar__buttons { display: flex; gap: .5rem; flex-wrap: wrap; }

/* ── Alerta ── */
.alert {
  padding: .75rem 1rem;
  border-radius: var(--radius);
  font-size: .9rem;
  font-weight: 500;
  display: flex;
  justify-content: space-between;
  cursor: pointer;
}
.alert--success { background: #d1fae5; color: #065f46; }
.alert--error   { background: #fee2e2; color: #991b1b; }
.alert__close   { opacity: .6; }
.fade-enter-active, .fade-leave-active { transition: opacity .3s; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

/* ── Cards ── */
.card {
  background: var(--color-surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 1rem 1.25rem;
}
.card__title {
  font-size: 1rem;
  font-weight: 700;
  margin-bottom: .75rem;
  padding-bottom: .5rem;
  border-bottom: 1px solid var(--color-border);
}

.route-summary-list {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: .75rem;
  margin-bottom: 1rem;
}

.route-summary-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: .75rem;
  padding: .8rem .9rem;
  border: 1px solid var(--color-border);
  border-radius: .8rem;
  background: #f8fafc;
}

.route-summary-item__name {
  display: flex;
  flex-direction: column;
  font-size: .85rem;
  color: #0f172a;
}

.route-summary-item__date {
  font-size: .72rem;
  color: var(--color-muted);
}

.route-summary-item__value {
  white-space: nowrap;
  color: #0f172a;
}

/* ── Layout principal ── */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.25rem;
  align-items: start;
}

.map-section { display: flex; flex-direction: column; }
.map-view    { flex: 1; min-height: 560px; }

.side-panel { display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Vehículos ── */
.vehicle-list  { list-style: none; display: flex; flex-direction: column; gap: .4rem; }
.vehicle-item  { display: flex; align-items: center; gap: .5rem; font-size: .85rem; padding: .35rem 0; border-bottom: 1px solid var(--color-border); }
.vehicle-item:last-child { border: none; }
.vehicle-dot   { width: 11px; height: 11px; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 0 2px rgba(0,0,0,.08); }
.vehicle-info  { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.vehicle-name  { font-weight: 600; }
.vehicle-origin { font-size: .75rem; color: var(--color-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.vehicle-cap   { color: var(--color-muted); white-space: nowrap; }
.list-empty    { color: var(--color-muted); font-style: italic; font-size: .85rem; }
.btn-add {
  margin-left: auto;
  background: var(--color-primary);
  color: #fff;
  border: none;
  border-radius: 50%;
  width: 22px; height: 22px;
  font-size: 1rem; line-height: 1;
  cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.btn-add:hover { background: #1d4ed8; }

/* ── Modal ── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal {
  background: var(--color-surface);
  border-radius: var(--radius);
  box-shadow: 0 8px 32px rgba(0,0,0,.22);
  padding: 1.5rem;
  width: 100%;
  max-width: 420px;
}
.modal__title {
  font-size: 1.05rem;
  font-weight: 700;
  margin-bottom: 1rem;
}
.modal__form  { display: flex; flex-direction: column; gap: .75rem; }
.modal__label { display: flex; flex-direction: column; gap: .25rem; font-size: .85rem; font-weight: 500; }
.modal__label input {
  padding: .45rem .6rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  font-size: .9rem;
  outline: none;
}
.modal__label input:focus { border-color: var(--color-primary); }
.modal__row    { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
.modal__actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: .25rem; }

/* ── Responsive ── */
@media (max-width: 900px) {
  .main-grid { grid-template-columns: 1fr; }
}
</style>
