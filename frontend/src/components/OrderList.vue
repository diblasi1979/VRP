<template>
  <div class="order-list">
    <div class="order-list__header">
      <span>{{ orders.length }} pedido(s)</span>
      <div class="order-list__filters">
        <button
          v-for="f in filters"
          :key="f.value"
          :class="['btn-filter', { active: activeFilter === f.value }]"
          @click="activeFilter = f.value"
        >
          {{ f.label }}
        </button>
      </div>
    </div>

    <div v-if="filtered.length === 0" class="order-list__empty">
      Sin pedidos en este estado.
    </div>

    <ul v-else class="order-items">
      <li v-for="order in filtered" :key="order.id" class="order-item">
        <span class="order-id">#{{ order.id }}</span>
        <div class="order-info">
          <span class="order-address">{{ order.address }}</span>
          <div class="order-meta">
            <span>⚖ {{ order.weight }} kg</span>
            <span>🪟 {{ order.time_window_start }}–{{ order.time_window_end }}</span>
          </div>
        </div>
        <span :class="['badge', `badge-${order.status}`]">{{ order.status }}</span>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  orders: { type: Array, default: () => [] },
})

const filters = [
  { label: 'Todos',     value: 'all'       },
  { label: 'Pendientes', value: 'pending'  },
  { label: 'Asignados', value: 'assigned'  },
  { label: 'Entregados', value: 'delivered'},
]

const activeFilter = ref('all')

const filtered = computed(() =>
  activeFilter.value === 'all'
    ? props.orders
    : props.orders.filter((o) => o.status === activeFilter.value)
)
</script>

<style scoped>
.order-list { display: flex; flex-direction: column; gap: .5rem; }

.order-list__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: .4rem;
  border-bottom: 1px solid var(--color-border);
  font-size: .85rem;
  color: var(--color-muted);
  flex-wrap: wrap;
  gap: .5rem;
}

.order-list__filters { display: flex; gap: .25rem; }

.btn-filter {
  padding: .2rem .6rem;
  border-radius: 999px;
  font-size: .75rem;
  font-weight: 600;
  background: var(--color-border);
  color: var(--color-text);
  border: 2px solid transparent;
}
.btn-filter.active {
  background: var(--color-primary);
  color: #fff;
}

.order-list__empty { color: var(--color-muted); font-style: italic; text-align: center; padding: .75rem 0; }

.order-items { list-style: none; display: flex; flex-direction: column; gap: .4rem; max-height: 38vh; overflow-y: auto; padding-right: .25rem; }

.order-item {
  display: flex;
  align-items: center;
  gap: .6rem;
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius);
  padding: .5rem .75rem;
}

.order-id {
  min-width: 30px;
  font-weight: 700;
  font-size: .8rem;
  color: var(--color-primary);
}

.order-info { flex: 1; }
.order-address { font-size: .85rem; font-weight: 500; display: block; }
.order-meta { font-size: .75rem; color: var(--color-muted); display: flex; gap: .5rem; margin-top: .1rem; }
</style>
