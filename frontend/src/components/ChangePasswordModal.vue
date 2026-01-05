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

const current_password = ref('')
const new_password = ref('')
const confirm_password = ref('')

const loading = ref(false)
const errors = ref([]) // Array para mostrar los errores escritos en código del request de ChangePassword del backend

const current_passwordInputRef = ref(null)
const new_passwordInputRef = ref(null)
const confirm_passwordInputRef = ref(null)

const close = () => emit('update:modelValue', false)

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      errors.value = []
      current_password.value = ''
      new_password.value = ''
      confirm_password.value = ''
      await nextTick()
      
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
    await auth.changePassword(current_password.value,
      new_password.value,
      confirm_password.value)
    close()
  } catch (e) {
    loading.value = false 

    if (Array.isArray(e?.messages)) {
      errors.value = e.messages
      await nextTick() 
      
      const firstError = errors.value[0].toLowerCase()

      if (firstError.includes('actual')) {
        current_passwordInputRef.value?.focus()

      } else if (firstError.includes('nueva') ) {
        new_passwordInputRef.value?.focus()
      }
      else if (firstError.includes('confirm') ) {
        confirm_passwordInputRef.value?.focus()
      }

      if (errors.value.length > 1) {
        toast.error(`Hay ${errors.value.length} errores que requieren tu atención`, true)
      } else {
        toast.error(errors.value[0], true)
      }
      
    } else {
      const msg = e?.message || 'No se pudo realizar cambio de contraseña'
      errors.value = [msg]
      toast.error(msg, true)
    }
  }
}

const onKeydown = (e) => {
  if (e.key === 'Escape') close()
}
</script>

<template>
  <!-- Overlay fade -->
  <Transition
    enter-active-class="transition-opacity duration-200 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-150 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="modelValue"
      class="fixed inset-0 z-[60]"
      @keydown="onKeydown"
    >
      <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]" @click="close"></div>

      <!-- Dialog pop -->
      <div class="relative min-h-full flex items-center justify-center px-4" @click.self="close">
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 translate-y-2 scale-95"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          leave-active-class="transition duration-150 ease-in"
          leave-from-class="opacity-100 translate-y-0 scale-100"
          leave-to-class="opacity-0 translate-y-2 scale-95"
        >
          <div
            v-if="modelValue"
            class="w-full max-w-2xl rounded-2xl border border-slate-800 bg-slate-900 shadow-2xl overflow-hidden"
          >
            <div class="flex items-center justify-between px-6 py-4 bg-slate-950 border-b border-slate-800">
              <h2 class="text-base md:text-lg font-semibold text-slate-100">
                Cambio de contraseña
              </h2>
              <button class="text-slate-300 hover:text-white" type="button" @click="close" aria-label="Cerrar">
                ✕
              </button>
            </div>

            <form class="px-6 py-6 space-y-4" @submit.prevent="submit">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Contraseña Actual</label>
                  <input
                    v-model="current_password"
                    ref="current_passwordInputRef"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Nueva Contraseña</label>
                  <input
                    v-model="new_password"
                    ref="new_passwordInputRef"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Confirma nueva contraseña</label>
                  <input
                    v-model="confirm_password"
                    ref="confirm_passwordInputRef"
                    type="password"
                    required
                    autocomplete="confirm-password"
                    class="w-full rounded-lg bg-slate-950 border border-slate-700 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
              </div>

              <ul v-if="errors.length" class="text-sm text-red-300 space-y-1">
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
                  {{ loading ? 'Conectando…' : 'Cambiar contraseña' }}
                </button>
              </div>
            </form>
          </div>
        </Transition>
      </div>
    </div>
  </Transition>
</template>
