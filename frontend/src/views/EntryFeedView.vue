<template>
  <div class="min-h-screen text-slate-100 font-sans bg-[#0f1113] overflow-x-hidden pb-20">
    
    <nav class="sticky top-0 z-50 bg-[#0f1113]/90 backdrop-blur-xl border-b border-white/5">
  <div class="max-w-7xl mx-auto px-4 flex items-center justify-start md:justify-center gap-6 md:gap-8 h-16 overflow-x-auto no-scrollbar">
    <button 
      v-for="tab in tabs" :key="tab.id"
      @click="setTab(tab.id)"
      :class="[
        'text-[10px] md:text-[11px] font-black uppercase tracking-[0.2em] transition-all relative h-full flex items-center flex-shrink-0',
        activeFilter === tab.id ? 'text-white' : 'text-slate-500 hover:text-slate-300'
      ]"
    >
      {{ tab.name }}
      <span v-if="activeFilter === tab.id" class="absolute bottom-0 left-0 w-full h-0.5 bg-brand shadow-[0_0_10px_#10b981]"></span>
    </button>
  </div>
</nav>

<header class="max-w-7xl mx-auto px-6 pt-10 md:pt-12 pb-6">
  <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
    <div class="text-center md:text-left">
      <h1 class="text-4xl md:text-5xl font-black text-white uppercase italic tracking-tighter leading-none">
        {{ currentTabName }}
      </h1>
      <p class="text-slate-500 font-medium uppercase text-[9px] md:text-[10px] mt-4 tracking-[0.2em] md:tracking-[0.3em]">
        {{ filterUserId ? 'Mostrando tus creaciones' : currentTabDescription }}
      </p>
    </div>

    <div v-if="activeFilter !== 'all' && auth.isAuthenticated" class="relative group self-center md:self-auto">
      <button class="bg-slate-900 border border-slate-800 px-5 py-2.5 rounded-xl text-slate-300 text-[10px] font-black uppercase tracking-widest flex items-center gap-3">
        <i class="bi bi-funnel"></i>
        {{ filterUserId ? 'Mis Publicaciones' : 'Explorar Todo' }}
        <i class="bi bi-chevron-down text-[8px]"></i>
      </button>
      <div class="absolute right-0 md:right-0 left-0 md:left-auto mt-2 w-56 bg-[#1c1c1c] border border-white/5 rounded-2xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50">
         </div>
    </div>
  </div>
</header>

    <main class="max-w-7xl mx-auto px-6">
      
      <div v-if="loading && entries.length === 0" class="mt-10 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div v-for="i in 3" :key="i" class="h-64 bg-slate-900/50 rounded-3xl animate-pulse"></div>
      </div>

      <div v-else-if="activeFilter === 'all'" class="flex flex-col gap-24 mt-10">
        
        <section v-for="section in contentSections" :key="section.type" class="flex flex-col">
          <div class="flex items-center justify-between mb-8 border-b border-slate-800 pb-4">
            <div class="flex items-center gap-6">
              <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">{{ section.label }}</h2>
              
              <div v-if="auth.isAuthenticated" class="relative group">
                <button class="text-[8px] bg-slate-900/50 border border-slate-800 px-3 py-1 rounded text-slate-500 uppercase font-black flex items-center gap-2">
                  Filtrar <i class="bi bi-chevron-down"></i>
                </button>
                <div class="absolute left-0 mt-2 w-48 bg-[#1c1c1c] border border-slate-800 rounded-xl shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 overflow-hidden">
                  <button @click="setTab(section.type)" class="w-full text-left px-4 py-3 text-[9px] uppercase font-bold text-slate-400 hover:bg-white/5">Explorar Global</button>
                  <button @click="filterByMe(section.type)" class="w-full text-left px-4 py-3 text-[9px] uppercase font-bold text-slate-400 hover:bg-white/5">Mis {{ section.label }}</button>
                </div>
              </div>
            </div>
          </div>

          <div class="brand-scroll flex flex-row-reverse gap-10 overflow-x-auto pb-8 pt-4 rtl-container">
            <div 
              v-for="item in getEntriesByType(section.type)" 
              :key="item.id" 
              @click="goToEntry(item)"
              class="flex-shrink-0 group cursor-pointer ltr-content"
              :class="section.type === 'user_list' ? 'w-[220px]' : 'w-[200px]'"
            >
              <template v-if="section.type === 'user_list'">
                <div class="poster-stack-container mb-4">
                  <ul class="poster-list-overlapped">
                    <li v-for="(film, idx) in item.films?.slice(0, 5)" :key="idx" class="poster-item" :style="{ zIndex: idx * 10 }">
                      <img :src="film.frame" class="poster-img" />
                    </li>
                  </ul>
                </div>
              </template>
              <template v-else>
                <div class="aspect-video bg-slate-900 border border-slate-800 rounded-xl overflow-hidden mb-4 relative">
                  <img :src="item.films?.[0]?.frame" class="w-full h-full object-cover opacity-50 group-hover:scale-110 transition-all duration-700" />
                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent"></div>
                </div>
              </template>
              
              <h3 class="font-black text-[11px] text-white uppercase truncate group-hover:text-brand transition-colors">{{ item.title }}</h3>
              <p class="text-[9px] text-slate-500 font-bold uppercase mt-1">{{ item.user.name }}</p>
            </div>
          </div>
        </section>
      </div>

      <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-x-8 gap-y-12 mt-10">
        <div v-for="entry in entries" :key="entry.id" @click="goToEntry(entry)" class="group cursor-pointer">
          <div v-if="entry.type === 'user_list'" class="poster-stack-container scale-90 origin-left mb-4">
             <ul class="poster-list-overlapped">
                <li v-for="(f, i) in entry.films?.slice(0, 3)" :key="i" class="poster-item" :style="{ zIndex: i*10, width: '80px', height: '120px' }">
                  <img :src="f.frame" class="w-full h-full object-cover rounded border border-black shadow-2xl" />
                </li>
             </ul>
          </div>
          <div v-else class="aspect-video rounded-2xl bg-slate-900 border border-slate-800 overflow-hidden mb-4 shadow-xl">
             <img :src="entry.films?.[0]?.frame" class="w-full h-full object-cover opacity-60 group-hover:scale-110 transition-transform duration-700" />
          </div>
          <h4 class="text-[12px] font-black uppercase text-white truncate px-1 group-hover:text-brand transition-colors">{{ entry.title }}</h4>
          <p class="text-[9px] text-slate-500 uppercase font-black mt-1 px-1 tracking-widest">{{ entry.user.name }}</p>
        </div>
      </div>

      <div v-if="hasMore" class="flex justify-center py-24">
        <button @click="fetchEntries(true)" class="text-[10px] font-black uppercase tracking-[0.5em] text-slate-600 hover:text-white transition-all">
          {{ loading ? 'Sincronizando...' : 'Cargar más' }}
        </button>
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';

const router = useRouter();
const auth = useAuthStore();

const entries = ref([]);
const loading = ref(false);
const activeFilter = ref('all');
const filterUserId = ref(null);
const page = ref(1);
const hasMore = ref(true);

const tabs = [
  { id: 'all', name: 'Comunidad', desc: 'Todo lo que ocurre en la red' },
  { id: 'user_list', name: 'Listas', desc: 'Colecciones curadas' },
  { id: 'user_review', name: 'Reviews', desc: 'Análisis detallados' },
  { id: 'user_debate', name: 'Debates', desc: 'Discusión cinéfila' }
];

const contentSections = [
  { type: 'user_list', label: 'Listas Populares' },
  { type: 'user_review', label: 'Reviews Recientes' },
  { type: 'user_debate', label: 'Últimos Debates' }
];

const currentTabName = computed(() => tabs.find(t => t.id === activeFilter.value)?.name);
const currentTabDescription = computed(() => tabs.find(t => t.id === activeFilter.value)?.desc);

const fetchEntries = async (loadMore = false) => {
  if (loading.value) return;
  loading.value = true;
  if (!loadMore) { page.value = 1; entries.value = []; }

  try {
    const params = { 
        page: page.value, 
        type: activeFilter.value !== 'all' ? activeFilter.value : null,
        user_id: filterUserId.value 
    };
    const { data } = await api.get('/user_entries/feed', { params });
    entries.value = [...entries.value, ...data.data.data];
    hasMore.value = data.data.next_page_url !== null;
    if (hasMore.value) page.value++;
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

const getEntriesByType = (type) => entries.value.filter(e => e.type === type);

const setTab = (id) => { 
  activeFilter.value = id; 
  filterUserId.value = null; 
  fetchEntries(); 
};

const filterByMe = (type) => {
  activeFilter.value = type;
  filterUserId.value = auth.user.id;
  fetchEntries();
};

const goToEntry = (entry) => router.push(`/user_entries/${entry.id}/show`);

onMounted(() => fetchEntries());
</script>

<style scoped>
.brand-scroll::-webkit-scrollbar { height: 3px; }
.brand-scroll::-webkit-scrollbar-thumb { background: #10b981; }
.rtl-container { direction: rtl; }
.ltr-content { direction: ltr; }

/* POSTER SOLAPADO */
.poster-list-overlapped { display: flex; height: 150px; position: relative; }
.poster-item { position: relative; width: 100px; height: 150px; margin-left: -75px; transition: transform 0.4s ease; }
.poster-item:first-child { margin-left: 0; }
.poster-img { width: 100px; height: 150px; object-fit: cover; border: 1.5px solid #0f1113; border-radius: 6px; box-shadow: 10px 0 30px rgba(0,0,0,0.5); }
.group:hover .poster-item { transform: translateY(-8px) rotate(-1deg); }

.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>