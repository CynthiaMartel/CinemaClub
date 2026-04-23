import { ref, computed, onMounted, onBeforeUnmount } from 'vue'

const _isStandalone = () =>
  window.matchMedia('(display-mode: standalone)').matches ||
  ('standalone' in navigator && navigator.standalone === true)

// ─── Singleton persistente en window ────────────────────────────────────────
// Las variables de módulo se resetean con cada hot-reload de Vite (HMR).
// Guardando en window, el evento beforeinstallprompt capturado sobrevive.
// El flag LISTENER_KEY evita registrar el listener más de una vez.
const PROMPT_KEY   = '__filmoclub_installPrompt__'
const LISTENER_KEY = '__filmoclub_promptListenerAdded__'
const PROMPT_EVENT = '__filmoclub_promptChanged__'

if (!window[LISTENER_KEY]) {
  window[LISTENER_KEY] = true

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    window[PROMPT_KEY] = e
    window.dispatchEvent(new CustomEvent(PROMPT_EVENT))
    if (import.meta.env.DEV) console.log('[PWA] beforeinstallprompt capturado ✓')
  })

  window.addEventListener('appinstalled', () => {
    window[PROMPT_KEY] = null
    window.dispatchEvent(new CustomEvent(PROMPT_EVENT))
    if (import.meta.env.DEV) console.log('[PWA] appinstalled ✓')
  })

  if (import.meta.env.DEV) {
    // Si tras 8 segundos no llega el evento, loguear el estado para diagnóstico
    setTimeout(() => {
      if (!window[PROMPT_KEY]) {
        console.warn('[PWA] beforeinstallprompt no recibido tras 8s. Verifica en DevTools → Application → Manifest y Service Workers.')
      }
    }, 8000)
  }
}

const getPrompt = () => window[PROMPT_KEY] ?? null

// ─── Detección de navegador ──────────────────────────────────────────────────
const _getBrowserType = () => {
  const ua = navigator.userAgent
  if (/iPad|iPhone|iPod/.test(ua)) return 'safari-ios'
  if (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1) return 'safari-ios'
  if (ua.includes('Firefox') || ua.includes('FxiOS')) return 'firefox'
  if (ua.includes('Safari') && !ua.includes('Chrome')) return 'safari-mac'
  return 'chromium' // Chrome, Brave, Edge — todos soportan beforeinstallprompt
}

// ─── Composable ─────────────────────────────────────────────────────────────
export function useInstallPrompt() {
  const canInstall   = ref(!!getPrompt())
  const isInstalled  = ref(_isStandalone())
  const browserType  = _getBrowserType()

  const showInstallButton = computed(() => !isInstalled.value)

  const onPromptChanged = () => {
    canInstall.value  = !!getPrompt()
    isInstalled.value = _isStandalone()
  }

  onMounted(() => {
    canInstall.value  = !!getPrompt()
    isInstalled.value = _isStandalone()
    window.addEventListener(PROMPT_EVENT, onPromptChanged)
  })

  onBeforeUnmount(() => {
    window.removeEventListener(PROMPT_EVENT, onPromptChanged)
  })

  const install = async () => {
    const e = getPrompt()
    if (!e) return

    e.prompt()
    const { outcome } = await e.userChoice
    window[PROMPT_KEY] = null
    canInstall.value   = false
    if (outcome === 'accepted') isInstalled.value = true
    window.dispatchEvent(new CustomEvent(PROMPT_EVENT))
  }

  return { canInstall, isInstalled, showInstallButton, install, browserType }
}
