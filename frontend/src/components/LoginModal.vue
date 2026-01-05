<script setup>
import { ref, nextTick, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const auth = useAuthStore()

const email = ref('')
const password = ref('')
const loading = ref(false)
const errors = ref([])
const emailInputRef = ref(null)

const close = () => 
emit('update:modelValue', false)
toast.close()

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      errors.value = []
      email.value = ''
      password.value = ''
      await nextTick()
      emailInputRef.value?.focus?.()
    }else {
      toast.close() 
    }
  }
)

const submit = async () => {
  toast.close()
  errors.value = []        
  loading.value = true

  try {
    await auth.login(email.value, password.value)
    close()
  } catch (e) {
  if (Array.isArray(e?.messages)) {
    errors.value = e.messages
    loading.value = false
  
    if (errors.value.length > 1) {
      // Si hay más de un error, ponemos un resumen
      toast.error(`Hay ${errors.value.length} errores que requieren tu atención`, true)
      
    } else {
      // Si solo hay uno, mostramos ese único error
      toast.error(errors.value[0], true)
      
    }
    
  } else {
    const msg = e?.message || 'No se pudo iniciar sesión'
    errors.value = [msg]
    toast.error(msg, true)
    loading.value = false
  }
}
}

const onKeydown = (e) => {
  if (e.key === 'Escape') close()
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-[60]" @keydown="onKeydown">
    
    <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]"></div>

   
    <div class="relative min-h-full flex items-center justify-center px-4" @click.self="close">
      <div class="w-full max-w-2xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 bg-slate-950 border-b border-slate-800">
          <h2 class="text-base md:text-lg font-semibold text-slate-100">
            ¡Qué alegría tenerte de vuelta!
          </h2>
          <button class="text-slate-300 hover:text-white" type="button" @click="close" aria-label="Cerrar">
            ✕
          </button>
        </div>

        <form class="px-6 py-6 space-y-4" @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-200">Email</label>
              <input
                ref="emailInputRef"
                v-model.trim="email"
                type="email"
                required
                autocomplete="email"
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
              />
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-200">Contraseña</label>
              <input
                v-model="password"
                type="password"
                required
                autocomplete="current-password"
                class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
              />
            </div>
          </div>

          <ul v-if="errors.length > 1" class="text-sm text-red-300 space-y-1">
                <li v-for="(msg, i) in errors" :key="i">{{ msg }}</li>
          </ul>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="button"
              class="px-4 py-2 rounded-lg border border-slate-700 text-sm font-semibold text-slate-200 hover:bg-slate-800"
              @click="close"
            >
              Cancelar
            </button>

            <button
              type="submit"
              class="px-4 py-2 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-900 hover:bg-emerald-400 disabled:opacity-60"
              :disabled="loading"
            >
              {{ loading ? 'Conectando…' : 'Login' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
