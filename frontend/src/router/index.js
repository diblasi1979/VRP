import { createRouter, createWebHistory } from 'vue-router'
import Dashboard from '../views/Dashboard.vue'
import History from '../views/History.vue'

const routes = [
  {
    path: '/',
    name: 'dashboard',
    component: Dashboard,
  },
  {
    path: '/history',
    name: 'history',
    component: History,
  },
]

export default createRouter({
  history: createWebHistory(),
  routes,
})
