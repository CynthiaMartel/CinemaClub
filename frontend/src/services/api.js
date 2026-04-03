import axios from 'axios'

const api = axios.create({
  baseURL: '/api',
  withCredentials: true, // Envía la cookie auth_token automáticamente en cada petición
})

export default api
