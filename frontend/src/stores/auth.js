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
    // UX frontend
    if (!email) throw new Error('Debes ingresar tu email')
    if (!password) throw new Error('Debes ingresar la contraseña')

    try {
      const { data } = await api.post('/login', { email, password })

      if (data.success !== 1 || !data.token) {
        throw new Error(data.message || 'Error en login')
      }

      this.token = data.token
      this.user = data.user
      localStorage.setItem('authToken', data.token)

      return data
    } catch (err) {
      // Laravel validation 422 (si aplica)
      if (err?.response?.status === 422 && err.response.data?.errors) {
        const ex = new Error('VALIDATION')
        ex.messages = Object.values(err.response.data.errors).flat()
        throw ex
      }

      // 401 / credenciales incorrectas / message custom
      if (err?.response?.data?.message) {
        throw new Error(err.response.data.message)
      }

      throw new Error(err?.message || 'No se pudo iniciar sesión')
    }
  }
  ,

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

    async changePassword(currentPassword, newPassword, confirmPassword) {
      // Validación UX del fronted
      if (!currentPassword) throw new Error('Debes ingresar tu contraseña actual')
      if (!newPassword) throw new Error('Debes ingresar la nueva contraseña')
      if (!confirmPassword) throw new Error('Debes confirmar la nueva contraseña')
      if (newPassword !== confirmPassword) throw new Error('Las contraseñas no coinciden')
      if (currentPassword == newPassword) throw new Error('La nueva contraseña no puede ser la misma que la antigua')

      try {
        const { data } = await api.post('/change-password', {
          current_password: currentPassword,
          new_password: newPassword,
          confirm_password: confirmPassword,
        })
        return data
      }  catch (err) {
      // 401: contraseña actual incorrecta
      if (err?.response?.status === 401) {
        const ex = new Error('UNAUTHORIZED')
        ex.messages = [err.response.data?.message || 'La contraseña actual es incorrecta']
        throw ex
      }

      // 422: validación Laravel (min, regex, required...)
      if (err?.response?.status === 422 && err.response.data?.errors) {
        const ex = new Error('VALIDATION')
        ex.messages = Object.values(err.response.data.errors).flat()
        throw ex
      }

      const ex = new Error('UNKNOWN')
      ex.messages = [err?.message || 'Error inesperado']
      throw ex
    }

    },

    async logout() {
      try {
        await api.post('/logout')
      } catch (e) {
        // si falla, igual limpia el estado
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('authToken')
      }
    },

    async register(name, email, password, passwordConfirmation) {
    // UX
    if (!name) throw new Error('Debes ingresar tu nombre de usuario')
    if (!email) throw new Error('Debes ingresar tu email')
    if (!password) throw new Error('Debes ingresar la contraseña')
    if (!passwordConfirmation) throw new Error('Debes confirmar la contraseña')
    if (password !== passwordConfirmation) throw new Error('Las contraseñas no coinciden')

    try {
      const { data } = await api.post('/register', {
        name,
        email,
        password,
        password_confirmation: passwordConfirmation, 
      })

      if (data.success !== 1 || !data.token) {
        throw new Error(data.message || 'Error en registro nuevo usuario')
      }

      this.token = data.token
      this.user = data.user
      localStorage.setItem('authToken', data.token)

      return data
    } catch (err) {
      if (err?.response?.status === 422 && err.response.data?.errors) {
        const ex = new Error('VALIDATION')
        ex.messages = Object.values(err.response.data.errors).flat()
        throw ex
      }

      const ex = new Error('UNKNOWN')
      ex.messages = [err?.response?.data?.message || err?.message || 'Error inesperado']
      throw ex
    }
  }



  },
})
