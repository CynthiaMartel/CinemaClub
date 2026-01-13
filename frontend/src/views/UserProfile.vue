<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'


const route = useRoute()

const error = ref(null)
const user_profiles = ref(null) 
const userData_fromFilmsActions = ref(null)
const isLoading = ref(null)

///** */
const contentSections = ref([
  { title: 'Proyectos', btnLabel: 'PROYECTO' },
  { title: 'Colecciones', btnLabel: 'COLECCIÓN' }
])/////

const fetchUserStats = async () => {
  const userId = route.params.id
  const { data } = await api.get(`/user_films/stats/${userId}`)
  userData_fromFilmsActions.value = data.user
}

const fetchProfile = async () => {
  const userId = route.params.id
  const { data } = await api.get(`/user_profiles/show/${userId}`)
  user_profiles.value = data.data
}


const loadAll = async () => {
  isLoading.value = true
  error.value = null
  try {
    await Promise.all([fetchProfile(), fetchUserStats()])
  } catch (err) {
    console.error(err)
    error.value = "No se pudo cargar el perfil"
  } finally {
    isLoading.value = false
  }
}

watch(
  () => route.params.id,
  (newId) => {
    if (!newId) return
    loadAll()
  },
  { immediate: true }
)




onMounted(loadAll)

</script>

<template>
  <div class="min-h-screen text-slate-100 font-sans bg-[#0f1113]">
    <div v-if="isLoading" class="flex flex-col items-center justify-center h-screen gap-4">
      <div class="w-12 h-12 border-4 border-emerald-500/20 border-t-emerald-500 rounded-full animate-spin"></div>
      <p class="text-slate-400">Cargando perfil de usuario...</p>
    </div>

    <div v-else-if="user_profiles" class="max-w-7xl mx-auto px-4 md:px-16 py-12">
      <div class="grid grid-cols-1 md:grid-cols-12 gap-12">
        
        <div class="md:col-span-8 flex flex-col gap-10">
          
          <header class="flex flex-col md:flex-row items-center md:items-end gap-6">
            <div class="relative group">
              <img 
                :src="user_profiles.avatar ? `/storage/${user_profiles.avatar}` : '/default-avatar.webp'" 
                class="w-32 h-32 rounded-full object-cover border-4 border-slate-800 shadow-2xl"
              />
              <button class="absolute bottom-0 right-0 bg-emerald-500 p-2 rounded-full shadow-lg hover:bg-emerald-400 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path d="M12 4v16m8-8H4"/></svg>
              </button>
            </div>
            <div class="flex flex-col text-center md:text-left">
              <h1 class="text-4xl font-black tracking-tight text-white">{{ user_profiles.user.name }}</h1>
              <p class="text-slate-400 font-medium">{{ user_profiles.location || 'Amante del cine en comunidad' }}</p>
            </div>
          </header>

          <section v-if="userData_fromFilmsActions" class="grid grid-cols-2 md:grid-cols-4 gap-4">

          <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-2xl text-center">
            <p class="text-2xl font-black text-emerald-400">{{ userData_fromFilmsActions.stats.films_seen }}</p>
            <p class="text-[10px] uppercase tracking-widest font-bold text-slate-500">Películas</p>
          </div>

          <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-2xl text-center">
            <p class="text-2xl font-black text-emerald-400">{{ userData_fromFilmsActions.stats.films_rated }}</p>
            <p class="text-[10px] uppercase tracking-widest font-bold text-slate-500">Ratings</p>
          </div>

          <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-2xl text-center">
            <p class="text-2xl font-black text-emerald-400">{{ userData_fromFilmsActions.stats.films_seen_this_year }}</p>
            <p class="text-[10px] uppercase tracking-widest font-bold text-slate-500">Este año</p>
          </div>

          <div class="bg-slate-900/40 border border-slate-800 p-4 rounded-2xl text-center">
            <p class="text-2xl font-black text-emerald-400">{{ userData_fromFilmsActions.stats.lists_created }}</p>
            <p class="text-[10px] uppercase tracking-widest font-bold text-slate-500">Listas</p>
          </div>

        </section>

          <section>
            <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-2">
              <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">La Bitácora</h2>
              <span class="text-[10px] text-emerald-500 cursor-pointer hover:underline">Ver todo</span>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
              <div v-for="i in 4" :key="i" class="group cursor-pointer">
                <div class="relative aspect-video overflow-hidden rounded-lg border border-slate-800">
                  <img src="https://images.unsplash.com/photo-1536440136628-849c177e76a1?q=80&w=500" class="object-cover w-full h-full group-hover:scale-110 transition-transform duration-500" />
                  <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-2">
                    <div class="flex gap-0.5">
                       <svg v-for="s in 5" :key="s" class="w-2 h-2 fill-yellow-500" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                  </div>
                </div>
                <p class="text-[11px] mt-2 font-bold text-slate-300 truncate">Blade Runner 2049</p>
              </div>
            </div>
          </section>

          <section v-for="section in contentSections" :key="section.title">
             <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-2">
              <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">{{ section.title }}</h2>
              <button class="bg-slate-800 hover:bg-slate-700 text-[9px] px-3 py-1 rounded-full transition-colors flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M12 4v16m8-8H4"/></svg>
                CREAR {{ section.btnLabel }}
              </button>
            </div>
            <div class="flex gap-4 overflow-x-auto pb-4 scrollbar-hide">
              <div v-for="n in 3" :key="n" class="min-w-[200px] bg-slate-900/60 border border-slate-800 p-4 rounded-xl">
                 <div class="h-24 bg-slate-800 rounded-lg mb-3 animate-pulse"></div>
                 <div class="h-3 w-3/4 bg-slate-700 rounded mb-2"></div>
                 <div class="h-2 w-1/2 bg-slate-800 rounded"></div>
              </div>
            </div>
          </section>

        </div>

        <aside class="md:col-span-4 flex flex-col gap-10">
          
          <section class="bg-slate-900/20 p-6 rounded-3xl border border-slate-800/50">
            <h2 class="text-xs font-bold uppercase tracking-[0.2em] text-slate-500 mb-6 text-center">Cinco Imprescindibles</h2>
            
            <div class="grid grid-cols-3 gap-2 mb-2">
              <div v-for="f in 3" :key="f" class="aspect-[2/3] bg-slate-800 rounded-md overflow-hidden border border-slate-700 hover:border-emerald-500/50 transition-colors cursor-pointer">
                <img src="https://images.unsplash.com/photo-1485846234645-a62644f84728?q=80&w=300" class="w-full h-full object-cover" />
              </div>
            </div>
            
            <div class="flex gap-2">
              <div v-for="f in 2" :key="f" class="flex-1 aspect-[2/3] bg-slate-800 rounded-md overflow-hidden border border-slate-700 hover:border-emerald-500/50 transition-colors cursor-pointer">
                <img src="https://images.unsplash.com/photo-1440404653325-ab127d49abc1?q=80&w=300" class="w-full h-full object-cover" />
              </div>
              <button class="w-10 flex items-center justify-center bg-slate-800 hover:bg-slate-700 rounded-md border border-slate-700 text-slate-500 hover:text-emerald-400 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              </button>
            </div>
          </section>

          <section class="flex flex-col gap-8 px-2">
            <div>
              <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-4 flex justify-between">
                Siguiendo <span>{{ user_profiles.followings_count }}</span>
              </h3>
              <div class="flex flex-wrap gap-2">
                <div v-for="u in 6" :key="u" class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 overflow-hidden hover:scale-110 transition-transform cursor-pointer">
                  <img :src="`https://i.pravatar.cc/150?u=${u}`" />
                </div>
              </div>
            </div>

            <div>
              <h3 class="text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-4 flex justify-between">
                Seguidores <span>{{ user_profiles.followers_count }}</span>
              </h3>
              <div class="flex flex-wrap gap-2">
                <div v-for="u in 6" :key="u" class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 overflow-hidden hover:scale-110 transition-transform cursor-pointer">
                  <img :src="`https://i.pravatar.cc/150?u=${u+10}`" />
                </div>
              </div>
            </div>
          </section>

        </aside>
      </div>
    </div>
  </div>
</template>



<style scoped>
/* Para ocultar scrollbar en las listas horizontales */
.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>