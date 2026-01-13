<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: Boolean, 
  film: Object
})

const emit = defineEmits(['update:modelValue', 'openPerson'])

const close = () => emit('update:modelValue', false)

// para obtener imágenes
const getImageUrl = (path, size = 'w500') => {
  if (!path) return null;
  if (path.startsWith('http')) return path;
  return `https://image.tmdb.org/t/p/${size}${path}`;
};

// ---FORMATEO ---

const formattedReleaseDate = computed(() => {
  if (!props.film?.release_date) return 'N/A'
  const date = new Date(props.film.release_date)
  return date.toISOString().split('T')[0]
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

const truncatedVote = computed(() => {
  if (!props.film?.vote_average) return null;
  return Number(props.film.vote_average).toFixed(1);
})

const formattedOtherTitles = computed(() => {
  const titles = props.film?.alternative_titles;
  if (!titles) return null;

  try {
    const parsed = typeof titles === 'string' && titles.startsWith('{') 
      ? JSON.parse(titles) 
      : titles;

    if (typeof parsed === 'object' && !Array.isArray(parsed)) {
      const mapping = { en: 'Inglés', es: 'Español', fr: 'Francés', it: 'Italiano' };
      return Object.entries(parsed)
        .map(([lang, title]) => `<span class="text-white/40">${mapping[lang] || lang}:</span> ${title}`)
        .join(' <br> '); 
    }
    
    if (Array.isArray(parsed)) return parsed.join(' • ');
    return parsed;
  } catch (e) {
    return titles;
  }
});

// --- REFERENCIAS DE IMAGEN ---

const directorPhoto = computed(() => {
  if (!directors.value || !directors.value.length) return null;
  const path = directors.value[0].profile_path;
  return path ? getImageUrl(path) : null;
});

const backdropUrl = computed(() => {
  return props.film?.backdrop ? getImageUrl(props.film.backdrop, 'original') : null;
});

// --- REPARTO Y DIRECCIÓN ---

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

const tmdbUrl = computed(() => {
  return props.film?.tmdb_id 
    ? `https://www.themoviedb.org/movie/${props.film.tmdb_id}` 
    : '#';
});

const cleanList = (data) => {
  if (!data) return []
  if (Array.isArray(data)) return data
  return data.split('\n').filter(item => item.trim() !== '')
}
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/90 backdrop-blur-md">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden max-h-[94vh] flex flex-col relative">
      
      <div class="p-5 border-b border-slate-800 flex justify-between items-center bg-slate-900 z-20">
        <div>
          <h2 class="text-xl font-black text-white uppercase tracking-tighter">{{ film.title }}</h2>
          <div class="flex items-center gap-4 mt-1">
             <p class="text-[10px] text-slate-500 font-bold uppercase tracking-widest">Ficha Técnica</p>
             
             <div v-if="truncatedVote" class="flex items-center gap-1.5">
                <span class="text-[9px] text-slate-500 font-bold uppercase">TMDB</span>
                <span class="bg-yellow-500 text-black text-[10px] font-black px-1.5 py-0.5 rounded">{{ truncatedVote }}</span>
             </div>

             <a :href="tmdbUrl" target="_blank" class="text-[10px] text-yellow-500 hover:text-yellow-700 font-bold uppercase flex items-center gap-1 transition-colors">
               <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
               Ver en TMDB
             </a>
          </div>
        </div>
        <button @click="close" class="bg-slate-900 hover:bg-slate-800 text-white p-2 rounded-full transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="overflow-y-auto relative flex-1 custom-scrollbar bg-slate-900">
        <div class="absolute inset-0 z-0 pointer-events-none">
          <img v-if="backdropUrl" :src="backdropUrl" class="w-full h-full object-cover opacity-90" />
          <div class="absolute inset-0 bg-gradient-to-b from-slate-900/60 via-slate-900/90 to-slate-900"></div>
        </div>

        <div class="relative z-10">
          <div class="p-6 md:p-10 flex flex-col md:flex-row gap-8 items-center md:items-start">
            
            <div class="flex flex-col gap-4 shrink-0 w-40 md:w-48">
              <div class="relative">
                <img 
                  v-if="film.frame" 
                  :src="film.frame" 
                  class="w-full h-52 md:h-64 object-cover rounded-2xl border-2 border-slate-700/50 shadow-2xl" 
                />
                <div v-else class="w-full h-52 md:h-64 bg-slate-800 rounded-2xl flex items-center justify-center border-2 border-slate-700">
                  <span class="text-slate-500 text-xs font-bold uppercase">Sin foto</span>
                </div>
              </div>

              <div v-if="formattedOtherTitles" class="bg-black/40 backdrop-blur-md p-3 rounded-xl border border-white/5">
                <span class="block text-[9px] font-bold text-yellow-500/70 mb-2 uppercase tracking-tighter">Otros Títulos</span>
                <div class="text-[10px] text-slate-300 leading-relaxed" v-html="formattedOtherTitles"></div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 flex-1 w-full text-left">
              <div class="bg-slate-900/60 backdrop-blur-md p-4 rounded-2xl border border-white/10 col-span-full shadow-lg">
                <span class="block text-[10px] text-yellow-500 font-bold uppercase mb-1">Lanzamiento oficial</span>
                <div class="flex flex-wrap items-baseline gap-2">
                  <span class="text-2xl text-white font-black">{{ filmYear }}</span>
                  <span class="text-xs text-slate-400 font-mono">[{{ formattedReleaseDate }}]</span>
                </div>
              </div>

              <div class="bg-slate-900/60 backdrop-blur-md p-4 rounded-2xl border border-white/10 shadow-lg">
                <span class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Duración</span>
                <span class="text-lg text-white font-semibold">{{ filmDuration || 'N/A' }}</span>
              </div>

              <div class="bg-slate-900/60 backdrop-blur-md p-4 rounded-2xl border border-white/10 shadow-lg">
                <span class="block text-[10px] text-slate-400 font-bold uppercase mb-1">Géneros</span>
                <div class="flex flex-wrap gap-1 mt-1">
                  <span v-for="g in (film.genre?.split(',') || [])" :key="g" 
                    class="text-[9px] bg-white/10 px-2 py-0.5 rounded-full text-slate-200 border border-white/10">
                    {{ g.trim() }}
                  </span>
                </div>
              </div>

              <div v-if="film.awards" class="bg-red-500/10 backdrop-blur-md p-4 rounded-2xl border border-red-500/20 col-span-1 shadow-lg">
                <span class="block text-[10px] text-er-400 font-bold uppercase mb-2">Premios</span>
                <ul class="space-y-1 max-h-[80px] overflow-y-auto custom-scrollbar pr-1">
                   <li v-for="a in cleanList(film.awards)" :key="a" class="text-[11px] text-red-100/80 leading-tight">• {{ a }}</li>
                </ul>
              </div>

              <div v-if="film.nominations" class="bg-amber-500/10 backdrop-blur-md p-4 rounded-2xl border border-amber-500/20 col-span-1 shadow-lg">
                <span class="block text-[10px] text-amber-400 font-bold uppercase mb-2">Nominaciones</span>
                <ul class="space-y-1 max-h-[80px] overflow-y-auto custom-scrollbar pr-1">
                   <li v-for="n in cleanList(film.nominations)" :key="n" class="text-[11px] text-amber-100/80 leading-tight">• {{ n }}</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="p-8 md:p-10 pt-0">
            <div class="bg-slate-900/60 backdrop-blur-sm rounded-3xl p-6 border border-white/10 shadow-2xl">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <div class="md:col-span-1 border-r border-white/5 pr-4">
                  <h4 class="text-[10px] font-bold text-yellow-600 uppercase tracking-[0.2em] mb-4">Dirección</h4>
                  <div class="space-y-4">
                    <div v-for="dir in directors" :key="dir.idPerson" 
                      @click="emit('openPerson', dir.idPerson)" 
                      class="group cursor-pointer">
                      <p class="text-white group-hover:text-yellow-500 font-bold transition-colors leading-tight">{{ dir.name }}</p>
                      <p class="text-[9px] text-slate-500 uppercase mt-0.5">Director Principal</p>
                      
                      <div v-if="directorPhoto" class="mt-4 overflow-hidden rounded-xl border border-white/10 shadow-xl w-32 aspect-[2/3] group-hover:border-yellow-500/50 transition-colors">
                        <img :src="directorPhoto" :alt="dir.name" class="w-full h-full object-cover" />
                      </div>
                    </div>
                  </div>
                </div>

                <div class="md:col-span-2">
                  <h4 class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.2em] mb-4">Reparto Principal</h4>
                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-6">
                    <div v-for="actor in actors.slice(0, 12)" :key="actor.idPerson" @click="emit('openPerson', actor.idPerson)" class="group cursor-pointer">
                      <p class="text-sm text-slate-200 group-hover:text-red-500 transition-colors font-bold leading-none mb-1">{{ actor.name }}</p>
                      <p class="text-[10px] text-slate-500 italic truncate">{{ actor.pivot?.character_name }}</p>
                    </div>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.02);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 255, 255, 0.2);
}
</style>