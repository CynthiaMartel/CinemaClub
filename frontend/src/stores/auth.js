// frontend/src/stores/auth.js
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('authToken') || null,
  }),
  getters: {
    isAuthenticated: (state) => !!state.token,
  },
  actions: {
    async login(email, password) {
      const { data } = await api.post('/login', { email, password })

      if (data.success !== 1 || !data.token) {
        throw new Error(data.message || 'Error en login')
      }

      this.token = data.token
      this.user = data.user
      localStorage.setItem('authToken', data.token)
    },

    async checkSession() {
      if (!this.token) return

      try {
        const { data } = await api.get('/check-session')
        if (data.success === 1) {
          this.user = data.user
        }
      } catch (e) {
        // token inválido o expirado
        this.logout()
      }
    },

    logout() {
      this.token = null
      this.user = null
      localStorage.removeItem('authToken')
      // Opcional: hacer logout en backend **************
      api.post('/logout').catch(() => {})
    },
  },
})
