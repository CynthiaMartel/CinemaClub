// frontend/src/services/api.js
import axios from 'axios'

const api = axios.create({
  baseURL: '/api', // gracias al proxy de Vite, esto apunta a cinemaclub.test/api
  // withCredentials: true, // ***más adelante veremos si lo activamos si usamos cookies con Sanctum
})

export default api
