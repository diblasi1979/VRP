<template>
  <div class="route-list">
    <div v-if="routes.length === 0" class="route-list__empty">
      No hay rutas optimizadas todavía. Pulsa <em>Optimizar Rutas</em>.
    </div>

    <div
      v-for="(route, idx) in routes"
      :key="route.id"
      class="route-card"
      :style="{ '--route-color': routeColors[idx % routeColors.length] }"
    >
      <!-- Cabecera -->
      <div class="route-card__header">
        <span class="route-card__dot" />
        <div class="route-card__title">
          <strong>{{ route.vehicle?.name ?? `Vehículo #${route.vehicle_id}` }}</strong>
          <span :class="['badge', `badge-${route.status}`]">{{ route.status }}</span>
        </div>
        <div v-if="route.total_distance_km !== null" class="route-card__distance">
          <span class="route-card__distance-label">Distancia</span>
          <strong>{{ formatDistance(route) }}</strong>
        </div>
        <div class="route-card__meta">
          <span v-if="route.total_duration">⏱ {{ formatDuration(route.total_duration) }}</span>
          <span>🛑 {{ route.stops.length }} paradas</span>
        </div>
      </div>

      <!-- Paradas -->
      <ol class="stop-list">
        <li
          v-for="stop in route.stops"
          :key="stop.id"
          class="stop-item"
        >
          <span class="stop-seq">{{ stop.stop_sequence }}</span>
          <div class="stop-info">
            <span class="stop-address">{{ stop.order?.address ?? `Pedido #${stop.order_id}` }}</span>
            <div class="stop-details">
              <span v-if="stop.order" :class="['badge', `badge-${stop.order.status}`]">{{ stop.order.status }}</span>
              <span v-if="stop.estimated_arrival">🕐 {{ stop.estimated_arrival }}</span>
              <span v-if="stop.order">⚖ {{ stop.order.weight }} kg</span>
              <span v-if="stop.order">
                🪟 {{ stop.order.time_window_start }}–{{ stop.order.time_window_end }}
              </span>
            </div>
          </div>
          <button
            v-if="allowDelivery && stop.order && stop.order.status !== 'delivered'"
            class="btn-primary stop-action"
            :disabled="loadingOrderIds.includes(stop.order.id)"
            @click="$emit('mark-delivered', stop.order)"
          >
            {{ loadingOrderIds.includes(stop.order.id) ? 'Guardando…' : 'Entregar' }}
          </button>
        </li>
      </ol>
    </div>
  </div>
</template>

<script setup>
defineEmits(['mark-delivered'])

defineProps({
  routes: { type: Array, default: () => [] },
  allowDelivery: { type: Boolean, default: false },
  loadingOrderIds: { type: Array, default: () => [] },
})

function formatDistance(route) {
  if (route.total_distance_km !== null && route.total_distance_km !== undefined) {
    return `${route.total_distance_km.toFixed(1)} km`
  }

  if (route.total_distance !== null && route.total_distance !== undefined) {
    return `${(route.total_distance / 1000).toFixed(1)} km`
  }

  return '—'
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

const routeColors = [
  '#2563eb', '#dc2626', '#16a34a', '#d97706',
  '#7c3aed', '#0891b2', '#be185d', '#0d9488',
]
</script>

<style scoped>
.route-list       { display: flex; flex-direction: column; gap: .75rem; }
.route-list__empty{ color: var(--color-muted); font-style: italic; padding: 1rem 0; text-align: center; }

.route-card {
  background: var(--color-surface);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  border-left: 4px solid var(--route-color, var(--color-primary));
  overflow: hidden;
}

.route-card__header {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding: .75rem 1rem;
  background: #f8fafc;
  border-bottom: 1px solid var(--color-border);
  flex-wrap: wrap;
}

.route-card__dot {
  width: 14px; height: 14px;
  border-radius: 50%;
  background: var(--route-color, var(--color-primary));
  flex-shrink: 0;
}

.route-card__title { display: flex; align-items: center; gap: .5rem; flex: 1; }

.route-card__distance {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  min-width: 92px;
  color: #0f172a;
}

.route-card__distance-label {
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: var(--color-muted);
}

.route-card__meta {
  display: flex;
  gap: .75rem;
  font-size: .8rem;
  color: var(--color-muted);
}

@media (max-width: 700px) {
  .route-card__distance {
    align-items: flex-start;
    min-width: auto;
  }
}

/* Paradas */
.stop-list { list-style: none; padding: .5rem 1rem .75rem; display: flex; flex-direction: column; gap: .4rem; }

.stop-item {
  display: flex;
  align-items: flex-start;
  gap: .6rem;
}

.stop-info {
  flex: 1;
}

.stop-action {
  align-self: center;
  white-space: nowrap;
  padding: .4rem .75rem;
}

.stop-seq {
  min-width: 22px; height: 22px;
  background: var(--route-color, var(--color-primary));
  color: #fff;
  border-radius: 50%;
  font-size: .7rem; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-top: .15rem;
}

.stop-address { font-size: .85rem; font-weight: 500; }
.stop-details { font-size: .75rem; color: var(--color-muted); display: flex; gap: .5rem; flex-wrap: wrap; margin-top: .1rem; }
</style>
