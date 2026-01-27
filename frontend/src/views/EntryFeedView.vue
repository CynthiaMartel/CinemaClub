<template>
  <div class="min-h-screen text-slate-100 font-sans bg-[#14181c] overflow-x-hidden pb-20">
    
    <header class="max-w-7xl mx-auto px-6 pt-12 pb-8">
      <div class="flex flex-col gap-6">
        
        <div class="text-left">
          <h1 class="text-4xl md:text-5xl font-black text-white uppercase italic tracking-tighter leading-none">
            {{ currentTabName }}
          </h1>
          <p class="text-slate-500 font-medium uppercase text-[10px] md:text-[11px] mt-2 tracking-[0.3em]">
            {{ filterUserId ? 'Mostrando tus creaciones' : currentTabDescription }}
          </p>
        </div>

        <div class="flex flex-wrap items-center justify-start gap-3 md:gap-4">
          <button 
            v-for="tab in tabs" :key="tab.id"
            @click="setTab(tab.id)"
            :class="[
              'px-5 py-2 rounded-full text-[10px] font-black uppercase tracking-widest border transition-all duration-300',
              activeFilter === tab.id 
                ? 'bg-white text-black border-white shadow-[0_0_15px_rgba(255,255,255,0.3)]' 
                : 'bg-transparent text-slate-500 border-slate-800 hover:border-slate-500 hover:text-white'
            ]"
          >
            {{ tab.name }}
          </button>

          <div v-if="activeFilter !== 'all' && auth.isAuthenticated" class="ml-auto relative group">
            <button class="text-slate-400 hover:text-brand text-[10px] font-black uppercase tracking-widest flex items-center gap-2 transition-colors">
              <i class="bi bi-funnel-fill"></i>
              {{ filterUserId ? 'Viendo lo mío' : 'Filtrar' }}
            </button>
            <div class="absolute right-0 top-full mt-2 w-40 bg-[#1c1c1c] border border-slate-800 rounded-xl shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-50 overflow-hidden">
                <button @click="setTab(activeFilter)" class="w-full text-left px-4 py-3 text-[9px] uppercase font-bold text-slate-400 hover:bg-white/5 hover:text-white">Explorar Todo</button>
                <button @click="filterByMe(activeFilter)" class="w-full text-left px-4 py-3 text-[9px] uppercase font-bold text-slate-400 hover:bg-white/5 hover:text-white">Mis Publicaciones</button>
            </div>
          </div>
        </div>

      </div>
    </header>

    <main class="max-w-7xl mx-auto px-6">
      
      <div v-if="loading && entries.length === 0" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-8">
        <div v-for="i in 3" :key="i" class="h-64 bg-slate-900/50 rounded-3xl animate-pulse"></div>
      </div>

      <div v-else-if="activeFilter === 'all'" class="flex flex-col gap-20 mt-4">
        
        <section v-for="section in contentSections" :key="section.type" class="flex flex-col">
          
          <div :class="['flex items-center justify-between mb-6 border-l-4 pl-4', section.colorClass]">
            <h2 class="text-lg md:text-xl font-black uppercase italic tracking-tighter text-white">
              {{ section.label }}
            </h2>
            <button @click="setTab(section.type)" class="text-[9px] font-bold uppercase tracking-widest text-slate-500 hover:text-white transition-colors">
              Ver todos <i class="bi bi-arrow-right ml-1"></i>
            </button>
          </div>

          <div class="brand-scroll flex flex-row-reverse gap-5 overflow-x-auto pb-8 pt-2 rtl-container">
            <div 
              v-for="item in getEntriesByType(section.type)" 
              v-bind:key="item.id" 
              @click="goToEntry(item)"
              class="flex-shrink-0 group cursor-pointer ltr-content transition-transform duration-300 hover:-translate-y-2"
              :class="[
                section.type === 'user_list' ? 'w-[220px]' : 'w-[160px]' 
              ]"
            >
              
              <template v-if="section.type === 'user_list'">
                <div class="poster-stack-container mb-4 pl-2">
                  <ul class="poster-list-overlapped">
                    <li v-for="(film, idx) in item.films?.slice(0, 5)" :key="idx" class="poster-item" :style="{ zIndex: idx * 10 }">
                      <img :src="film.frame" class="poster-img" />
                    </li>
                  </ul>
                </div>
              </template>

              <template v-else-if="section.type === 'user_review'">
                <div class="aspect-[2/3] bg-slate-800 rounded-2xl overflow-hidden mb-3 relative shadow-lg group-hover:shadow-[0_0_20px_rgba(190,43,12,0.3)] transition-all duration-500 border border-slate-700/50">
                  <img :src="item.films?.[0]?.frame" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                  <div class="absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/90 to-transparent"></div>
                  
                  <div class="absolute top-3 right-3 w-8 h-8 rounded-full bg-slate-900/90 border border-slate-700 flex items-center justify-center text-orange-400 shadow-lg">
                    <i class="bi bi-star-fill text-brand text-sm"></i>
                  </div>
                </div>
              </template>

              <template v-else-if="section.type === 'user_debate'">
                <div class="aspect-[2/3] bg-[#0a0a0a] border border-slate-700 rounded-lg overflow-hidden mb-3 relative shadow-md group-hover:border-orange-400 transition-colors">
                  <img :src="item.films?.[0]?.frame" class="w-full h-full object-cover opacity-60 grayscale-[40%] group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-700" />
                  <div class="absolute top-2 left-2 w-8 h-8 rounded-full bg-slate-900/90 border border-slate-700 flex items-center justify-center text-orange-400 shadow-lg">
                    <i class="bi bi-chat-quote-fill text-xs"></i>
                  </div>
                </div>
              </template>

              <template v-else>
                <div class="aspect-video bg-slate-900 border border-slate-800 rounded-xl overflow-hidden mb-4 relative">
                  <img :src="item.films?.[0]?.frame" class="w-full h-full object-cover opacity-50 group-hover:scale-110 transition-all duration-700" />
                </div>
              </template>
              
              <h3 class="font-black text-[11px] text-white uppercase truncate group-hover:text-brand transition-colors leading-tight pl-1">{{ item.title }}</h3>
              <p class="text-[9px] text-slate-500 font-bold uppercase mt-1 pl-1 truncate">{{ item.user.name }}</p>
            </div>
          </div>
        </section>
      </div>

      <div v-else class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-x-6 gap-y-10 mt-8">
        <div v-for="entry in entries" :key="entry.id" @click="goToEntry(entry)" class="group cursor-pointer">
          
          <div v-if="entry.type === 'user_list'" class="poster-stack-container scale-90 origin-left mb-4">
             <ul class="poster-list-overlapped">
                <li v-for="(f, i) in entry.films?.slice(0, 3)" :key="i" class="poster-item" :style="{ zIndex: i*10, width: '80px', height: '120px' }">
                  <img :src="f.frame" class="w-full h-full object-cover rounded border border-black shadow-2xl" />
                </li>
             </ul>
          </div>
          
          <div v-else-if="entry.type === 'user_review'" 
               class="aspect-[2/3] rounded-2xl overflow-hidden mb-3 relative shadow-lg group-hover:shadow-[#BE2B0C]/20 transition-all border border-slate-800 group-hover:border-[#BE2B0C]">
              <img :src="entry.films?.[0]?.frame" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
              <div class="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-black via-black/40 to-transparent opacity-80"></div>
              
              <div class="absolute top-3 right-3 drop-shadow-[0_2px_4px_rgba(0,0,0,0.8)]">
                 <i class="bi bi-star-fill text-yellow-400 text-sm"></i>
              </div>
          </div>

          <div v-else-if="entry.type === 'user_debate'" 
               class="aspect-[2/3] rounded-lg bg-[#050505] border border-slate-800 overflow-hidden mb-3 shadow-md relative group-hover:border-orange-400 transition-colors">
              <img :src="entry.films?.[0]?.frame" class="w-full h-full object-cover opacity-60 group-hover:opacity-90 transition-all duration-700" />
              <div class="absolute top-2 left-2 bg-black/70 p-1.5 rounded-full border border-white/10 text-orange-400">
                 <i class="bi bi-chat-quote-fill text-[10px]"></i>
              </div>
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
  { id: 'all', name: 'Comunidad', desc: 'Feed Global' },
  { id: 'user_debate', name: 'Debates', desc: 'Discusiones' },
  { id: 'user_list', name: 'Listas', desc: 'Colecciones' },
  { id: 'user_review', name: 'Reviews', desc: 'Críticas' },
  
];


const contentSections = [
  { type: 'user_debate', label: 'Últimos Debates', colorClass: 'border-orange-400' },
  { type: 'user_list', label: 'Listas Populares', colorClass: 'border-yellow-600' },
  { type: 'user_review', label: 'Reviews Recientes', colorClass: 'border-[#BE2B0C]' }
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

const goToEntry = (entry) => {
  router.push(`/entry/${entry.type}/${entry.id}`);
};

onMounted(() => fetchEntries());
</script>

<style scoped>
.brand-scroll::-webkit-scrollbar { height: 3px; }
.brand-scroll::-webkit-scrollbar-thumb { background: #10b981; }
.rtl-container { direction: rtl; }
.ltr-content { direction: ltr; }

/* POSTER SOLAPADO (Solo para listas) */
.poster-list-overlapped { display: flex; height: 150px; position: relative; }
.poster-item { position: relative; width: 100px; height: 150px; margin-left: -75px; transition: transform 0.4s ease; }
.poster-item:first-child { margin-left: 0; }
.poster-img { width: 100px; height: 150px; object-fit: cover; border: 1.5px solid #0f1113; border-radius: 6px; box-shadow: 10px 0 30px rgba(0,0,0,0.5); }
.group:hover .poster-item { transform: translateY(-8px) rotate(-1deg); }

.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>