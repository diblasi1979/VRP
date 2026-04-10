<template>
  <div class="app-shell">
    <header class="top-bar">
      <div class="top-bar__brand">🚚 VRP TMS — Optimización de Rutas</div>
      <nav class="top-bar__nav">
        <router-link to="/">Dashboard</router-link>
        <router-link to="/history">Historial</router-link>
      </nav>
    </header>
    <router-view />
  </div>
</template>

<style scoped>
.app-shell   { display: flex; flex-direction: column; min-height: 100vh; }

.top-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--color-primary);
  color: #fff;
  padding: .75rem 1.5rem;
  box-shadow: var(--shadow);
}

.top-bar__brand { font-weight: 700; font-size: 1.1rem; letter-spacing: .02em; }
.top-bar__nav { display: flex; gap: .85rem; }
.top-bar__nav a { color: rgba(255,255,255,.85); font-size: .9rem; }
.top-bar__nav a:hover { color: #fff; }
.top-bar__nav .router-link-active { color: #fff; font-weight: 700; }
</style>
