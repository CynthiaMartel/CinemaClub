<template>
  <div v-if="entry" 
    class="min-h-screen bg-[#17191c] text-slate-300 pb-20 selection:bg-brand/20"
    :class="themeClasses.accentSelection"
  >
    
    <div class="content-wrap relative z-10 mx-auto max-w-[1200px] px-6 sm:px-12 md:px-24 py-10">

      <EntryHeader 
        :user="entry.user"
        :title="entry.title"
        :type="entry.type"
        :accent-color="themeClasses.text"
        :bg-gradient="themeClasses.gradient"
        :films="entry.films"
      />

      <main class="mt-8">
        
        <div v-if="entry.type === 'user_debate'" class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16">
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

            <div class="mt-10 flex items-center gap-4">
              <button 
                v-if="auth.isAuthenticated"
                @click="toggleLike"
                :disabled="savingLike"
                :class="[
                  'group flex items-center gap-2 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 border',
                  likeSaved 
                    ? 'bg-red-500/10 border-red-500/50 text-red-500' 
                    : 'bg-slate-900 border-slate-700 text-slate-400 hover:border-red-500 hover:text-red-500'
                ]"
              >
                <span v-if="savingLike" class="animate-spin mr-1">○</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" :fill="likeSaved ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                </svg>
                {{ likeSaved ? 'Te gusta' : 'Me gusta' }}
                <span v-if="entry.likes_count > 0" class="ml-1 opacity-60">{{ entry.likes_count }}</span>
              </button>
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
          
          <div class="flex justify-end items-center gap-3 mb-8">
            <button 
              v-if="auth.isAuthenticated"
              @click="toggleLike"
              :disabled="savingLike"
              :class="[
                'group flex items-center gap-2 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 border',
                likeSaved 
                  ? 'bg-red-500/10 border-red-500/50 text-red-500' 
                  : 'bg-slate-900 border-slate-700 text-slate-400 hover:border-red-500 hover:text-red-500'
              ]"
            >
              <span v-if="savingLike" class="animate-spin mr-1">○</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" :fill="likeSaved ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
              </svg>
              {{ likeSaved ? 'Te gusta' : 'Me gusta' }}
              <span v-if="entry.likes_count > 0" class="ml-1 opacity-60">{{ entry.likes_count }}</span>
            </button>

            <button 
              v-if="auth.isAuthenticated"
              @click="toggleSaveList"
              :disabled="isSavingList"
              :class="[
                'group flex items-center gap-2 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 border',
                isSavedList 
                  ? 'bg-yellow-600/10 border-yellow-600/50 text-yellow-600' 
                  : 'bg-slate-900 border-slate-700 text-slate-400 hover:border-yellow-600 hover:text-yellow-600'
              ]"
            >
              <span v-if="isSavingList" class="animate-spin mr-1">○</span>
              <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" :fill="isSavedList ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z" />
              </svg>
              {{ isSavedList ? 'Lista Guardada' : 'Guardar Lista' }}
            </button>
          </div>
          
          <div class="text-center mb-12">
            <p class="text-yellow-600 text-3xl mb-2 font-serif">--</p>
            <p class="text-slate-400 text-lg italic leading-relaxed px-10">{{ entry.content }}</p>
          </div>
          <div class="bg-gradient-to-b from-slate-900/40 to-transparent p-6 border-t border-slate-800/50 rounded-3xl shadow-2xl">
              <MovieGrid :films="mappedFilms" show-numbers />
          </div>
        </div>

        <div v-else-if="entry.type === 'user_review'" class="grid grid-cols-1 md:grid-cols-12 gap-10 lg:gap-16 items-start">
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

             <div class="mt-12 flex items-center gap-4">
              <button 
                v-if="auth.isAuthenticated"
                @click="toggleLike"
                :disabled="savingLike"
                :class="[
                  'group flex items-center gap-2 px-5 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all duration-300 border',
                  likeSaved 
                    ? 'bg-red-500/10 border-red-500/50 text-red-500' 
                    : 'bg-slate-900 border-slate-700 text-slate-400 hover:border-red-500 hover:text-red-500'
                ]"
              >
                <span v-if="savingLike" class="animate-spin mr-1">○</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" :fill="likeSaved ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                </svg>
                {{ likeSaved ? 'Te gusta' : 'Me gusta' }}
                <span v-if="entry.likes_count > 0" class="ml-1 opacity-60">{{ entry.likes_count }}</span>
              </button>
             </div>
             
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

const isSavedList = ref(false);
const isSavingList = ref(false);

const savingLike = ref(false);
const likeSaved = ref(false);

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

const toggleSaveList = async () => {
  if (!entry.value || isSavingList.value) return;
  
  isSavingList.value = true;
  try {
    await api.post(`/user_entries_lists/${entry.value.id}/save`);
    isSavedList.value = !isSavedList.value;
  } catch (e) {
    console.error("Error al guardar/quitar la lista:", e);
  } finally {
    isSavingList.value = false;
  }
};


const toggleLike = async () => {
  if (!entry.value || savingLike.value) return;
  
  savingLike.value = true;
  try {
    const { data } = await api.post(`/user_entries/${entry.value.id}/like`);
    
    likeSaved.value = !likeSaved.value;

    if (entry.value && data.likes_count !== undefined) {
      entry.value.likes_count = data.likes_count;
    }

  } catch (e) {
    console.error("Error en like dar/quitar", e);
  } finally {
    savingLike.value = false;
  }
};

const loadData = async () => {
  try {
    // Usamos GET para consultar (show)
    const { data } = await api.get(`/user_entries/${route.params.id}/show`);
    
    const entryData = data.data; 
    entry.value = entryData;
    
    isSavedList.value = !!entryData.saved; // El !! lo convierte a booleano real
    
    likeSaved.value = !!entryData.is_like
    
    console.log("Estado de guardado cargado:", isSavedList.value);
  } catch (e) { 
    console.error("Error cargando la entrada:", e); 
  }
};

onMounted(loadData);
</script>