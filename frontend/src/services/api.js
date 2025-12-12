// frontend/src/services/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: '/api', // gracias al proxy de Vite, esto apunta a cinemaclub.test/api
  // withCredentials: true, // ***más adelante veremos si lo activamos si usamos cookies con Sanctum
})

// Interceptor para añadir el token de Sanctum si existe
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('authToken')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

export default api
