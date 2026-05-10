<script setup>
import { ref, nextTick, watch, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const auth = useAuthStore()

const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')

const loading = ref(false)
const errors = ref([])
const registered = ref(false)

const nameInputRef = ref(null)
const emailInputRef = ref(null)
const passwordInputRef = ref(null)

const turnstileContainer = ref(null)
const turnstileToken = ref('')
let turnstileWidgetId = null

const renderTurnstile = () => {
  if (!window.turnstile || !turnstileContainer.value) return
  turnstileWidgetId = window.turnstile.render(turnstileContainer.value, {
    sitekey: import.meta.env.VITE_TURNSTILE_SITE_KEY ?? '1x00000000000000000000AA',
    callback: (token) => { turnstileToken.value = token },
    'expired-callback': () => { turnstileToken.value = '' },
    theme: 'dark',
  })
}

const resetTurnstile = () => {
  if (turnstileWidgetId !== null && window.turnstile) {
    window.turnstile.reset(turnstileWidgetId)
  }
  turnstileToken.value = ''
}

onUnmounted(() => {
  if (turnstileWidgetId !== null && window.turnstile) {
    window.turnstile.remove(turnstileWidgetId)
  }
})

const close = () => {
  emit('update:modelValue', false)
  registered.value = false
}

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      errors.value = []
      registered.value = false
      name.value = ''
      email.value = ''
      password.value = ''
      password_confirmation.value = ''
      await nextTick()
      nameInputRef.value?.focus?.()
      renderTurnstile()
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
    await auth.register(name.value, email.value, password.value, password_confirmation.value, turnstileToken.value)
    registered.value = true
  } catch (e) {
    loading.value = false
    resetTurnstile()

    if (Array.isArray(e?.messages)) {
      errors.value = e.messages
      await nextTick()

      const firstError = errors.value[0].toLowerCase()
      if (firstError.includes('email')) {
        emailInputRef.value?.focus()
      } else if (firstError.includes('contraseña')) {
        passwordInputRef.value?.focus()
      }

      if (errors.value.length > 1) {
        toast.error(`Hay ${errors.value.length} errores que requieren tu atención`, true)
      } else {
        toast.error(errors.value[0], true)
      }
    } else {
      const msg = e?.message || 'No se pudo realizar el registro de nuevo usuario'
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
      <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]"></div>

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
                {{ registered ? '¡Cuenta creada!' : '¡Únete a la comunidad!' }}
              </h2>
              <button class="text-slate-300 hover:text-white" type="button" @click="close" aria-label="Cerrar">
                ✕
              </button>
            </div>

            <!-- Estado post-registro: pedir verificación de email -->
            <div v-if="registered" class="px-6 py-8 text-center space-y-4">
              <p class="text-slate-200 text-sm leading-relaxed">
                Te hemos enviado un email a <strong class="text-white">{{ email }}</strong>.<br>
                Haz clic en el enlace de verificación para activar tu cuenta.
              </p>
              <p class="text-slate-400 text-xs">
                ¿No lo ves? Revisa la carpeta de spam.
              </p>
              <button
                type="button"
                class="mt-4 px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80"
                @click="close"
              >
                Entendido
              </button>
            </div>

            <!-- Formulario de registro -->
            <form v-else class="px-6 py-6 space-y-4" @submit.prevent="submit">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Nombre de usuario/usuaria</label>
                  <input
                    ref="nameInputRef"
                    v-model="name"
                    type="text"
                    required
                    autocomplete="username"
                    class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Email</label>
                  <input
                    ref="emailInputRef"
                    v-model.trim="email"
                    type="email"
                    required
                    autocomplete="email"
                    class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Contraseña</label>
                  <input
                    ref="passwordInputRef"
                    v-model="password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
                <div class="space-y-1">
                  <label class="text-sm font-medium text-slate-200">Confirma contraseña</label>
                  <input
                    v-model="password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
                  />
                </div>
              </div>

              <ul v-if="errors.length > 1" class="text-sm text-red-300 space-y-1">
                <li v-for="(msg, i) in errors" :key="i">{{ msg }}</li>
              </ul>

              <div ref="turnstileContainer" class="flex justify-center"></div>

              <div class="flex items-center justify-end gap-3 pt-2">
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
                  :disabled="loading || !turnstileToken"
                >
                  {{ loading ? 'Creando cuenta…' : 'Crear cuenta' }}
                </button>
              </div>
            </form>
          </div>
        </Transition>
      </div>
    </div>
  </Transition>
</template>
