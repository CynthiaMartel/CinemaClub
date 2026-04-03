import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    // El token ya NO se almacena aquí — viaja como cookie HttpOnly
    // que el navegador gestiona automáticamente y el JS no puede leer.
    // Usamos un flag ligero en localStorage solo para saber si llamar
    // a checkSession al cargar la app (evita un request innecesario a usuarios no logueados)
    _loggedIn: localStorage.getItem('_loggedIn') === '1',
  }),

  getters: {
    isAuthenticated: (state) => !!state.user,
  },

  actions: {

    async login(email, password) {
      if (!email) throw new Error('Debes ingresar tu email')
      if (!password) throw new Error('Debes ingresar la contraseña')

      try {
        const { data } = await api.post('/login', { email, password })

        if (data.success !== 1) {
          throw new Error(data.message || 'Error en login')
        }

        this.user = data.user
        localStorage.setItem('_loggedIn', '1')

        return data
      } catch (err) {
        if (err?.response?.status === 422 && err.response.data?.errors) {
          const ex = new Error('VALIDATION')
          ex.messages = Object.values(err.response.data.errors).flat()
          throw ex
        }
        if (err?.response?.data?.message) {
          throw new Error(err.response.data.message)
        }
        throw new Error(err?.message || 'No se pudo iniciar sesión')
      }
    },

    async checkSession() {
      // Si el flag indica que nunca hubo login, no hacemos el request
      if (!this._loggedIn) return

      try {
        const { data } = await api.get('/check-session')
        if (data.success === 1) {
          this.user = data.user
        }
      } catch {
        // Cookie expirada o token revocado — limpiamos estado
        this._clearSession()
      }
    },

    async logout() {
      try {
        await api.post('/logout')
      } catch {
        // Si falla el request, limpiamos igualmente
      } finally {
        this._clearSession()
      }
    },

    async register(name, email, password, passwordConfirmation) {
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

        if (data.success !== 1) {
          throw new Error(data.message || 'Error en registro nuevo usuario')
        }

        this.user = data.user
        localStorage.setItem('_loggedIn', '1')

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
    },

    async changePassword(currentPassword, newPassword, confirmPassword) {
      if (!currentPassword) throw new Error('Debes ingresar tu contraseña actual')
      if (!newPassword) throw new Error('Debes ingresar la nueva contraseña')
      if (!confirmPassword) throw new Error('Debes confirmar la nueva contraseña')
      if (newPassword !== confirmPassword) throw new Error('Las contraseñas no coinciden')
      if (currentPassword === newPassword) throw new Error('La nueva contraseña no puede ser la misma que la antigua')

      try {
        const { data } = await api.post('/change-password', {
          current_password: currentPassword,
          new_password: newPassword,
          confirm_password: confirmPassword,
        })
        return data
      } catch (err) {
        if (err?.response?.status === 401) {
          const ex = new Error('UNAUTHORIZED')
          ex.messages = [err.response.data?.message || 'La contraseña actual es incorrecta']
          throw ex
        }
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

    _clearSession() {
      this.user = null
      this._loggedIn = false
      localStorage.removeItem('_loggedIn')
    },
  },
})
