<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()

const status  = ref('loading') // loading | success | error
const message = ref('')

onMounted(async () => {
  const token = route.params.token
  if (!token) {
    status.value  = 'error'
    message.value = 'Enlace no válido.'
    return
  }

  try {
    const { data } = await api.get(`/verify-email/${token}`)
    if (data.success === 1) {
      status.value  = 'success'
      message.value = data.message
    } else {
      status.value  = 'error'
      message.value = data.message
    }
  } catch (err) {
    status.value  = 'error'
    message.value = err?.response?.data?.message || 'Error al verificar el email.'
  }
})
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4 bg-[#14181c]">
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#1b2228] shadow-2xl overflow-hidden">

      <div class="px-6 py-4 bg-[#14181c] border-b border-white/10">
        <h1 class="text-base font-semibold text-slate-100">Verificación de email</h1>
      </div>

      <div class="px-6 py-10 text-center space-y-4">

        <div v-if="status === 'loading'" class="text-slate-400 text-sm">
          Verificando tu cuenta…
        </div>

        <div v-else-if="status === 'success'" class="space-y-4">
          <p class="text-slate-200 text-sm">{{ message }}</p>
          <button
            class="px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80"
            @click="router.push('/')"
          >
            Ir al inicio e iniciar sesión
          </button>
        </div>

        <div v-else class="space-y-4">
          <p class="text-red-400 text-sm">{{ message }}</p>
          <button
            class="px-4 py-2 rounded-lg border border-white/10 text-sm text-slate-200 hover:bg-white/5"
            @click="router.push('/')"
          >
            Volver al inicio
          </button>
        </div>

      </div>
    </div>
  </div>
</template>
