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

    async login(identifier, password, turnstileToken = '') {
      if (!identifier) throw new Error('Debes ingresar tu email o nombre de usuario')
      if (!password) throw new Error('Debes ingresar la contraseña')

      try {
        const { data } = await api.post('/login', { identifier, password, cf_turnstile_response: turnstileToken })

        // success=2 significa que el login fue correcto pero se requiere 2FA
        if (data.requires_2fa) {
          return data
        }

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

    async verifyTwoFactor(tempToken, code) {
      try {
        const { data } = await api.post('/2fa/verify', { temp_token: tempToken, code })
        if (data.success !== 1) throw new Error(data.message || 'Código incorrecto')
        this.user = data.user
        localStorage.setItem('_loggedIn', '1')
        return data
      } catch (err) {
        const msg = err?.response?.data?.message || err?.message || 'Error al verificar el código'
        throw new Error(msg)
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

    async register(name, email, password, passwordConfirmation, turnstileToken = '') {
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
          cf_turnstile_response: turnstileToken,
        })

        if (data.success !== 1) {
          throw new Error(data.message || 'Error en registro nuevo usuario')
        }

        // No hay auto-login: el usuario debe verificar su email primero
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

    async forgotPassword(email) {
      if (!email) throw new Error('Debes ingresar tu email')
      try {
        const { data } = await api.post('/forgot-password', { email })
        return data
      } catch (err) {
        const ex = new Error('UNKNOWN')
        ex.messages = [err?.response?.data?.message || err?.message || 'Error inesperado']
        throw ex
      }
    },

    async resetPassword(token, email, password, passwordConfirmation) {
      if (!password) throw new Error('Debes ingresar la nueva contraseña')
      if (password !== passwordConfirmation) throw new Error('Las contraseñas no coinciden')
      try {
        const { data } = await api.post('/reset-password', {
          token,
          email,
          password,
          password_confirmation: passwordConfirmation,
        })
        if (data.success !== 1) throw new Error(data.message || 'Error al restablecer contraseña')
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

    // Actualiza el avatar en el store sin recargar sesión completa
    // (se llama desde EditProfileModal tras guardar el perfil con éxito)
    setAvatar(avatarUrl) {
      if (this.user) {
        this.user = { ...this.user, avatar: avatarUrl }
      }
    },

    _clearSession() {
      this.user = null
      this._loggedIn = false
      localStorage.removeItem('_loggedIn')
    },
  },
})
