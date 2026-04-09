<template>
  <div class="dashboard">
    <!-- ── Barra de acciones ─────────────────────────────────────────── -->
    <div class="action-bar">
      <div class="action-bar__left">
        <h1 class="action-bar__title">Dashboard de Rutas</h1>
        <p class="action-bar__sub">
          {{ orders.length }} pedidos ·
          {{ vehicles.length }} vehículos ·
          {{ routes.length }} rutas optimizadas
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
          <h2 class="card__title">🚚 Vehículos</h2>
          <ul class="vehicle-list">
            <li v-for="v in vehicles" :key="v.id" class="vehicle-item">
              <span class="vehicle-name">{{ v.name }}</span>
              <span class="vehicle-cap">Capacidad: {{ v.capacity }} kg</span>
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
      <RouteList :routes="routes" />
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import MapView   from '../components/MapView.vue'
import RouteList from '../components/RouteList.vue'
import OrderList from '../components/OrderList.vue'
import vrpApi    from '../api/vrp.js'

// -----------------------------------------------------------------------
// Estado
// -----------------------------------------------------------------------
const orders   = ref([])
const vehicles = ref([])
const routes   = ref([])
const loading  = ref(false)
const alert    = ref(null)

// -----------------------------------------------------------------------
// Carga inicial
// -----------------------------------------------------------------------
onMounted(loadAll)

async function loadAll() {
  try {
    const [ordersRes, vehiclesRes, routesRes] = await Promise.all([
      vrpApi.getOrders(),
      vrpApi.getVehicles(),
      vrpApi.getRoutes(),
    ])
    orders.value   = ordersRes.data.data
    vehicles.value = vehiclesRes.data.data
    routes.value   = routesRes.data.data
  } catch (err) {
    showAlert('error', 'No se pudo conectar con el backend. ¿Está Laravel corriendo en :8000?')
    console.error(err)
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

/* ── Layout principal ── */
.main-grid {
  display: grid;
  grid-template-columns: 1fr 340px;
  gap: 1.25rem;
  align-items: start;
}

.map-section { display: flex; flex-direction: column; }
.map-view    { flex: 1; min-height: 450px; }

.side-panel { display: flex; flex-direction: column; gap: 1.25rem; }

/* ── Vehículos ── */
.vehicle-list  { list-style: none; display: flex; flex-direction: column; gap: .4rem; }
.vehicle-item  { display: flex; justify-content: space-between; font-size: .85rem; padding: .35rem 0; border-bottom: 1px solid var(--color-border); }
.vehicle-item:last-child { border: none; }
.vehicle-name  { font-weight: 600; }
.vehicle-cap   { color: var(--color-muted); }
.list-empty    { color: var(--color-muted); font-style: italic; font-size: .85rem; }

/* ── Rutas ── */
.routes-section { }

/* ── Responsive ── */
@media (max-width: 900px) {
  .main-grid { grid-template-columns: 1fr; }
}
</style>
