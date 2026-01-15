<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/stores/auth'
import { useUserFilmActionsStore } from '@/stores/user_film_actions'
import api from '@/services/api'

// Componentes
import CommentSection from '@/components/CommentSection.vue'
import LoginModal from '@/components/LoginModal.vue'
import RatingIt from '@/components/RatingIt.vue'
import PersonModal from '@/components/CastCrewModal.vue'
import FilmDetailsModal from '@/components/FilmDetailsModal.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const userActionsStore = useUserFilmActionsStore()
const { userVote, isSavingRate } = storeToRefs(userActionsStore)

// Variables de estado
const film = ref(null)
const isLoading = ref(true)
const error = ref(null)
const isLoginOpen = ref(false)
const isDetailsModalOpen = ref(false)
const isCastCrewModalOpen = ref(false)
const isListModalOpen = ref(false)
const selectedActorId = ref(null)

// Navegación
const goCreateEntry = () => {
  if (auth.user?.id) {
    router.push({ name: 'create-entry', params: { id: auth.user.id } })
  }
}

const openLogin = () => { isLoginOpen.value = true }

const openPerson = (id) => {
  selectedActorId.value = id
  isCastCrewModalOpen.value = true
}

// Computados (Layout y Datos)
const directors = computed(() => {
  if (!film.value?.cast) return []
  return film.value.cast.filter(person => person.pivot?.role === 'Director')
})

const actors = computed(() => {
  if (!film.value?.cast) return []
  return film.value.cast
    .filter(person => person.pivot?.role === 'Actor')
    .sort((a, b) => (a.pivot?.credit_order ?? 0) - (b.pivot?.credit_order ?? 0))
    .slice(0, 12)
})

const filmYear = computed(() => {
  if (!film.value?.release_date) return ''
  return new Date(film.value.release_date).getFullYear()
})

const filmDuration = computed(() => {
  if (!film.value?.duration || film.value.duration <= 0) return null
  const h = Math.floor(film.value.duration / 60)
  const m = film.value.duration % 60
  return h > 0 ? `${h}h ${m}m` : `${m}m`
})

const formattedTMDB = computed(() => {
  return film.value?.vote_average ? Number(film.value.vote_average).toFixed(1) : '—'
})

const hasExtraDetails = computed(() => {
  return (film.value?.awards?.length > 0) || 
         (film.value?.nominations?.length > 0) || 
         (film.value?.alternative_titles?.length > 0);
})

// Carga de datos
const fetchFilm = async () => {
  isLoading.value = true
  error.value = null
  try {
    const id = route.params.id
    const { data } = await api.get(`/films/${id}`)
    film.value = data
    await fetchUserFilmActions()
  } catch (e) {
    error.value = 'No se pudo cargar la información de la película.'
  } finally {
    isLoading.value = false
  }
}

const fetchUserFilmActions = async () => {
  if (!film.value || !auth.isAuthenticated) return
  try {
    const id = route.params.id
    const response = await api.get(`/films/show-user-action/${id}`)
    if (response.data?.success) {
      film.value.user_action = response.data.data
      userVote.value = response.data.data.rating || null
    }
  } catch (e) { console.error("Error actions:", e) }
}

// Watchers
watch(() => auth.isAuthenticated, async (isLoged) => {
  if (isLoged) await fetchFilm()
  else {
    userVote.value = 0
    if (film.value) film.value.user_action = null
  }
})

watch(() => route.params.id, (newId) => {
  if (newId) fetchFilm()
})

onMounted(fetchFilm)
</script>

<template>
  <div class="min-h-screen text-slate-100 font-sans bg-[#17191c]">

    <div v-if="isLoading" class="flex flex-col items-center justify-center h-screen gap-4">
      <div class="w-12 h-12 border-4 border-slate-800 border-t-[#BE2B0C] rounded-full animate-spin"></div>
      <p class="text-slate-500 font-black uppercase tracking-widest text-[10px]">Consultando archivos de CinemaClub...</p>
    </div>

    <div v-else-if="error" class="flex items-center justify-center h-screen">
      <div class="bg-red-500/10 border border-[#BE2B0C]/50 p-6 rounded-2xl text-center">
        <p class="text-[#BE2B0C] font-bold">{{ error }}</p>
        <button @click="fetchFilm" class="mt-4 text-slate-300 underline cursor-pointer uppercase text-[10px] font-black tracking-widest">Reintentar</button>
      </div>
    </div>

    <div v-else-if="film" class="flex flex-col">
      <header
        class="relative h-64 md:h-[450px] flex items-end px-6 md:px-16 bg-cover bg-center"
        :style="{ backgroundImage: `url(${film.backdrop || film.frame || ''})` }"
      >
        <div class="absolute inset-0 bg-gradient-to-t from-[#17191c] via-[#17191c]/40 to-transparent" />
      </header> 

      <main class="max-w-7xl mx-auto px-4 md:px-16 pb-12 relative -mt-32 z-10">
        <section class="grid gap-10 md:grid-cols-12 items-start">

          <aside class="md:col-span-4 lg:col-span-3 sticky top-6">
            <div class="flex flex-col gap-4 max-w-[240px] mx-auto md:mx-0">
              
              <div class="flex flex-wrap items-center gap-1.5 mb-2">
                <div v-if="filmYear" class="text-[9px] bg-slate-900/80 text-slate-300 px-2 py-1 rounded border border-slate-700 uppercase font-black tracking-wider">{{ filmYear }}</div>
                <div v-if="filmDuration" class="text-[9px] bg-slate-900/80 text-slate-300 px-2 py-1 rounded border border-slate-700 uppercase font-black tracking-wider">{{ filmDuration }}</div>
              </div>

              <div class="relative group overflow-hidden rounded-xl shadow-2xl border border-slate-700/50 bg-slate-900">
                <img v-if="film.frame" :src="film.frame" class="w-full h-auto object-cover transition-transform duration-700 group-hover:scale-110" />
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
              </div>

              <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl shadow-xl backdrop-blur-md">
                <h3 class="text-[9px] font-black text-slate-500 uppercase tracking-[3px] text-center border-b border-slate-800/50 pb-3 mb-3">Rating Club</h3>
                <div class="text-center">
                  <p class="text-[#BE2B0C] font-black text-3xl tracking-tighter">{{ film.globalRate > 0 ? Number(film.globalRate).toFixed(1) : '—' }}</p>
                </div>
              </div>

              <div class="bg-slate-900/60 border border-slate-800 p-4 rounded-2xl shadow-xl backdrop-blur-md relative group">
                <RatingIt v-if="auth.isAuthenticated || true" :filmId="film.idFilm" :filmRef="film" />
                
                <div class="flex items-center justify-around mt-4 pt-4 border-t border-slate-800/50">
                  <button @click="userActionsStore.toggleFavorite(film.idFilm, film)" :class="film.user_action?.is_favorite ? 'text-[#BE2B0C]' : 'text-slate-500 hover:text-[#BE2B0C]'" class="transition-all duration-300 cursor-pointer p-1 transform hover:scale-125">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" /></svg>
                  </button>
                  <button @click="userActionsStore.toggleWatched(film.idFilm, film)" :class="film.user_action?.watched ? 'text-yellow-600' : 'text-slate-500 hover:text-yellow-600'" class="transition-all duration-300 cursor-pointer p-1 transform hover:scale-125">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                  </button>
                  <button @click="userActionsStore.toggleWatchLater(film.idFilm, film)" :class="film.user_action?.watch_later ? 'text-emerald-400' : 'text-slate-500 hover:text-emerald-400'" class="transition-all duration-300 cursor-pointer p-1 transform hover:scale-125">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>
                  </button>
                  <button @click="isListModalOpen = true" class="text-slate-500 hover:text-yellow-500 transition-all duration-300 cursor-pointer p-1 transform hover:scale-125">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                  </button>
                </div>

                <div v-if="!auth.isAuthenticated" @click="openLogin" class="absolute inset-0 bg-slate-950/90 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-xl cursor-pointer z-20">
                  <p class="text-[9px] font-black text-[#BE2B0C] uppercase tracking-[2px] text-center px-4">Login para interactuar</p>
                </div>
                <p v-if="isSavingRate" class="text-[8px] text-orange-500 text-center animate-pulse mt-1 font-black uppercase tracking-tighter">Guardando...</p>
              </div>
            </div>
          </aside>

          <div class="md:col-span-8 lg:col-span-9 flex flex-col gap-10">
            <div class="space-y-6">
              <div class="flex flex-col gap-y-4 border-b border-slate-800 pb-8">
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white tracking-tighter leading-[1.1] max-w-4xl drop-shadow-md">
                  {{ film.title }}
                </h1>
                
                <div v-if="directors.length" class="flex items-center gap-3">
                  <span class="text-[10px] font-black text-slate-500 uppercase tracking-[3px] whitespace-nowrap">Dirigida por</span>
                  <div class="flex flex-wrap gap-2">
                    <template v-for="(dir, index) in directors" :key="dir.idPerson">
                      <span @click="openPerson(dir.idPerson)" class="text-sm font-black text-yellow-600 hover:text-yellow-500 transition-colors cursor-pointer uppercase tracking-tight">
                        {{ dir.name }}
                      </span>
                      <span v-if="index < directors.length - 1" class="text-slate-700">/</span>
                    </template>
                  </div>
                </div>
              </div>

              <div class="pt-4 max-w-3xl">
                <p class="text-slate-300 text-xl leading-relaxed font-serif italic border-l-2 border-[#BE2B0C]/30 pl-8">
                  {{ film.overview || 'No hay sinopsis disponible en los archivos.' }}
                </p>
              </div>
            </div>

            <div class="bg-slate-900/40 border border-slate-800 rounded-2xl overflow-hidden shadow-xl backdrop-blur-sm">
              <div class="bg-slate-900/50 px-6 py-4 border-b border-slate-800 flex justify-between items-center">
                <h3 class="text-[10px] font-black text-yellow-600 uppercase tracking-[3px]">Especificaciones</h3>
                <button v-if="hasExtraDetails" @click="isDetailsModalOpen = true" class="text-[9px] font-black bg-slate-800 hover:bg-slate-700 text-slate-300 px-4 py-1.5 rounded-full transition-all uppercase tracking-widest flex items-center gap-2 border border-slate-700">
                  <span>Detalles Pro</span>
                </button>
              </div>
              <div class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div v-if="film.original_title" class="flex flex-col">
                    <span class="text-[9px] text-slate-500 font-black uppercase tracking-widest mb-1">Título original</span>
                    <span class="text-sm text-slate-200 font-medium italic font-serif">{{ film.original_title }}</span>
                  </div>
                  <div class="flex flex-col">
                    <span class="text-[9px] text-slate-500 font-black uppercase tracking-widest mb-1">TMDB Score</span>
                    <span class="text-sm text-yellow-500 font-black">{{ formattedTMDB }}</span>
                  </div>
                </div>

                <div v-if="actors.length" class="flex flex-col pt-4 border-t border-slate-800/60">
                  <span class="text-[9px] text-slate-500 font-black uppercase tracking-widest mb-4">Cast / Reparto</span>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-2 gap-x-6">
                    <div v-for="actor in actors" :key="actor.idPerson" class="text-xs flex items-baseline gap-2 group">
                      <span @click="openPerson(actor.idPerson)" class="text-slate-200 group-hover:text-yellow-600 transition-colors cursor-pointer font-bold">{{ actor.name }}</span>
                      <span v-if="actor.pivot?.character_name" class="text-slate-600 italic text-[10px] truncate font-serif">{{ actor.pivot.character_name }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-10">
              <CommentSection 
                type="film" 
                :entry-id="film.idFilm" 
                :is-authenticated="auth.isAuthenticated" 
                :current-user-id="auth.user?.id"
                accent-class="bg-yellow-700"
              />
            </div>

          </div>
        </section>
      </main>
    </div>
  </div>

  <div v-if="isListModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-md" @click="isListModalOpen = false"></div>
    <div class="relative bg-slate-900 border border-slate-800 w-full max-w-md rounded-3xl p-8 shadow-2xl">
        <h3 class="text-xl font-black text-white uppercase tracking-tighter mb-8">Archivar en lista</h3>
        <button @click="goCreateEntry" class="w-full py-4 border border-dashed border-slate-700 rounded-xl text-slate-500 text-[10px] hover:border-yellow-600 hover:text-yellow-600 transition-all font-black uppercase tracking-widest">
          + Nueva Colección
        </button>
    </div>
  </div>

  <FilmDetailsModal v-model="isDetailsModalOpen" :film="film" @openPerson="openPerson" />
  <LoginModal v-model="isLoginOpen" />
  <PersonModal v-model="isCastCrewModalOpen" :personId="selectedActorId" />
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #BE2B0C; }
</style>


