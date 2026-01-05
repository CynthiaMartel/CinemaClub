import { defineStore } from 'pinia'

export const useToastStore = defineStore('toast', {
  state: () => ({
    show: false,
    message: '',
    timeoutId: null,
    isModal: false,
  }),
  actions: {
    
    error(message, isModal = false) {
      this.message = message
      this.show = true
      this.isModal = isModal

      if (this.timeoutId) clearTimeout(this.timeoutId)

      // Si no es modal, se cierra a los 3 segundos
      if (!isModal) {
        this.timeoutId = setTimeout(() => {
          this.close()
        }, 3000)
      }
    },
    close() {
      this.show = false
      if (this.timeoutId) clearTimeout(this.timeoutId)
      this.timeoutId = null
    }
  }
})
