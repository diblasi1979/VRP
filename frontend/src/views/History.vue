<template>
  <div class="history-page">
    <section class="history-hero">
      <div>
        <h1 class="history-hero__title">Consultas Históricas</h1>
        <p class="history-hero__sub">Rutas completadas filtrables por vehículo y fecha.</p>
      </div>
      <button class="btn-secondary" :disabled="loading" @click="loadHistory">Recargar</button>
    </section>

    <section class="history-filters">
      <label class="history-field">
        Vehículo
        <select v-model="filters.vehicle_id">
          <option value="">Todos</option>
          <option v-for="vehicle in vehicles" :key="vehicle.id" :value="String(vehicle.id)">
            {{ vehicle.name }}
          </option>
        </select>
      </label>

      <label class="history-field">
        Desde
        <input v-model="filters.date_from" type="date" />
      </label>

      <label class="history-field">
        Hasta
        <input v-model="filters.date_to" type="date" />
      </label>

      <div class="history-actions">
        <button class="btn-primary" :disabled="loading" @click="loadHistory">Buscar</button>
        <button class="btn-secondary" :disabled="loading" @click="resetFilters">Limpiar</button>
      </div>
    </section>

    <transition name="fade">
      <div v-if="alert" :class="['alert', `alert--${alert.type}`]" @click="alert = null">
        {{ alert.message }}
        <span class="alert__close">✕</span>
      </div>
    </transition>

    <section class="history-summary">
      <article class="history-stat">
        <span class="history-stat__label">Rutas</span>
        <strong>{{ routes.length }}</strong>
      </article>
      <article class="history-stat">
        <span class="history-stat__label">Kilómetros</span>
        <strong>{{ totalDistanceKm.toFixed(1) }} km</strong>
      </article>
      <article class="history-stat">
        <span class="history-stat__label">Paradas</span>
        <strong>{{ totalStops }}</strong>
      </article>
    </section>

    <section class="history-results">
      <div v-if="routes.length === 0" class="history-empty">
        No hay rutas históricas para los filtros seleccionados.
      </div>

      <div v-else class="history-route-list">
        <article
          v-for="route in routes"
          :key="route.id"
          class="history-route-card"
          @click="selectedRoute = route"
        >
          <div class="history-route-card__main">
            <div>
              <h2 class="history-route-card__title">{{ route.vehicle?.name ?? `Vehículo #${route.vehicle_id}` }}</h2>
              <p class="history-route-card__meta">
                {{ route.stops.length }} paradas
                <span v-if="route.completed_at">· {{ formatDate(route.completed_at) }}</span>
              </p>
            </div>
            <span class="badge badge-completed">{{ route.status }}</span>
          </div>

          <div class="history-route-card__stats">
            <span>📍 {{ formatDistance(route) }}</span>
            <span v-if="route.total_duration">⏱ {{ Math.round(route.total_duration / 60) }} min</span>
            <span>Ver detalle</span>
          </div>
        </article>
      </div>
    </section>

    <transition name="fade">
      <div v-if="selectedRoute" class="history-modal-overlay" @click.self="selectedRoute = null">
        <div class="history-modal">
          <div class="history-modal__header">
            <div>
              <h2 class="history-modal__title">{{ selectedRoute.vehicle?.name ?? `Vehículo #${selectedRoute.vehicle_id}` }}</h2>
              <p class="history-modal__sub">
                {{ selectedRoute.stops.length }} paradas · {{ formatDistance(selectedRoute) }}
                <span v-if="selectedRoute.completed_at">· {{ formatDate(selectedRoute.completed_at) }}</span>
              </p>
            </div>
            <button class="btn-secondary" @click="selectedRoute = null">Cerrar</button>
          </div>

          <ol class="history-stop-list">
            <li v-for="stop in selectedRoute.stops" :key="stop.id" class="history-stop-item">
              <span class="history-stop-item__seq">{{ stop.stop_sequence }}</span>
              <div class="history-stop-item__body">
                <strong>{{ stop.order?.address ?? `Pedido #${stop.order_id}` }}</strong>
                <div class="history-stop-item__meta">
                  <span v-if="stop.order" :class="['badge', `badge-${stop.order.status}`]">{{ stop.order.status }}</span>
                  <span v-if="stop.estimated_arrival">🕐 {{ stop.estimated_arrival }}</span>
                  <span v-if="stop.order">⚖ {{ stop.order.weight }} kg</span>
                  <span v-if="stop.order">🪟 {{ stop.order.time_window_start }}–{{ stop.order.time_window_end }}</span>
                </div>
              </div>
            </li>
          </ol>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import vrpApi from '../api/vrp.js'

const loading = ref(false)
const alert = ref(null)
const vehicles = ref([])
const routes = ref([])
const selectedRoute = ref(null)
const filters = ref({
  vehicle_id: '',
  date_from: '',
  date_to: '',
})

onMounted(async () => {
  await loadVehicles()
  await loadHistory()
})

async function loadVehicles() {
  const response = await vrpApi.getVehicles()
  vehicles.value = response.data.data
}

async function loadHistory() {
  loading.value = true

  try {
    const response = await vrpApi.getRoutes({
      status: 'completed',
      ...(filters.value.vehicle_id ? { vehicle_id: filters.value.vehicle_id } : {}),
      ...(filters.value.date_from ? { date_from: filters.value.date_from } : {}),
      ...(filters.value.date_to ? { date_to: filters.value.date_to } : {}),
    })

    routes.value = response.data.data
  } catch (error) {
    showAlert('error', error.response?.data?.message ?? 'No se pudo cargar el historial.')
  } finally {
    loading.value = false
  }
}

function resetFilters() {
  filters.value = {
    vehicle_id: '',
    date_from: '',
    date_to: '',
  }

  loadHistory()
}

const totalDistanceKm = computed(() => routes.value.reduce((sum, route) => sum + (Number(route.total_distance_km) || 0), 0))
const totalStops = computed(() => routes.value.reduce((sum, route) => sum + route.stops.length, 0))

function formatDistance(route) {
  return `${(Number(route.total_distance_km) || 0).toFixed(1)} km`
}

function formatDate(value) {
  return new Date(value).toLocaleString('es-AR')
}

function showAlert(type, message) {
  alert.value = { type, message }
  setTimeout(() => { alert.value = null }, 5000)
}
</script>

<style scoped>
.history-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem;
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.history-hero,
.history-filters,
.history-summary,
.history-results {
  background: var(--color-surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 1rem 1.25rem;
}

.history-hero {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}

.history-hero__title {
  font-size: 1.5rem;
  font-weight: 700;
}

.history-hero__sub {
  color: var(--color-muted);
}

.history-filters {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: .75rem;
  align-items: end;
}

.history-field {
  display: flex;
  flex-direction: column;
  gap: .35rem;
  font-size: .85rem;
  font-weight: 600;
}

.history-field input,
.history-field select {
  padding: .55rem .7rem;
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  font-size: .9rem;
  background: #fff;
}

.history-actions {
  display: flex;
  gap: .5rem;
  flex-wrap: wrap;
}

.history-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: .75rem;
}

.history-stat {
  border: 1px solid var(--color-border);
  border-radius: .8rem;
  padding: .8rem .9rem;
  background: #f8fafc;
}

.history-stat__label {
  display: block;
  font-size: .75rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--color-muted);
  margin-bottom: .2rem;
}

.history-empty {
  color: var(--color-muted);
  font-style: italic;
  text-align: center;
  padding: 1rem 0;
}

.history-route-list {
  display: flex;
  flex-direction: column;
  gap: .75rem;
}

.history-route-card {
  border: 1px solid var(--color-border);
  border-radius: .9rem;
  padding: 1rem;
  background: #f8fafc;
  cursor: pointer;
  transition: transform .15s ease, box-shadow .15s ease;
}

.history-route-card:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(15, 23, 42, .08);
}

.history-route-card__main,
.history-route-card__stats {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
}

.history-route-card__stats {
  margin-top: .65rem;
  color: var(--color-muted);
  font-size: .9rem;
}

.history-route-card__title {
  font-size: 1rem;
}

.history-route-card__meta {
  color: var(--color-muted);
  font-size: .85rem;
  margin-top: .15rem;
}

.history-modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, .55);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  z-index: 1100;
}

.history-modal {
  width: min(840px, 100%);
  max-height: 85vh;
  overflow: auto;
  background: var(--color-surface);
  border-radius: 1rem;
  box-shadow: 0 18px 50px rgba(15, 23, 42, .22);
  padding: 1.25rem;
}

.history-modal__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  margin-bottom: 1rem;
}

.history-modal__title {
  font-size: 1.15rem;
  font-weight: 700;
}

.history-modal__sub {
  color: var(--color-muted);
  margin-top: .2rem;
}

.history-stop-list {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: .65rem;
}

.history-stop-item {
  display: flex;
  gap: .75rem;
  padding: .85rem .9rem;
  border: 1px solid var(--color-border);
  border-radius: .85rem;
  background: #fff;
}

.history-stop-item__seq {
  min-width: 28px;
  height: 28px;
  border-radius: 50%;
  background: var(--color-primary);
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: .8rem;
}

.history-stop-item__body {
  flex: 1;
}

.history-stop-item__meta {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  margin-top: .35rem;
  color: var(--color-muted);
  font-size: .82rem;
}

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
.alert--error { background: #fee2e2; color: #991b1b; }
.alert__close { opacity: .6; }

.fade-enter-active, .fade-leave-active { transition: opacity .3s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

@media (max-width: 900px) {
  .history-hero {
    flex-direction: column;
    align-items: flex-start;
  }

  .history-modal__header {
    flex-direction: column;
  }
}
</style>