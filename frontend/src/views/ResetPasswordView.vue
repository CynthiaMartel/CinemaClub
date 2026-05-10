<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()
const toast  = useToastStore()

const token    = ref(route.query.token || '')
const email    = ref(route.query.email || '')
const password = ref('')
const confirm  = ref('')
const loading  = ref(false)
const done     = ref(false)
const errors   = ref([])

onMounted(() => {
  if (!token.value || !email.value) {
    toast.error('Enlace de restablecimiento no válido', true)
    router.replace('/')
  }
})

const submit = async () => {
  errors.value = []
  loading.value = true
  try {
    await auth.resetPassword(token.value, email.value, password.value, confirm.value)
    done.value = true
  } catch (e) {
    loading.value = false
    if (Array.isArray(e?.messages)) {
      errors.value = e.messages
    } else {
      errors.value = [e?.message || 'Error al restablecer la contraseña']
    }
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4 bg-[#14181c]">
    <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#1b2228] shadow-2xl overflow-hidden">

      <div class="px-6 py-4 bg-[#14181c] border-b border-white/10">
        <h1 class="text-base font-semibold text-slate-100">
          {{ done ? '¡Contraseña restablecida!' : 'Nueva contraseña' }}
        </h1>
      </div>

      <div v-if="done" class="px-6 py-8 text-center space-y-4">
        <p class="text-slate-200 text-sm">
          Tu contraseña ha sido actualizada. Ya puedes iniciar sesión con tu nueva contraseña.
        </p>
        <button
          class="px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80"
          @click="router.push('/')"
        >
          Ir al inicio
        </button>
      </div>

      <form v-else class="px-6 py-6 space-y-4" @submit.prevent="submit">
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-200">Nueva contraseña</label>
          <input
            v-model="password"
            type="password"
            required
            autocomplete="new-password"
            class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
          />
        </div>
        <div class="space-y-1">
          <label class="text-sm font-medium text-slate-200">Confirmar contraseña</label>
          <input
            v-model="confirm"
            type="password"
            required
            autocomplete="new-password"
            class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
          />
        </div>

        <ul v-if="errors.length" class="text-sm text-red-300 space-y-1">
          <li v-for="(msg, i) in errors" :key="i">{{ msg }}</li>
        </ul>

        <div class="flex justify-end pt-2">
          <button
            type="submit"
            class="px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80 disabled:opacity-60"
            :disabled="loading"
          >
            {{ loading ? 'Guardando…' : 'Cambiar contraseña' }}
          </button>
        </div>
      </form>
    </div>
  </div>
</template>
