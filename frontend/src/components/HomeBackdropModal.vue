
<script setup>
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import FilmSearch from '@/components/FilmSearch.vue'

// Props para controlar la visibilidad
const props = defineProps({
  modelValue: Boolean
})

const emit = defineEmits(['update:modelValue', 'change-backdrop'])

const close = () => emit('update:modelValue', false)

//  Seleccionamos película en el componente hijo
const handleFilmSelect = (film) => {
  // Emitimos al padre la película elegida
  emit('change-backdrop', film)
  close()
}

// Cerrar con ESC
const onKeydown = (e) => {
  if (e.key === 'Escape') close()
}

onMounted(() => document.addEventListener('keydown', onKeydown))
onUnmounted(() => document.removeEventListener('keydown', onKeydown))
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
    
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close"></div>

    <div class="relative w-full max-w-lg bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden animate-fade-in-up">
        
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-slate-950">
          <h2 class="text-sm font-bold text-slate-100 uppercase tracking-widest">
             Cambiar Fondo de Pantalla
          </h2>
          <button class="text-slate-400 hover:text-white transition-colors" @click="close">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>

        <div class="p-6 flex flex-col gap-4 min-h-[300px]">
            <p class="text-xs text-slate-400">Busca una película para establecerla como fondo principal.</p>
            
            <div class="w-full">
                <FilmSearch @select-film="handleFilmSelect" />
            </div>
        </div>

        <div class="bg-slate-950/50 px-6 py-3 flex justify-end border-t border-slate-800">
            <button @click="close" class="text-xs font-bold text-slate-400 hover:text-white uppercase tracking-widest">
                Cancelar
            </button>
        </div>

    </div>
  </div>
</template>

<style scoped>
.animate-fade-in-up {
  animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes fadeInUp {
  from { opacity: 0; transform: scale(0.95) translateY(10px); }
  to { opacity: 1; transform: scale(1) translateY(0); }
}
</style>