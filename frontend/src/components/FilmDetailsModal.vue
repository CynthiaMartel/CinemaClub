<script setup>
import { computed } from 'vue'


const props = defineProps({
  modelValue: Boolean, // Controla la visibilidad
  film: Object
})

const emit = defineEmits(['update:modelValue', 'openPerson'])

const close = () => emit('update:modelValue', false)

// Lógica interna para el modal
const directors = computed(() => {
  if (!props.film?.cast) return []
  return props.film.cast.filter(p => p.pivot?.role === 'Director')
})

const actors = computed(() => {
  if (!props.film?.cast) return []
  return props.film.cast
    .filter(p => p.pivot?.role === 'Actor')
    .sort((a, b) => (a.pivot?.credit_order ?? 0) - (b.pivot?.credit_order ?? 0))
})

const filmYear = computed(() => {
  if (!props.film?.release_date) return ''
  return new Date(props.film.release_date).getFullYear()
})

const filmDuration = computed(() => {
  if (!props.film?.duration) return null
  const h = Math.floor(props.film.duration / 60)
  const m = props.film.duration % 60
  return h > 0 ? `${h}h ${m}m` : `${m}m`
})

// Obtener la foto del primer director (si existe)
const getImageUrl = (path) => {
  if (!path) return null;
  if (path.startsWith('http')) return path;

  return `https://image.tmdb.org/t/p/w500${path}`;
};

// Obtener la foto del primer director (CORREGIDA)
const directorPhoto = computed(() => {
  if (!directors.value || !directors.value.length) return null;
  
  const path = directors.value[0].profile_path || directors.value[0].photo;
  
  // ¡AQUÍ ESTÁ EL CAMBIO! Pasamos el path por la función transformadora
  return path ? getImageUrl(path) : null;
});
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-sm">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-3xl rounded-3xl shadow-2xl overflow-hidden max-h-[92vh] flex flex-col">
      
      <div class="p-5 border-b border-slate-800 flex justify-between items-center bg-slate-900/50">
        <div>
          <h2 class="text-xl font-black text-white uppercase tracking-tighter">{{ film.title }}</h2>
          <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Archivo de Detalles</p>
        </div>
        <button @click="close" class="bg-slate-800 hover:bg-red-600 text-white p-2 rounded-full transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="p-6 overflow-y-auto space-y-8">
        
        <div class="flex flex-col md:flex-row gap-6 items-center md:items-start text-center md:text-left">
        <div class="relative shrink-0">
            <img 
            v-if="directorPhoto" 
            :src="directorPhoto" 
            class="w-32 h-32 md:w-40 md:h-40 object-cover border border-slate-800 shadow-2xl" 
            />
            <div v-else class="w-32 h-32 md:w-40 md:h-40 bg-slate-800 rounded-full flex items-center justify-center border-2 border-slate-700">
            <span class="text-slate-500 text-xs">Sin foto</span>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4 flex-1 w-full">
            <div class="bg-slate-950/50 p-3 rounded-xl border border-slate-800">
            <span class="block text-[9px] text-slate-500 font-bold uppercase mb-1">Año</span>
            <span class="text-sm text-white font-medium">{{ filmYear }}</span>
            </div>
            <div class="bg-slate-950/50 p-3 rounded-xl border border-slate-800">
            <span class="block text-[9px] text-slate-500 font-bold uppercase mb-1">Duración</span>
            <span class="text-sm text-white font-medium">{{ filmDuration }}</span>
            </div>
            <div class="bg-slate-950/50 p-3 rounded-xl border border-slate-800 col-span-2">
            <span class="block text-[9px] text-slate-500 font-bold uppercase mb-1">Géneros</span>
            <div class="flex flex-wrap gap-2">
                <span v-for="g in (Array.isArray(film.genre) ? film.genre : film.genre.split(','))" :key="g" class="text-[10px] bg-slate-800 px-2 py-0.5 rounded text-slate-300 border border-slate-700">
                {{ g.trim() }}
                </span>
            </div>
            </div>
        </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div class="space-y-6">
            <div>
              <h4 class="text-[10px] font-bold text-yellow-600 uppercase tracking-widest mb-2">Dirección</h4>
              <div class="flex flex-wrap gap-2">
                <span v-for="dir in directors" :key="dir.idPerson" 
                  @click="emit('openPerson', dir.idPerson)" 
                  class="text-slate-200 hover:text-yellow-500 cursor-pointer underline decoration-slate-700 underline-offset-4 transition-colors">
                  {{ dir.name }}
                </span>
              </div>
            </div>

            <div v-if="film.alternative_titles?.length">
              <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-2">Títulos Alternativos</h4>
              <ul class="text-xs text-slate-400 space-y-1">
                <li v-for="t in film.alternative_titles" :key="t" class="italic">{{ t }}</li>
              </ul>
            </div>
          </div>

          <div class="space-y-6">
            <div v-if="film.awards?.length">
              <h4 class="text-[10px] font-bold text-emerald-500 uppercase tracking-widest mb-3">Premios</h4>
              <div v-for="a in film.awards" :key="a" class="text-[11px] text-slate-200 bg-emerald-500/5 border border-emerald-500/10 p-2 rounded-lg mb-1">
                {{ a }}
              </div>
            </div>
            <div v-if="film.nominations?.length">
              <h4 class="text-[10px] font-bold text-amber-500 uppercase tracking-widest mb-3">Nominaciones</h4>
              <div v-for="n in film.nominations" :key="n" class="text-[11px] text-slate-300 bg-slate-800/50 p-2 rounded-lg mb-1">
                {{ n }}
              </div>
            </div>
          </div>
        </div>

        <div class="pt-6 border-t border-slate-800">
          <h4 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest mb-4">Reparto Completo</h4>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div v-for="actor in actors" :key="actor.idPerson" @click="emit('openPerson', actor.idPerson)" class="group cursor-pointer">
              <p class="text-xs text-slate-200 group-hover:text-red-500 transition-colors font-medium">{{ actor.name }}</p>
              <p class="text-[9px] text-slate-500 italic truncate">{{ actor.pivot?.character_name }}</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>