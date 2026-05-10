<template>
  <div v-if="entry" 
    class="min-h-screen bg-[#17191c] text-slate-300 pb-20 selection:bg-brand/20"
    :class="themeClasses.accentSelection"
  >
    
    <EntryHeader
      :bg-gradient="themeClasses.gradient"
      :films="entry.films"
    />

    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 pb-20 relative z-10">

      <!-- Meta del entry — solapado sobre el fondo, igual que UserProfile -->
      <div class="-mt-20 sm:-mt-28 md:-mt-36 mb-8 md:mb-12 relative z-20">
        <div class="flex flex-wrap items-center gap-3 sm:gap-6 mb-6 md:mb-8">
          <div :class="['inline-flex items-center gap-2 px-3 py-1 bg-slate-900/80 border rounded-full text-[10px] font-black uppercase tracking-[2px] shadow-sm backdrop-blur-md', themeClasses.border, themeClasses.text]">
            <span class="w-1.5 h-1.5 rounded-full animate-pulse bg-current"></span>
            {{ typeLabel }}
          </div>

          <span class="text-slate-800 font-thin text-2xl select-none">|</span>

          <div class="flex items-center gap-4 group cursor-pointer" @click="router.push({ name: 'user-profile', params: { username: entry.user?.name } })">
            <div class="w-8 h-8 rounded-full border border-slate-700 bg-slate-800 flex items-center justify-center shadow-xl transition-all duration-300 group-hover:border-orange-400/60 flex-shrink-0 overflow-hidden">
              <span class="text-xs font-black text-slate-400 group-hover:text-orange-400 transition-colors select-none">
                {{ entry.user?.name?.charAt(0).toUpperCase() || '?' }}
              </span>
            </div>
            <p class="text-[11px] tracking-tight">
              <span class="text-slate-500 italic font-light lowercase">escrito por</span>
              <span class="text-white font-black ml-3 uppercase tracking-[2px] transition-colors group-hover:text-orange-400">
                @{{ entry.user?.name }}
              </span>
            </p>
          </div>
        </div>

        <div class="flex items-center justify-between gap-4 mb-6">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white tracking-tighter leading-[1.1] max-w-4xl drop-shadow-md">
            {{ entry.title }}
          </h1>

          <div v-if="isOwner" class="flex items-center gap-2 shrink-0">
            <button
              @click="editEntry"
              class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all duration-200 border bg-slate-900 border-slate-700 text-slate-400 hover:border-brand hover:text-brand"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
              </svg>
              Editar
            </button>
            <button
              @click="deleteEntry"
              :disabled="isDeleting"
              class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-[10px] font-black uppercase tracking-widest transition-all duration-200 border bg-slate-900 border-slate-700 text-slate-400 hover:border-red-500 hover:text-red-500 disabled:opacity-50"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
              </svg>
              {{ isDeleting ? 'Borrando...' : 'Borrar' }}
            </button>
          </div>
        </div>
      </div>

      <main>
        
        <!-- DEBATE - Reddit-style thread -->
        <div v-if="entry.type === 'user_debate'" class="max-w-3xl mx-auto">

          <!-- Post card -->
          <div class="rounded-2xl overflow-hidden border border-slate-800/80 bg-[#1a1d21] shadow-2xl">

            <!-- Thread top bar -->
            <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-900/70 border-b border-slate-800/50 text-[9px] font-black uppercase tracking-widest text-slate-500">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-orange-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
              </svg>
              <span :class="themeClasses.text">Debate</span>
              <span class="text-slate-700">•</span>
              <span>FilmoClub</span>
            </div>

            <div class="flex">

              <!-- Like column -->
              <div class="flex flex-col items-center justify-center gap-1.5 px-3 py-5 bg-slate-900/20 border-r border-slate-800/40 min-w-[48px] shrink-0">
                <button
                  v-if="auth.isAuthenticated"
                  @click="toggleLike"
                  :disabled="savingLike"
                  :class="['flex flex-col items-center gap-1 p-1 rounded transition-colors', likeSaved ? themeClasses.text : 'text-slate-600 hover:text-orange-400']"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" :fill="likeSaved ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                  </svg>
                  <span class="text-xs font-black tabular-nums">{{ entry.likes_count || 0 }}</span>
                </button>
                <button v-else @click="isLoginOpen = true" class="flex flex-col items-center gap-1 text-slate-700 hover:text-orange-400 transition-colors p-1 rounded">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" />
                  </svg>
                  <span class="text-xs font-black tabular-nums">{{ entry.likes_count || 0 }}</span>
                </button>
              </div>

              <!-- Content -->
              <div class="flex-1 min-w-0 p-4 sm:p-6">

                <!-- Pills: tipo + films -->
                <div class="flex flex-wrap items-center gap-2 mb-4">
                  <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border', themeClasses.border, themeClasses.text]">
                    <span class="w-1 h-1 rounded-full bg-current animate-pulse"></span>
                    Debate
                  </span>
                  <span
                    v-for="film in mappedFilms"
                    :key="film.idFilm"
                    @click="router.push({ name: 'film-detail', params: { id: film.idFilm } })"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[9px] font-bold uppercase tracking-wide bg-slate-800/80 border border-slate-700/50 text-slate-400 hover:border-orange-500/60 hover:text-slate-200 cursor-pointer transition-colors"
                  >
                    {{ film.title }}
                  </span>
                </div>

                <!-- Debate content -->
                <div
                  :class="['ck-content text-base md:text-lg leading-relaxed text-slate-200 border-l-2 pl-5 mb-5', themeClasses.border]"
                  v-html="entry.content"
                ></div>

                <!-- Film poster strip -->
                <div v-if="mappedFilms.length" class="flex gap-3 mt-5 overflow-x-auto pb-2 -mx-1 px-1">
                  <div
                    v-for="film in mappedFilms"
                    :key="film.idFilm"
                    @click="router.push({ name: 'film-detail', params: { id: film.idFilm } })"
                    class="shrink-0 group relative cursor-pointer"
                  >
                    <div :class="['h-[100px] w-[66px] rounded-lg overflow-hidden border transition-all duration-200 shadow-lg group-hover:scale-105 group-hover:shadow-orange-900/40 group-hover:shadow-xl', themeClasses.border]" style="border-opacity:0.4">
                      <img :src="film.poster_url" :alt="film.title" class="w-full h-full object-cover">
                    </div>
                    <p class="text-[8px] text-slate-500 text-center mt-1 max-w-[66px] truncate">{{ film.title }}</p>
                  </div>
                </div>

                <!-- Action bar -->
                <div class="flex items-center justify-between mt-5 pt-3 border-t border-slate-800/40">
                  <span class="flex items-center gap-1.5 text-[10px] font-black text-slate-500 uppercase tracking-wide">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 9.75a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375m-13.5 3.01c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.184-4.183a1.14 1.14 0 01.778-.332 48.294 48.294 0 005.83-.498c1.585-.233 2.708-1.626 2.708-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z" />
                    </svg>
                    {{ debateCommentCount }} comentarios
                  </span>
                  <button
                    @click="handleCreate"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[10px] font-black uppercase tracking-wide border border-brand/40 text-brand hover:bg-brand hover:text-white transition-all duration-200"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Nueva entrada
                  </button>
                </div>

              </div>
            </div>
          </div>

        </div>

        <div v-else-if="entry.type === 'user_list'" class="max-w-4xl mx-auto">

          <div class="text-center mb-12">
            <p class="text-yellow-600 text-3xl mb-2 font-serif">--</p>
            <div class="ck-content text-slate-400 text-lg italic leading-relaxed px-4 md:px-10" v-html="entry.content"></div>
          </div>
          <div class="bg-gradient-to-b from-slate-900/40 to-transparent p-6 border-t border-slate-800/50 rounded-3xl shadow-2xl">
              <MovieGrid :films="mappedFilms" show-numbers />
          </div>

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

            <button
              @click="handleCreate"
              class="flex items-center gap-1.5 px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest border border-brand/50 text-brand hover:bg-brand hover:text-white transition-all duration-300"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
              </svg>
              Crear entrada
            </button>
          </div>
        </div>

        <div v-else-if="entry.type === 'user_review'" class="grid grid-cols-1 md:grid-cols-12 gap-8 lg:gap-16 items-start">
          <aside class="md:col-span-4 lg:col-span-3 md:sticky md:top-10">
            <div class="relative group cursor-pointer" @click="router.push({ name: 'film-detail', params: { id: mappedFilms[0]?.idFilm } })">
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
             <div class="review-content ck-content text-xl md:text-2xl leading-[1.9] text-slate-200 font-light" v-html="entry.content"></div>

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
              <button
                @click="handleCreate"
                class="flex items-center gap-1.5 px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest border border-brand/50 text-brand hover:bg-brand hover:text-white transition-all duration-300"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Crear entrada
              </button>
             </div>
             
             <div v-if="mappedFilms.length > 1" class="mt-20 pt-10 border-t border-slate-800/50">
               <h4 class="text-slate-600 text-[10px] font-black uppercase tracking-[3px] mb-8">Otras películas analizadas</h4>
               <MovieGrid :films="mappedFilms.slice(1)" />
             </div>
          </div>
        </div>

        <footer :class="entry.type === 'user_debate' ? 'mt-6 max-w-3xl mx-auto' : 'mt-32 pt-16 border-t border-slate-800/30'">
          <CommentSection
            type="entry"
            :entry-id="entry.id"
            :is-authenticated="auth.isAuthenticated"
            :current-user-id="auth.user?.id"
            :accent-class="themeClasses.button"
            :variant="entry.type === 'user_debate' ? 'thread' : 'default'"
            @count="debateCommentCount = $event"
          />
        </footer>
      </main>

    </div>
    <LoginModal :is-open="isLoginOpen" @close="isLoginOpen = false" />
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';
import EntryHeader from '@/components/EntryHeader.vue';
import MovieGrid from '@/components/MovieGrid.vue';
import CommentSection from '@/components/CommentSection.vue';
import LoginModal from '@/components/LoginModal.vue';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const entry = ref(null);

const isLoginOpen = ref(false);
const debateCommentCount = ref(0);

const handleCreate = () => {
  if (!auth.isAuthenticated) { isLoginOpen.value = true; return; }
  router.push({ name: 'create-entry' });
};

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
    bar: 'bg-yellow-600',
    accentSelection: 'selection:bg-yellow-600/20'
  };

  if (entry.value?.type === 'user_debate') return {
      text: 'text-orange-400',
      border: 'border-orange-500/30',
      gradient: 'from-orange-600/10',
      button: 'bg-orange-600 hover:bg-orange-500',
      bar: 'bg-orange-400',
      accentSelection: 'selection:bg-orange-500/20'
  };

  return {
    text: 'text-[#BE2B0C]',
    border: 'border-[#BE2B0C]/30',
    gradient: 'from-[#BE2B0C]/10',
    button: 'bg-[#BE2B0C] hover:bg-red-700',
    bar: 'bg-[#BE2B0C]',
    accentSelection: 'selection:bg-[#BE2B0C]/20'
  };
});

const typeLabel = computed(() => {
  if (entry.value?.type === 'user_list') return 'Lista';
  if (entry.value?.type === 'user_debate') return 'Debate';
  return 'Crítica';
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

const isOwner = computed(() =>
  auth.isAuthenticated && entry.value && auth.user?.id === entry.value.user_id
);

const editEntry = () => {
  router.push({ name: 'create-entry', params: { id: entry.value.id } });
};

const isDeleting = ref(false);
const deleteEntry = async () => {
  if (!confirm('¿Seguro que quieres borrar esta entrada? Esta acción no se puede deshacer.')) return;
  isDeleting.value = true;
  try {
    await api.delete(`/user_entries/${entry.value.id}`);
    router.push({ name: 'entry-feed' });
  } catch (e) {
    console.error("Error al borrar entrada:", e);
    alert("No se pudo borrar la entrada.");
  } finally {
    isDeleting.value = false;
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

<style>
.review-content p:first-child::first-letter {
  font-size: 3.75rem;
  font-weight: 900;
  color: #BE2B0C;
  float: left;
  margin-right: 0.75rem;
  margin-top: 0.25rem;
  line-height: 1;
}
</style>

<style scoped>
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}
</style>