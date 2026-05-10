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
const new_password     = ref('')
const confirm_password = ref('')
const loading          = ref(false)
const errors           = ref([])

const current_passwordInputRef = ref(null)
const new_passwordInputRef     = ref(null)
const confirm_passwordInputRef = ref(null)

// Flujo de recuperación de contraseña
const forgotMode    = ref(false)
const forgotEmail   = ref('')
const forgotLoading = ref(false)
const forgotSent    = ref(false)

const close = () => {
  emit('update:modelValue', false)
  toast.close()
}

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      errors.value          = []
      current_password.value = ''
      new_password.value    = ''
      confirm_password.value = ''
      forgotMode.value      = false
      forgotEmail.value     = ''
      forgotSent.value      = false
      await nextTick()
      current_passwordInputRef.value?.focus?.()
    } else {
      toast.close()
    }
  }
)

const submit = async () => {
  toast.close()
  errors.value = []
  loading.value = true

  try {
    await auth.changePassword(current_password.value, new_password.value, confirm_password.value)
    close()
  } catch (e) {
    loading.value = false

    if (Array.isArray(e?.messages)) {
      errors.value = e.messages
      await nextTick()

      const firstError = errors.value[0].toLowerCase()
      if (firstError.includes('actual')) {
        current_passwordInputRef.value?.focus()
      } else if (firstError.includes('nueva')) {
        new_passwordInputRef.value?.focus()
      } else if (firstError.includes('confirm')) {
        confirm_passwordInputRef.value?.focus()
      }

      if (errors.value.length > 1) {
        toast.error(`Hay ${errors.value.length} errores que requieren tu atención`, true)
      } else {
        toast.error(errors.value[0], true)
      }
    } else {
      const msg = e?.message || 'No se pudo realizar el cambio de contraseña'
      errors.value = [msg]
      toast.error(msg, true)
    }
  }
}

const openForgot = () => {
  forgotEmail.value = auth.user?.email || ''
  forgotMode.value  = true
  forgotSent.value  = false
  errors.value      = []
  toast.close()
}

const submitForgot = async () => {
  if (!forgotEmail.value) {
    toast.error('Introduce tu email para continuar', true)
    return
  }
  forgotLoading.value = true
  try {
    await auth.forgotPassword(forgotEmail.value)
    forgotSent.value = true
  } catch (e) {
    toast.error(e?.messages?.[0] || 'Error al enviar el enlace', true)
  } finally {
    forgotLoading.value = false
  }
}

const onKeydown = (e) => {
  if (e.key === 'Escape') close()
}
</script>

<template>
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
            class="w-full max-w-2xl rounded-2xl border border-white/10 bg-[#1b2228] shadow-2xl overflow-hidden"
          >
            <div class="flex items-center justify-between px-6 py-4 bg-[#14181c] border-b border-white/10">
              <h2 class="text-base md:text-lg font-semibold text-slate-100">
                {{ forgotMode ? 'Recuperar contraseña' : 'Cambio de contraseña' }}
              </h2>
              <button class="text-slate-300 hover:text-white" type="button" @click="close" aria-label="Cerrar">
                ✕
              </button>
            </div>

            <!-- Flujo de recuperación -->
            <div v-if="forgotMode" class="px-6 py-6 space-y-4">
              <div v-if="forgotSent" class="text-center space-y-3 py-4">
                <p class="text-slate-200 text-sm leading-relaxed">
                  Si existe una cuenta con <strong class="text-white">{{ forgotEmail }}</strong>,
                  recibirás un enlace para restablecer tu contraseña en breve.
                </p>
                <p class="text-slate-400 text-xs">Revisa también la carpeta de spam.</p>
                <button
                  type="button"
                  class="mt-2 text-sm text-brand hover:underline"
                  @click="forgotMode = false"
                >
                  Volver al cambio de contraseña
                </button>
              </div>

              <form v-else class="space-y-4" @submit.prevent="submitForgot">
                <p class="text-sm text-slate-400">
                  Escribe tu email y te enviaremos un enlace para restablecer tu contraseña.
                </p>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Email</label>
                  <input
                    v-model.trim="forgotEmail"
                    type="email"
                    required
                    autocomplete="email"
                    class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div class="flex items-center justify-between gap-3 pt-2">
                  <button
                    type="button"
                    class="text-sm text-slate-400 hover:text-slate-200"
                    @click="forgotMode = false"
                  >
                    Volver
                  </button>
                  <button
                    type="submit"
                    class="px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80 disabled:opacity-60"
                    :disabled="forgotLoading"
                  >
                    {{ forgotLoading ? 'Enviando…' : 'Enviar enlace' }}
                  </button>
                </div>
              </form>
            </div>

            <!-- Formulario de cambio de contraseña -->
            <form v-else class="px-6 py-6 space-y-4" @submit.prevent="submit">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Contraseña actual</label>
                  <input
                    ref="current_passwordInputRef"
                    v-model="current_password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Nueva contraseña</label>
                  <input
                    ref="new_passwordInputRef"
                    v-model="new_password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Confirma nueva contraseña</label>
                  <input
                    ref="confirm_passwordInputRef"
                    v-model="confirm_password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
              </div>

              <ul v-if="errors.length" class="text-sm text-red-300 space-y-1">
                <li v-for="(msg, i) in errors" :key="i">{{ msg }}</li>
              </ul>

              <div class="flex items-center justify-between gap-3 pt-2">
                <button
                  type="button"
                  class="text-xs text-slate-400 hover:text-slate-200 underline underline-offset-2"
                  @click="openForgot"
                >
                  ¿Has olvidado tu contraseña?
                </button>

                <div class="flex gap-3">
                  <button
                    type="button"
                    class="px-4 py-2 rounded-lg border border-white/10 text-sm font-semibold text-slate-200 hover:bg-white/5"
                    @click="close"
                  >
                    Cancelar
                  </button>
                  <button
                    type="submit"
                    class="px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80 disabled:opacity-60"
                    :disabled="loading"
                  >
                    {{ loading ? 'Guardando…' : 'Cambiar contraseña' }}
                  </button>
                </div>
              </div>
            </form>

          </div>
        </Transition>
      </div>
    </div>
  </Transition>
</template>
