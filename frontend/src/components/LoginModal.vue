<script setup>
import { ref, nextTick, watch, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()
const router = useRouter()
const route = useRoute()

const props = defineProps({
  modelValue: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const auth = useAuthStore()

const identifier = ref('')
const password = ref('')
const loading = ref(false)
const errors = ref([])
const identifierInputRef = ref(null)

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

// Estado del flujo de recuperación de contraseña
const forgotMode = ref(false)
const forgotEmail = ref('')
const forgotLoading = ref(false)
const forgotSent = ref(false)

// Flujo 2FA
const twoFactorMode = ref(false)
const twoFactorTempToken = ref('')
const twoFactorCode = ref('')
const twoFactorLoading = ref(false)

const close = () => {
  emit('update:modelValue', false)
  toast.close()
}

watch(
  () => props.modelValue,
  async (open) => {
    if (open) {
      errors.value = []
      identifier.value = ''
      password.value = ''
      forgotMode.value = false
      forgotEmail.value = ''
      forgotSent.value = false
      twoFactorMode.value = false
      twoFactorTempToken.value = ''
      twoFactorCode.value = ''
      await nextTick()
      identifierInputRef.value?.focus?.()
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
    const result = await auth.login(identifier.value, password.value, turnstileToken.value)
    if (result?.requires_2fa) {
      twoFactorTempToken.value = result.temp_token
      twoFactorMode.value = true
      loading.value = false
      return
    }
    close()
    const redirect = route.query.redirect
    if (redirect && typeof redirect === 'string' && redirect.startsWith('/')) {
      router.push(redirect)
    }
  } catch (e) {
    loading.value = false
    resetTurnstile()
    if (Array.isArray(e?.messages)) {
      errors.value = e.messages
      if (errors.value.length > 1) {
        toast.error(`Hay ${errors.value.length} errores que requieren tu atención`, true)
      } else {
        toast.error(errors.value[0], true)
      }
    } else {
      const msg = e?.message || 'No se pudo iniciar sesión'
      errors.value = [msg]
      toast.error(msg, true)
    }
  }
}

const submitTwoFactor = async () => {
  if (!twoFactorCode.value || twoFactorCode.value.length !== 6) return
  twoFactorLoading.value = true
  errors.value = []
  try {
    await auth.verifyTwoFactor(twoFactorTempToken.value, twoFactorCode.value)
    close()
    const redirect = route.query.redirect
    if (redirect && typeof redirect === 'string' && redirect.startsWith('/')) {
      router.push(redirect)
    }
  } catch (e) {
    errors.value = [e?.message || 'Código incorrecto']
    toast.error(errors.value[0], true)
    twoFactorCode.value = ''
  } finally {
    twoFactorLoading.value = false
  }
}

const openForgot = () => {
  // Si el identificador parece un email lo precargamos, si es usuario lo dejamos vacío
  const val = identifier.value
  forgotEmail.value = val.includes('@') ? val : ''
  forgotMode.value = true
  forgotSent.value = false
  errors.value = []
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
  <div v-if="modelValue" class="fixed inset-0 z-[60]" @keydown="onKeydown">

    <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]"></div>

    <div class="relative min-h-full flex items-center justify-center px-4" @click.self="close">
      <div class="w-full max-w-2xl rounded-2xl border border-white/10 bg-[#1b2228] shadow-2xl overflow-hidden">

        <div class="flex items-center justify-between px-6 py-4 bg-[#14181c] border-b border-white/10">
          <h2 class="text-base md:text-lg font-semibold text-slate-100">
            {{ forgotMode ? 'Recuperar contraseña' : '¡Qué alegría tenerte de vuelta!' }}
          </h2>
          <button class="text-slate-300 hover:text-white" type="button" @click="close" aria-label="Cerrar">
            ✕
          </button>
        </div>

        <!-- Flujo 2FA -->
        <div v-if="twoFactorMode" class="px-6 py-8 space-y-5">
          <p class="text-sm text-slate-300 leading-relaxed">
            Tu cuenta tiene verificación en dos pasos activa.<br>
            Abre tu app de autenticación (Google Authenticator, Authy…) e introduce el código de 6 dígitos.
          </p>
          <form class="space-y-4" @submit.prevent="submitTwoFactor">
            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-200">Código de verificación</label>
              <input
                v-model.trim="twoFactorCode"
                type="text"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                autocomplete="one-time-code"
                placeholder="123456"
                class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand tracking-widest text-center text-lg"
              />
            </div>
            <ul v-if="errors.length" class="text-sm text-red-300 space-y-1">
              <li v-for="(msg, i) in errors" :key="i">{{ msg }}</li>
            </ul>
            <div class="flex items-center justify-between gap-3 pt-1">
              <button type="button" class="text-xs text-slate-400 hover:text-slate-200" @click="twoFactorMode = false">
                ← Volver
              </button>
              <button
                type="submit"
                class="px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80 disabled:opacity-60"
                :disabled="twoFactorLoading || twoFactorCode.length !== 6"
              >
                {{ twoFactorLoading ? 'Verificando…' : 'Verificar' }}
              </button>
            </div>
          </form>
        </div>

        <!-- Flujo de recuperación de contraseña -->
        <div v-if="!twoFactorMode && forgotMode" class="px-6 py-6 space-y-4">
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
              Volver al inicio de sesión
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

        <!-- Formulario de login normal -->
        <form v-if="!twoFactorMode && !forgotMode" class="px-6 py-6 space-y-4" @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-200">Email o usuario</label>
              <input
                ref="identifierInputRef"
                v-model.trim="identifier"
                type="text"
                required
                autocomplete="username"
                class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
              />
            </div>

            <div class="space-y-1">
              <label class="text-sm font-medium text-slate-200">Contraseña</label>
              <input
                v-model="password"
                type="password"
                required
                autocomplete="current-password"
                class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-sm text-slate-100 focus:outline-none focus:ring-2 focus:ring-brand"
              />
            </div>
          </div>

          <ul v-if="errors.length > 1" class="text-sm text-red-300 space-y-1">
            <li v-for="(msg, i) in errors" :key="i">{{ msg }}</li>
          </ul>

          <div ref="turnstileContainer" class="flex justify-center"></div>

          <div class="flex items-center justify-between gap-3 pt-2">
            <button
              type="button"
              class="text-xs text-slate-400 hover:text-slate-200 underline underline-offset-2"
              @click="openForgot"
            >
              ¿Olvidaste tu contraseña?
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
                :disabled="loading || !turnstileToken"
              >
                {{ loading ? 'Conectando…' : 'Login' }}
              </button>
            </div>
          </div>
        </form>

      </div>
    </div>
  </div>
</template>
