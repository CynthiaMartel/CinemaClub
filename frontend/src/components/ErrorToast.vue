<script setup>
import { useToastStore } from '@/stores/toast'
const toast = useToastStore()
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="opacity-0 translate-y-10"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div 
      v-if="toast.show" 
      :class="[
        'fixed z-[100] px-4 transition-all duration-300',
        // ESTILO PARA MODAL
        toast.isModal 
          ? 'top-[80%] left-1/2 -translate-x-1/2 w-full max-w-2xl' 
          : 'bottom-6 right-6 w-[90vw] max-w-sm'
      ]"
    >
      <div class="rounded-xl border border-red-500/50 bg-slate-950 px-4 py-3 shadow-2xl">
        <div class="flex items-center gap-3">
          <p class="text-sm font-medium text-red-200 leading-snug">
            {{ toast.message }}
          </p>
          <button class="ml-auto text-slate-400 hover:text-white" @click="toast.close">✕</button>
        </div>
      </div>
    </div>
  </Transition>
</template>
