<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const email = ref('')
const password = ref('')
const cargando = ref(false)
const error = ref(null)

const router = useRouter()
const auth = useAuthStore()

const submit = async () => {
  cargando.value = true
  error.value = null

  try {
    await auth.login(email.value, password.value)
    // Opcional: comprobar sesión más detallada
    await auth.checkSession()
    // Redirigir a home
    router.push({ name: 'home' })
  } catch (e) {
    console.error(e)
    error.value = e.message || 'No se pudo iniciar sesión'
  } finally {
    cargando.value = false
  }
}
</script>

<template>
  <div class="flex items-center justify-center min-h-[calc(100vh-4rem)] px-4">
    <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
      <h1 class="text-xl font-semibold mb-4 text-center">Iniciar sesión</h1>

      <form class="space-y-4" @submit.prevent="submit">
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-200">Email</label>
          <input
            v-model="email"
            type="email"
            required
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-red-500"
          />
        </div>

        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-200">Contraseña</label>
          <input
            v-model="password"
            type="password"
            required
            class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-red-500"
          />
        </div>

        <p v-if="error" class="text-sm text-red-400">
          {{ error }}
        </p>

        <button
          type="submit"
          class="w-full mt-2 px-4 py-2 rounded-lg bg-red-600 text-slate-100 font-semibold hover:bg-red-500 disabled:opacity-60"
          :disabled="cargando"
        >
          {{ cargando ? 'Conectando…' : 'Entrar' }}
        </button>
      </form>
    </div>
  </div>
</template>
