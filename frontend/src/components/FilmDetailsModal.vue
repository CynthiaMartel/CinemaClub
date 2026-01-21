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

const awardsProcessed = computed(() => {
  const awardsList = cleanList(props.film.awards);
  return awardsList && awardsList > 0;
})
const nominationsProcessed = computed(() => {
  const nominationsList = cleanList(props.film.awards);
  return nominationsList && nominationsList > 0;
})

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
  <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-4 backdrop-blur-md">
    <div class="absolute inset-0 bg-slate-950/40" @click="close"></div>

    <div class="bg-slate-900 border border-slate-800 w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden max-h-[94vh] flex flex-col relative transition-all">
      
      <div class="p-5 md:p-6 border-b border-slate-800 flex justify-between items-center bg-[#1b2228] z-20">
        <div class="min-w-0 flex-1">
          <h2 class="text-xl md:text-2xl font-black text-white uppercase tracking-tighter truncate">{{ film.title }}</h2>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-2 mt-1">
             <p class="text-[9px] text-slate-500 font-black uppercase tracking-[0.2em]">Ficha Técnica</p>
             
             <div v-if="truncatedVote" class="flex items-center gap-1.5 border-l border-slate-700 pl-4">
                <span class="text-[9px] text-slate-500 font-bold uppercase">TMDB</span>
                <span class="bg-yellow-500 text-black text-[10px] font-black px-1.5 py-0.5 rounded-sm">{{ truncatedVote }}</span>
             </div>

             <a :href="tmdbUrl" target="_blank" class="text-[9px] text-yellow-500 hover:text-yellow-600 font-black uppercase flex items-center gap-1 transition-colors">
               <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>
               TMDB
             </a>
          </div>
        </div>
        <button @click="close" class="ml-4 bg-slate-900 hover:bg-slate-800 text-white p-2 rounded-full transition-all border border-slate-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <div class="overflow-y-auto relative flex-1 custom-scrollbar bg-slate-900">
        <div class="absolute inset-0 z-0 pointer-events-none">
          <img v-if="backdropUrl" :src="backdropUrl" class="w-full h-full object-cover opacity-30" />
          <div class="absolute inset-0 bg-gradient-to-b from-slate-900/80 via-slate-900/95 to-slate-900"></div>
        </div>

        <div class="relative z-10">
          <div class="p-6 md:p-10 flex flex-col md:flex-row gap-8 items-center md:items-start">
            
            <div class="flex flex-col gap-4 shrink-0 w-48 md:w-56">
              <div class="relative group">
                <img 
                  v-if="film.frame" 
                  :src="film.frame" 
                  class="w-full aspect-[2/3] object-cover rounded-2xl border border-slate-700 shadow-2xl transition-transform duration-500 group-hover:scale-[1.02]" 
                />
                <div v-else class="w-full aspect-[2/3] bg-slate-800 rounded-2xl flex items-center justify-center border border-slate-700">
                  <span class="text-slate-500 text-[10px] font-black uppercase">Sin foto</span>
                </div>
              </div>

              <div v-if="formattedOtherTitles" class="bg-black/40 backdrop-blur-md p-4 rounded-2xl border border-white/5">
                <span class="block text-[9px] font-black text-yellow-500/70 mb-2 uppercase tracking-widest">Títulos Alternativos</span>
                <div class="text-[11px] text-slate-300 leading-relaxed italic font-serif" v-html="formattedOtherTitles"></div>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 flex-1 w-full text-left">
              <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-white/10 col-span-full shadow-lg">
                <span class="block text-[9px] text-red-500 font-black uppercase tracking-widest mb-1">Lanzamiento oficial</span>
                <div class="flex flex-wrap items-baseline gap-2">
                  <span class="text-3xl text-white font-black leading-none">{{ filmYear }}</span>
                  <span class="text-[10px] text-slate-500 font-mono tracking-tighter uppercase">{{ formattedReleaseDate }}</span>
                </div>
              </div>

              <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-white/10 shadow-lg">
                <span class="block text-[9px] text-slate-500 font-black uppercase tracking-widest mb-1">Duración</span>
                <span class="text-xl text-white font-black">{{ filmDuration || 'N/A' }}</span>
              </div>

              <div class="bg-slate-900/60 backdrop-blur-md p-5 rounded-2xl border border-white/10 shadow-lg">
                <span class="block text-[9px] text-slate-500 font-black uppercase tracking-widest mb-2">Géneros</span>
                <div class="flex flex-wrap gap-1.5">
                  <span v-for="g in (film.genre?.split(',') || [])" :key="g" 
                    class="text-[9px] bg-white/5 px-2 py-0.5 rounded border border-white/10 text-slate-200 font-bold uppercase">
                    {{ g.trim() }}
                  </span>
                </div>
              </div>

              <div v-if="awardsProcessed" class="bg-red-500/5 backdrop-blur-md p-5 rounded-2xl border border-red-500/20 shadow-lg">
                <span class="block text-[9px] text-red-400 font-black uppercase tracking-widest mb-3">Premios</span>
                <ul class="space-y-2 max-h-[100px] overflow-y-auto custom-scrollbar-thin">
                   <li v-for="a in awardsProcessed" :key="a" class="text-[11px] text-red-100/70 leading-tight">• {{ a }}</li>
                </ul>
              </div>

              <div v-if="nominationsProcessed" class="bg-amber-500/5 backdrop-blur-md p-5 rounded-2xl border border-amber-500/20 shadow-lg">
                <span class="block text-[9px] text-amber-400 font-black uppercase tracking-widest mb-3">Nominaciones</span>
                <ul class="space-y-2 max-h-[100px] overflow-y-auto custom-scrollbar-thin">
                   <li v-for="n in nominationsProcessed" :key="n" class="text-[11px] text-amber-100/70 leading-tight">• {{ n }}</li>
                </ul>
              </div>
            </div>
          </div>

          <div class="p-6 md:p-10 pt-0">
            <div class="bg-slate-900/40 backdrop-blur-sm rounded-3xl p-6 md:p-8 border border-white/5 shadow-2xl">
              <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
                
                <div class="lg:col-span-1 lg:border-r lg:border-white/5 lg:pr-8">
                  <h4 class="text-[9px] font-black text-yellow-600 uppercase tracking-[0.3em] mb-6">Dirección</h4>
                  <div class="space-y-6">
                    <div v-for="dir in directors" :key="dir.idPerson" 
                      @click="emit('openPerson', dir.idPerson)" 
                      class="group cursor-pointer">
                      <p class="text-white group-hover:text-yellow-500 font-black transition-colors leading-tight text-lg tracking-tight">{{ dir.name }}</p>
                      <p class="text-[9px] text-slate-500 font-black uppercase mt-1 tracking-widest">Director Principal</p>
                      
                      <div v-if="directorPhoto" class="mt-4 overflow-hidden rounded-2xl border border-white/10 shadow-xl w-32 aspect-[2/3] transition-all duration-500 group-hover:border-yellow-500/50">
                        <img :src="directorPhoto" :alt="dir.name" class="w-full h-full object-cover" />
                      </div>
                    </div>
                  </div>
                </div>

                <div class="lg:col-span-2">
                  <h4 class="text-[9px] font-black text-slate-500 uppercase tracking-[0.3em] mb-6">Reparto Principal</h4>
                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-6">
                    <div v-for="actor in actors.slice(0, 15)" :key="actor.idPerson" @click="emit('openPerson', actor.idPerson)" class="group cursor-pointer">
                      <p class="text-sm text-slate-200 group-hover:text-red-500 transition-colors font-black leading-tight mb-1 uppercase tracking-tighter">{{ actor.name }}</p>
                      <p class="text-[10px] text-slate-500 italic truncate font-serif">{{ actor.pivot?.character_name }}</p>
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
.font-serif {
  font-family: 'Tiempos Headline', Georgia, serif;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: rgba(0, 0, 0, 0.1);
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #1e293b;
  border-radius: 10px;
}
.custom-scrollbar-thin::-webkit-scrollbar {
  width: 3px;
}
.custom-scrollbar-thin::-webkit-scrollbar-thumb {
  background: rgba(255, 255, 255, 0.05);
}
</style>