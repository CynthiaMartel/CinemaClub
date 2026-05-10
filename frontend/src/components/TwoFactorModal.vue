<script setup>
import { ref, watch } from 'vue'
import api from '@/services/api'
import { useToastStore } from '@/stores/toast'

const toast = useToastStore()

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  twoFactorEnabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue', 'changed'])

// Estado interno
const step = ref('menu')   // 'menu' | 'setup' | 'confirm' | 'disable'
const qrSvg = ref('')
const secret = ref('')
const code = ref('')
const loading = ref(false)
const error = ref('')

watch(() => props.modelValue, (open) => {
  if (open) {
    step.value = 'menu'
    qrSvg.value = ''
    secret.value = ''
    code.value = ''
    error.value = ''
  }
})

const close = () => emit('update:modelValue', false)

const startSetup = async () => {
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.get('/2fa/setup')
    qrSvg.value = data.qr_svg
    secret.value = data.secret
    step.value = 'setup'
  } catch (e) {
    error.value = e?.response?.data?.message || 'Error al generar el QR'
  } finally {
    loading.value = false
  }
}

const confirmSetup = async () => {
  if (code.value.length !== 6) return
  loading.value = true
  error.value = ''
  try {
    await api.post('/2fa/confirm', { code: code.value })
    toast.success('Verificación en dos pasos activada correctamente.')
    emit('changed', true)
    close()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Código incorrecto'
    code.value = ''
  } finally {
    loading.value = false
  }
}

const disableTwoFactor = async () => {
  loading.value = true
  error.value = ''
  try {
    await api.delete('/2fa/disable')
    toast.success('Verificación en dos pasos desactivada.')
    emit('changed', false)
    close()
  } catch (e) {
    error.value = e?.response?.data?.message || 'Error al desactivar'
  } finally {
    loading.value = false
  }
}

const onKeydown = (e) => { if (e.key === 'Escape') close() }
</script>

<template>
  <Transition enter-active-class="transition-opacity duration-200" enter-from-class="opacity-0" enter-to-class="opacity-100"
              leave-active-class="transition-opacity duration-150" leave-from-class="opacity-100" leave-to-class="opacity-0">
    <div v-if="modelValue" class="fixed inset-0 z-[60]" @keydown="onKeydown">
      <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]"></div>
      <div class="relative min-h-full flex items-center justify-center px-4" @click.self="close">
        <div class="w-full max-w-md rounded-2xl border border-white/10 bg-[#1b2228] shadow-2xl overflow-hidden">

          <div class="flex items-center justify-between px-6 py-4 bg-[#14181c] border-b border-white/10">
            <h2 class="text-base font-semibold text-slate-100">Verificación en dos pasos (2FA)</h2>
            <button type="button" class="text-slate-300 hover:text-white" @click="close" aria-label="Cerrar">✕</button>
          </div>

          <div class="px-6 py-6 space-y-5">

            <!-- Menú principal -->
            <template v-if="step === 'menu'">
              <p v-if="twoFactorEnabled" class="text-sm text-green-400 flex items-center gap-2">
                <span>✓</span> El 2FA está activo en tu cuenta.
              </p>
              <p v-else class="text-sm text-slate-400 leading-relaxed">
                Añade una capa extra de seguridad. Necesitarás una app como
                <strong class="text-slate-200">Google Authenticator</strong> o <strong class="text-slate-200">Authy</strong>.
              </p>

              <p class="text-xs text-amber-400/80 leading-relaxed">
                El 2FA es obligatorio para las cuentas con acceso al panel de administración y editorial.
              </p>

              <div class="flex gap-3 pt-2">
                <button v-if="!twoFactorEnabled" type="button"
                  class="px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80 disabled:opacity-60"
                  :disabled="loading" @click="startSetup">
                  {{ loading ? 'Generando…' : 'Activar 2FA' }}
                </button>
                <button v-else type="button"
                  class="px-4 py-2 rounded-lg border border-red-500/40 text-sm font-semibold text-red-400 hover:bg-red-500/10"
                  @click="step = 'disable'">
                  Desactivar 2FA
                </button>
                <button type="button" class="px-4 py-2 rounded-lg border border-white/10 text-sm text-slate-300 hover:bg-white/5" @click="close">
                  Cerrar
                </button>
              </div>
            </template>

            <!-- Paso: escanear QR -->
            <template v-else-if="step === 'setup'">
              <p class="text-sm text-slate-300">Escanea este código QR con tu app de autenticación:</p>
              <div class="flex justify-center bg-white rounded-xl p-3">
                <img :src="'data:image/svg+xml;base64,' + qrSvg" alt="QR 2FA" class="w-44 h-44" />
              </div>
              <p class="text-xs text-slate-500 text-center">
                ¿No puedes escanear? Introduce manualmente: <span class="font-mono text-slate-300 break-all">{{ secret }}</span>
              </p>
              <p class="text-sm text-slate-300">Introduce el código de 6 dígitos que aparece en la app:</p>
              <input v-model.trim="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                placeholder="123456" autocomplete="one-time-code"
                class="w-full rounded-lg bg-[#14181c] border border-white/10 px-3 py-2 text-lg text-slate-100 tracking-widest text-center focus:outline-none focus:ring-2 focus:ring-brand" />
              <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
              <div class="flex gap-3 pt-1">
                <button type="button" class="text-sm text-slate-400 hover:text-slate-200" @click="step = 'menu'">← Volver</button>
                <button type="button"
                  class="ml-auto px-4 py-2 rounded-lg bg-brand text-sm font-semibold text-white hover:bg-brand/80 disabled:opacity-60"
                  :disabled="loading || code.length !== 6" @click="confirmSetup">
                  {{ loading ? 'Verificando…' : 'Confirmar y activar' }}
                </button>
              </div>
            </template>

            <!-- Paso: confirmar desactivación -->
            <template v-else-if="step === 'disable'">
              <p class="text-sm text-slate-300 leading-relaxed">
                ¿Segura que quieres desactivar el 2FA? Tu cuenta quedará protegida solo por contraseña.
              </p>
              <p v-if="error" class="text-sm text-red-400">{{ error }}</p>
              <div class="flex gap-3 pt-2">
                <button type="button" class="text-sm text-slate-400 hover:text-slate-200" @click="step = 'menu'">← Volver</button>
                <button type="button"
                  class="ml-auto px-4 py-2 rounded-lg bg-red-600 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-60"
                  :disabled="loading" @click="disableTwoFactor">
                  {{ loading ? 'Desactivando…' : 'Sí, desactivar' }}
                </button>
              </div>
            </template>

          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>
