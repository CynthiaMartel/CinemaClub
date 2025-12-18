import { defineStore } from 'pinia'

export const useToastStore = defineStore('toast', {
  state: () => ({
    show: false,
    message: '',
    timeoutId: null,
  }),
  actions: {
    error(message, ms = 3000) {
      this.message = message
      this.show = true

      if (this.timeoutId) clearTimeout(this.timeoutId)
      this.timeoutId = setTimeout(() => {
        this.show = false
        this.timeoutId = null
      }, ms)
    },
    close() {
      this.show = false
      if (this.timeoutId) clearTimeout(this.timeoutId)
      this.timeoutId = null
    }
  }
})
