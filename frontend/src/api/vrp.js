import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

export default {
  // Pedidos
  getOrders(status = null) {
    return api.get('/orders', { params: status ? { status } : {} })
  },

  createOrder(data) {
    return api.post('/orders', data)
  },

  updateOrderStatus(id, status) {
    return api.patch(`/orders/${id}/status`, { status })
  },

  // Vehículos
  getVehicles() {
    return api.get('/vehicles')
  },

  createVehicle(data) {
    return api.post('/vehicles', data)
  },

  // Rutas
  getRoutes(status = null) {
    return api.get('/routes', { params: status ? { status } : {} })
  },

  clearRoutes() {
    return api.delete('/routes')
  },

  // Optimización
  optimizeRoutes() {
    return api.post('/optimize-routes')
  },
}
