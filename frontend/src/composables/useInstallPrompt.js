import { ref, onMounted, onBeforeUnmount } from 'vue'

export function useInstallPrompt() {
  const deferredPrompt = ref(null)
  const canInstall = ref(false)
  const isInstalled = ref(false)

  const handleBeforeInstallPrompt = (e) => {
    e.preventDefault()
    deferredPrompt.value = e
    canInstall.value = true
  }

  const handleAppInstalled = () => {
    deferredPrompt.value = null
    canInstall.value = false
    isInstalled.value = true
  }

  const install = async () => {
    if (!deferredPrompt.value) return
    deferredPrompt.value.prompt()
    const { outcome } = await deferredPrompt.value.userChoice
    if (outcome === 'accepted') {
      deferredPrompt.value = null
      canInstall.value = false
    }
  }

  onMounted(() => {
    // Ya instalada si corre en modo standalone (home screen)
    if (window.matchMedia('(display-mode: standalone)').matches) {
      isInstalled.value = true
    }
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
    window.addEventListener('appinstalled', handleAppInstalled)
  })

  onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
    window.removeEventListener('appinstalled', handleAppInstalled)
  })

  return { canInstall, isInstalled, install }
}
