import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router'
import './assets/main.css'
import { useAuthStore } from '@/stores/auth'
import { registerSW } from 'virtual:pwa-register'

registerSW({ onNeedRefresh() { window.location.reload() } })

const pinia = createPinia()
const app = createApp(App)
app.use(pinia).use(router)

// Restaurar sesión antes de montar para que auth.isAuthenticated
// ya esté resuelto cuando cualquier vista llame a su onMounted
const auth = useAuthStore()
auth.checkSession().finally(() => {
  app.mount('#app')
})

