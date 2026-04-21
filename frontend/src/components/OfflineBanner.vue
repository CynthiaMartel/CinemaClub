<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'

const isOffline = ref(!navigator.onLine)

const handleOnline  = () => { isOffline.value = false }
const handleOffline = () => { isOffline.value = true }

onMounted(() => {
  window.addEventListener('online',  handleOnline)
  window.addEventListener('offline', handleOffline)
})

onBeforeUnmount(() => {
  window.removeEventListener('online',  handleOnline)
  window.removeEventListener('offline', handleOffline)
})
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 -translate-y-2"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 -translate-y-2"
  >
    <div
      v-if="isOffline"
      class="fixed top-16 left-0 right-0 z-50 flex items-center justify-center gap-2 px-4 py-2
             bg-amber-950/90 backdrop-blur-sm border-b border-amber-800/50 text-amber-300 text-xs font-medium"
      role="status"
      aria-live="polite"
    >
      <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M6.343 6.343a9 9 0 000 12.728M8.464 8.464a5 5 0 000 7.072M12 12h.01" />
      </svg>
      Sin conexión — mostrando contenido guardado
    </div>
  </Transition>
</template>
