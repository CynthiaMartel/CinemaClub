<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()

const error = ref(null)
const user_profiles = ref(null) 
const userData_fromFilmsActions = ref(null)
const isLoading = ref(true)

// Variables para contenido generado y guardado
const userDebates = ref([])
const userLists = ref([])
const savedLists = ref([])
const userReviews = ref([])


const contentSections = ref([
  { title: 'Debates Creados', btnLabel: 'DEBATE', type: 'user_debate' },
  { title: 'Listas Creadas', btnLabel: 'LISTA', type: 'user_list' },
  { title: 'Reseñas', btnLabel: 'RESEÑA', type: 'user_review' }
])

// 1. Cargar lo que el usuario HA CREADO 


const fetchUserEntries = async () => {
  const id = route.params.id
  try{
    const [listsResponse, debatesResponse, reviewsResponse] = await Promise.all([
    api.get(`/user_profiles/${id}/lists`),
    api.get(`/user_profiles/${id}/debates`),
    api.get(`/user_profiles/${id}/reviews`)
  ])
  
  userLists.value = listsResponse.data.data
  userDebates.value = debatesResponse.data.data
  userReviews.value = reviewsResponse.data.data
  }catch (err){
    console.error("Error cargando contenido de usuari:", err)
    }
}

// 2. Cargar lo que el usuario HA GUARDADO (Colección)
const fetchSavedLists = async () => {
  try {
    const userId = route.params.id
    const { data } = await api.get(`/user_profiles/${userId}/saved-lists`)
    savedLists.value = data.data || []
  } catch (err) {
    console.error("Error cargando guardadas:", err)
  }
}

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
    await Promise.all([
      fetchProfile(), 
      fetchUserStats(), 
      fetchUserEntries(), 
      fetchSavedLists()
    ])
  } catch (err) {
    error.value = "No se pudo cargar el perfil"
  } finally {
    isLoading.value = false
  }
}

const getSectionData = (type) => {
  if (type === 'user_debate') return userDebates.value
  if (type === 'user_list') return userLists.value
  if (type === 'user_review') return userReviews.value 
  return []
}

watch(() => route.params.id, (newId) => {
  if (newId) loadAll()
}, { immediate: true })

onMounted(loadAll)
</script>

<template>
  <div class="min-h-screen text-slate-100 font-sans bg-[#0f1113] overflow-x-hidden">
    
    <div v-if="isLoading" class="flex flex-col items-center justify-center h-screen gap-4">
      <div class="w-12 h-12 border-4 border-slate-800 border-t-brand rounded-full animate-spin"></div>
      <p class="text-slate-400 text-sm uppercase tracking-widest">Cargando perfil...</p>
    </div>

    <div v-else-if="user_profiles" class="max-w-7xl mx-auto px-4 sm:px-6 md:px-16 py-8 md:py-12">
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12">
        
        <div class="lg:col-span-8 flex flex-col gap-12">
          
          <header class="flex flex-col md:flex-row items-center md:items-end gap-6 mb-4">
            <img 
              :src="user_profiles.avatar ? `/storage/${user_profiles.avatar}` : '/default-avatar.webp'" 
              class="w-28 h-28 md:w-36 md:h-36 rounded-full object-cover border-4 border-slate-800 shadow-2xl"
            />
            <div class="text-center md:text-left">
              <h1 class="text-4xl md:text-5xl font-black text-white uppercase italic leading-none">{{ user_profiles.user.name }}</h1>
              <p class="text-slate-400 font-medium uppercase text-[10px] md:text-xs mt-3 tracking-[0.3em]">{{ user_profiles.location || 'Cinéfilo' }}</p>
            </div>
          </header>

          <section v-if="userData_fromFilmsActions" class="grid grid-cols-2 md:grid-cols-4 gap-4 border-y border-slate-800 py-6">
            <div v-for="(stat, label) in { films_seen: 'Películas', films_rated: 'Ratings', films_seen_this_year: 'Este año' }" :key="label" class="text-center border-r border-slate-800/30 last:border-0 md:last:border-r">
              <p class="text-2xl md:text-3xl font-black text-white">{{ userData_fromFilmsActions.stats[label] }}</p>
              <p class="text-[9px] md:text-[10px] uppercase tracking-widest font-bold text-slate-500">{{ stat }}</p>
            </div>
            <div class="text-center">
              <p class="text-2xl md:text-3xl font-black text-white">{{ userLists.length }}</p>
              <p class="text-[9px] md:text-[10px] uppercase tracking-widest font-bold text-slate-500">Listas</p>
            </div>
          </section>

          <section v-for="section in contentSections" :key="section.title" class="flex flex-col">
            <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-2">
              <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">{{ section.title }}</h2>
              <button class="text-brand text-[10px] font-bold uppercase tracking-widest">+ CREAR</button>
            </div>

            <div v-if="getSectionData(section.type).length > 0" class="brand-scroll flex flex-row-reverse gap-8 overflow-x-auto pb-8 pt-4 rtl-container">
              
              <div 
                v-for="item in getSectionData(section.type)" 
                :key="item.id" 
                @click="router.push(`/entry/${item.type}/${item.id}`)"
                class="flex-shrink-0 group cursor-pointer ltr-content"
                :class="section.type === 'user_review' ? 'w-[160px] md:w-[200px]' : 'w-auto'"
              >
                
                <div v-if="section.type === 'user_list'" class="w-[180px] md:w-[220px]">
                  <div class="poster-stack-container">
                    <ul class="poster-list-overlapped">
                      <li v-for="(film, idx) in item.films?.slice(0, 5)" :key="idx" class="poster-item" :style="{ zIndex: idx * 10 }">
                        <img :src="film.frame || '/default-poster.webp'" class="poster-img" />
                      </li>
                    </ul>
                  </div>
                  <h3 class="text-[12px] font-black text-white uppercase mt-4 truncate group-hover:text-brand transition-colors">{{ item.title }}</h3>
                  <p class="text-[9px] text-slate-500 font-bold uppercase mt-1 italic">{{ item.films?.length }} FILMS</p>
                </div>

                <div v-else-if="section.type === 'user_review'" class="review-vertical-card flex flex-col h-full bg-slate-900/40 border border-slate-800 rounded-lg overflow-hidden hover:border-brand/50 transition-all">
                  <div class="relative aspect-[2/3] w-full overflow-hidden">
                    <img :src="item.films?.[0]?.frame || '/default-poster.webp'" class="w-full h-full object-cover opacity-50" />
                    <div class="absolute top-2 left-2 bg-black/60 px-2 py-1 rounded text-[8px] font-bold text-slate-200">
                      {{ new Date(item.created_at).toLocaleDateString() }}
                    </div>
                  </div>
                  <div class="p-3 flex flex-col gap-1.5">
                    <h3 class="text-[11px] font-black text-brand uppercase leading-tight line-clamp-2">
                      {{ item.title }}
                    </h3>
                    <p class="text-[9px] font-bold text-slate-400 uppercase truncate">
                      {{ item.films?.[0]?.title }}
                    </p>
                    <p class="text-[9px] text-slate-500 line-clamp-2 italic mt-1">"{{ item.content }}"</p>
                  </div>
                </div>

                <div v-else class="flex flex-col">
                  <div class="relative w-36 md:w-44 aspect-video bg-slate-900 border border-slate-800 rounded-lg overflow-hidden shadow-lg">
                    <img :src="item.films?.[0]?.frame || '/default-debate.webp'" class="w-full h-full object-cover opacity-40 group-hover:scale-110 transition-transform duration-700" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                  </div>
                  <h3 class="text-[10px] font-black text-white uppercase mt-3 truncate w-32 md:w-40 group-hover:text-brand transition-colors px-1">{{ item.title }}</h3>
                </div>

              </div>
            </div>
            <div v-else class="py-10 border border-dashed border-slate-800 rounded text-center opacity-40 text-[9px] uppercase tracking-widest italic">Nada por aquí</div>
          </section>

          <section class="mt-4">
            <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-2">
              <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-yellow-600 italic">Listas Guardadas</h2>
            </div>

            <div v-if="savedLists.length > 0" class="brand-scroll flex flex-row-reverse gap-10 overflow-x-auto pb-10 pt-4 rtl-container">
              <div 
                v-for="list in savedLists" 
                :key="list.id"
                @click="router.push(`/user_entries/${list.id}`)"
                class="flex-shrink-0 group cursor-pointer ltr-content w-[180px] md:w-[220px]"
              >
                <div class="poster-stack-container mb-4">
                  <ul class="poster-list-overlapped">
                    <li v-for="(film, idx) in list.films?.slice(0, 5)" :key="idx" class="poster-item" :style="{ zIndex: idx * 10 }">
                      <img :src="film.frame || '/default-poster.webp'" class="poster-img" />
                    </li>
                  </ul>
                </div>
                
                <h3 class="font-black text-[12px] text-slate-100 uppercase truncate group-hover:text-yellow-500 transition-colors">{{ list.title }}</h3>
                <p class="text-[9px] text-slate-500 font-bold uppercase mt-1">De: {{ list.user?.name }}</p>

                <div class="flex gap-1.5 mt-3 border-t border-slate-800 pt-3 opacity-60 group-hover:opacity-100 transition-opacity">
                  <div v-for="(film, fIdx) in list.films?.slice(0, 4)" :key="fIdx" class="w-8 h-12 md:w-10 md:h-14 rounded bg-slate-800 overflow-hidden border border-slate-700">
                    <img :src="film.frame" class="w-full h-full object-cover" />
                  </div>
                  <div v-if="list.films?.length > 4" class="w-8 h-12 md:w-10 md:h-14 bg-slate-900 border border-slate-800 flex items-center justify-center text-[8px] font-bold text-slate-600">
                    +{{ list.films.length - 4 }}
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="py-10 border border-dashed border-slate-800 rounded text-center opacity-40">
              <p class="text-slate-500 text-[9px] uppercase tracking-widest italic">Aún no has guardado ninguna lista.</p>
            </div>
          </section>

        </div>

        <aside class="lg:col-span-4 flex flex-col gap-10">
          <section class="bg-slate-900/20 p-6 rounded-2xl border border-slate-800/50">
            <h2 class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-500 mb-6 text-center italic">Imprescindibles</h2>
            <div class="grid grid-cols-3 gap-2">
               <div v-for="f in 6" :key="f" class="aspect-[2/3] bg-slate-800 rounded-md overflow-hidden border border-slate-700 hover:border-brand/50 transition-colors">
                  <img src="https://images.unsplash.com/photo-1485846234645-a62644f84728?q=80&w=300" class="w-full h-full object-cover" />
               </div>
            </div>
          </section>
        </aside>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* SCROLLBAR BRAND */
.brand-scroll::-webkit-scrollbar { height: 5px; }
.brand-scroll::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.02); border-radius: 10px; }
.brand-scroll::-webkit-scrollbar-thumb { 
  background: #10b981; 
  border-radius: 10px; 
}


/* LÓGICA RTL */
.rtl-container { direction: rtl; }
.ltr-content { direction: ltr; }

/* POSTER SOLAPADO */
.poster-list-overlapped {
  display: flex;
  list-style: none;
  padding: 0;
  margin: 0;
  height: 150px;
  position: relative;
}

.poster-item {
  position: relative;
  width: 100px;  
  height: 150px; 
  margin-left: -75px; /* Deja ver solo el 25% */
  transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.poster-item:first-child { margin-left: 0; }

.poster-img {
  width: 100px;
  height: 150px;
  object-fit: cover;
  border: 1.5px solid #0f1113;
  border-radius: 4px;
  box-shadow: 4px 0 12px rgba(0,0,0,0.6);
}

.group:hover .poster-item {
  transform: translateY(-8px) rotate(-1deg);
}

/* RESPONSIVE */
@media (max-width: 768px) {
  .poster-list-overlapped { height: 120px; }
  .poster-item { width: 80px; height: 120px; margin-left: -60px; }
  .poster-img { width: 80px; height: 120px; }
  
  /* Ajuste para reseñas en móvil */
  .review-vertical-card { width: 150px; }
}
</style>