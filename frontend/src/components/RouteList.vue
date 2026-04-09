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
          <span class="badge badge-optimized">{{ route.status }}</span>
        </div>
        <div class="route-card__meta">
          <span v-if="route.total_distance">📍 {{ (route.total_distance / 1000).toFixed(1) }} km</span>
          <span v-if="route.total_duration">⏱ {{ Math.round(route.total_duration / 60) }} min</span>
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
              <span v-if="stop.estimated_arrival">🕐 {{ stop.estimated_arrival }}</span>
              <span v-if="stop.order">⚖ {{ stop.order.weight }} kg</span>
              <span v-if="stop.order">
                🪟 {{ stop.order.time_window_start }}–{{ stop.order.time_window_end }}
              </span>
            </div>
          </div>
        </li>
      </ol>
    </div>
  </div>
</template>

<script setup>
defineProps({
  routes: { type: Array, default: () => [] },
})

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

.route-card__meta {
  display: flex;
  gap: .75rem;
  font-size: .8rem;
  color: var(--color-muted);
}

/* Paradas */
.stop-list { list-style: none; padding: .5rem 1rem .75rem; display: flex; flex-direction: column; gap: .4rem; }

.stop-item {
  display: flex;
  align-items: flex-start;
  gap: .6rem;
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
