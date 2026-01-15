<template>
  <div v-if="entry" 
    class="min-h-screen bg-[#17191c] text-slate-300 pb-20 selection:bg-brand/20"
    :class="themeClasses.accentSelection"
  >
    
    <EntryHeader 
      :user="entry.user"
      :title="entry.title"
      :type="entry.type"
      :accent-color="themeClasses.text"
      :bg-gradient="themeClasses.gradient"
    />

    <main class="max-w-6xl mx-auto px-6 mt-4">
      
      <div v-if="entry.type === 'user_debate'" class="grid grid-cols-1 lg:grid-cols-12 gap-12">
        <article class="lg:col-span-8 prose prose-invert max-w-none relative">
          <div :class="['inline-flex items-center gap-2 px-3 py-1 rounded-md text-[10px] font-black mb-6 border uppercase tracking-[2px]', themeClasses.border, themeClasses.text]">
            <span class="w-1 h-1 rounded-full bg-current"></span>
            Tema de Discusión
          </div>

          <div class="relative">
            <span :class="['absolute -top-10 -left-4 text-8xl opacity-20 font-serif select-none', themeClasses.text]">“</span>
            <p class="text-xl md:text-2xl leading-relaxed text-slate-200 font-serif italic border-l-2 pl-8 transition-colors duration-500 relative z-10" :class="themeClasses.border">
              {{ entry.content }}
            </p>
          </div>
        </article>
        
        <aside class="lg:col-span-4">
          <div class="sticky top-10 p-6 bg-slate-900/30 border border-slate-800/50 rounded-2xl backdrop-blur-md">
            <h4 class="text-[10px] font-black tracking-widest text-slate-500 uppercase mb-6 flex items-center justify-between">
              Referencias Visuales
              <span :class="['px-2 py-0.5 rounded text-[8px] border', themeClasses.border, themeClasses.text]">Fichas</span>
            </h4>
            
            <div class="grid grid-cols-3 gap-3">
              <div v-for="film in mappedFilms" :key="film.id" class="group relative">
                <div class="aspect-[2/3] rounded-md overflow-hidden border border-slate-800 bg-black transition-all group-hover:border-orange-500/50 group-hover:scale-105 shadow-lg">
                  <img :src="film.poster_url" class="w-full h-full object-cover" :alt="film.title">
                  <div class="absolute inset-0 bg-orange-600/20 opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"></div>
                </div>
                <div class="absolute -bottom-2 left-0 right-0 opacity-0 group-hover:opacity-100 transition-all transform translate-y-2 z-20">
                   <p class="bg-slate-900 text-[8px] text-white p-1 rounded border border-slate-700 truncate text-center shadow-2xl">
                     {{ film.title }}
                   </p>
                </div>
              </div>
            </div>
          </div>
        </aside>
      </div>

      <div v-else-if="entry.type === 'user_list'" class="max-w-4xl mx-auto">
        <div class="text-center mb-12">
          <p class="text-yellow-600 text-3xl mb-2 font-serif">--</p>
          <p class="text-slate-400 text-lg italic leading-relaxed px-10">{{ entry.content }}</p>
        </div>
        <div class="bg-gradient-to-b from-slate-900/40 to-transparent p-6 border-t border-slate-800/50 rounded-3xl shadow-2xl">
           <MovieGrid :films="mappedFilms" show-numbers />
        </div>
      </div>

      <div v-else-if="entry.type === 'user_review'" class="grid grid-cols-1 md:grid-cols-12 gap-10 items-start">
        <aside class="md:col-span-4 lg:col-span-3 sticky top-10">
          <div class="relative group">
            <div class="absolute -inset-1 bg-[#BE2B0C] rounded-xl blur opacity-10 group-hover:opacity-30 transition duration-1000"></div>
            <div class="relative shadow-2xl rounded-xl overflow-hidden border border-slate-700 bg-slate-900">
                <img :src="mappedFilms[0]?.poster_url" class="w-full h-[360px] object-cover transition-transform duration-700 group-hover:scale-105" />
                <div class="absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black via-black/90 to-transparent">
                   <p class="text-white font-black text-base uppercase tracking-tighter leading-tight">{{ mappedFilms[0]?.title }}</p>
                   <p class="text-[#BE2B0C] text-[10px] font-black tracking-widest mt-1">{{ mappedFilms[0]?.year }}</p>
                </div>
            </div>
          </div>
        </aside>

        <div class="md:col-span-8 lg:col-span-9">
           <div class="flex items-center gap-3 mb-8">
             <div class="h-[1px] w-6 bg-[#BE2B0C]"></div>
             <span class="text-[#BE2B0C] font-black uppercase tracking-[5px] text-[10px]">Crítica analítica</span>
           </div>
           <p class="text-xl md:text-2xl leading-[1.9] text-slate-200 font-light first-letter:text-6xl first-letter:font-black first-letter:text-[#BE2B0C] first-letter:mr-4 first-letter:float-left first-letter:mt-1">
             {{ entry.content }}
           </p>
           
           <div v-if="mappedFilms.length > 1" class="mt-20 pt-10 border-t border-slate-800/50">
             <h4 class="text-slate-600 text-[10px] font-black uppercase tracking-[3px] mb-8">Otras películas analizadas</h4>
             <MovieGrid :films="mappedFilms.slice(1)" />
           </div>
        </div>
      </div>

      <footer class="mt-32 pt-16 border-t border-slate-800/30">
        <CommentSection 
          type="entry"
          :entry-id="entry.id"
          :is-authenticated="auth.isAuthenticated"
          :current-user-id="auth.user?.id"
          :accent-class="themeClasses.button"
        />
      </footer>
    </main>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';
import EntryHeader from '@/components/EntryHeader.vue';
import MovieGrid from '@/components/MovieGrid.vue';
import CommentSection from '@/components/CommentSection.vue';

const route = useRoute();
const auth = useAuthStore();
const entry = ref(null);

const themeClasses = computed(() => {
  if (entry.value?.type === 'user_list') return {
    text: 'text-yellow-600',
    border: 'border-yellow-600/30',
    gradient: 'from-yellow-600/10',
    button: 'bg-yellow-600 hover:bg-yellow-500',
    accentSelection: 'selection:bg-yellow-600/20'
  };
  
  if (entry.value?.type === 'user_debate') return {
      text: 'text-orange-400', 
      border: 'border-orange-500/30',
      gradient: 'from-orange-600/10',
      button: 'bg-orange-600 hover:bg-orange-500',
      accentSelection: 'selection:bg-orange-500/20'
  };

  return {
    text: 'text-[#BE2B0C]',
    border: 'border-[#BE2B0C]/30',
    gradient: 'from-[#BE2B0C]/10',
    button: 'bg-[#BE2B0C] hover:bg-red-700',
    accentSelection: 'selection:bg-[#BE2B0C]/20'
  };
});

const mappedFilms = computed(() => {
  return entry.value?.films?.map(f => ({
    ...f,
    poster_url: f.frame
  })) || [];
});

const loadData = async () => {
  try {
    const { data } = await api.get(`/user_entries/${route.params.id}`);
    entry.value = data.data || data;
  } catch (e) { console.error("Error cargando la entrada:", e); }
};

onMounted(loadData);
</script>