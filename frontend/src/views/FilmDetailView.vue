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
  <div class="min-h-screen text-[#9ab] font-sans bg-[#14181c] selection:bg-[#BE2B0C]/40">

    <div v-if="isLoading" class="flex flex-col items-center justify-center h-screen gap-6">
      <div class="w-14 h-14 border-4 border-slate-800 border-t-[#BE2B0C] rounded-full animate-spin"></div>
      <p class="text-slate-500 font-black uppercase tracking-[0.3em] text-[10px]">CinemaClub is loading...</p>
    </div>

    <div v-else-if="error" class="flex items-center justify-center h-screen px-4">
      <div class="bg-red-950/20 border border-red-900/50 p-8 rounded-3xl text-center max-w-sm backdrop-blur-md">
        <p class="text-red-500 font-bold mb-6 text-sm uppercase tracking-widest">{{ error }}</p>
        <button @click="fetchFilm" class="w-full py-3 bg-red-900/20 hover:bg-red-900/40 text-red-200 rounded-xl transition-all uppercase text-[10px] font-black tracking-widest border border-red-900/30">Reintentar</button>
      </div>
    </div>

    <div v-else-if="film" class="relative">
      
      <header class="absolute top-0 left-0 w-full h-[550px] overflow-hidden z-0">
        <div 
          class="absolute inset-0 bg-cover bg-center transition-transform duration-[4s] scale-105 opacity-50"
          :style="{ backgroundImage: `url(${film.backdrop || film.frame || ''})` }"
        ></div>
        
        <div class="absolute inset-0 bg-gradient-to-t from-[#14181c] via-transparent to-transparent"></div>
        
        <div class="absolute inset-0 hidden md:block bg-gradient-to-r from-[#14181c] via-transparent to-[#14181c]"></div>
        <div class="absolute inset-0 hidden md:block shadow-[inset_0_0_150px_rgba(20,24,28,1)]"></div>
      </header>

      <div class="content-wrap relative z-10 mx-auto max-w-[1200px] px-6 sm:px-12 md:px-24">
        
        <div class="film-page-wrapper pt-[280px] grid grid-cols-1 md:grid-cols-[230px_1fr] gap-x-16 pb-20">
          
          <aside class="flex flex-col gap-6 md:sticky md:top-10 self-start">
            <div class="relative group w-full shadow-[0_0_20px_rgba(0,0,0,0.5)] rounded-lg overflow-hidden border border-white/10 bg-[#1b2228] transition-all duration-500 hover:scale-[1.05] hover:border-white/30 cursor-pointer">
              <img v-if="film.frame" :src="film.frame" class="w-full h-auto object-cover block" />
              <div class="absolute inset-0 ring-1 ring-inset ring-white/20 rounded-lg group-hover:ring-white/40 transition-all"></div>
            </div>

            <div class="actions-panel relative bg-[#1b2228] border border-white/5 rounded-lg p-5 group/actions">
              <div class="text-center w-full mb-4">
                <span class="block text-[9px] text-[#899] font-black uppercase tracking-[0.2em] mb-1">Club Score</span>
                <span class="text-3xl font-black text-white tracking-tighter leading-none">
                  {{ film.globalRate > 0 ? Number(film.globalRate).toFixed(1) : '—' }}
                </span>
              </div>

              <RatingIt :filmId="film.idFilm" :filmRef="film" />

              <div class="grid grid-cols-4 gap-2 mt-5 pt-5 border-t border-white/5 text-[#9ab]">
                <button @click="userActionsStore.toggleFavorite(film.idFilm, film)" :class="film.user_action?.is_favorite ? 'text-[#BE2B0C] bg-[#BE2B0C]/10' : 'hover:text-white'" class="aspect-square flex items-center justify-center rounded transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" /></svg>
                </button>
                <button @click="userActionsStore.toggleWatched(film.idFilm, film)" :class="film.user_action?.watched ? 'text-[#00c020] bg-[#00c020]/10' : 'hover:text-white'" class="aspect-square flex items-center justify-center rounded transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                </button>
                <button @click="userActionsStore.toggleWatchLater(film.idFilm, film)" :class="film.user_action?.watch_later ? 'text-[#34a8c4] bg-[#34a8c4]/10' : 'hover:text-white'" class="aspect-square flex items-center justify-center rounded transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>
                </button>
                <button @click="isListModalOpen = true" class="aspect-square flex items-center justify-center rounded hover:text-white transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                </button>
              </div>

              <div v-if="!auth.isAuthenticated" 
                   @click="openLogin" 
                   class="absolute inset-0 bg-slate-950/90 backdrop-blur-sm opacity-0 group-hover/actions:opacity-100 transition-all duration-300 flex items-center justify-center rounded-lg cursor-pointer p-4 text-center z-20">
                <p class="text-[9px] font-black text-white uppercase tracking-[0.2em]">Login para interactuar</p>
              </div>
            </div>
          </aside>

          <div class="flex flex-col pt-10 md:pt-20 max-w-full md:max-w-[800px]">
            
            <section class="film-header mb-8">
              <h1 class="text-4xl sm:text-5xl font-black text-white tracking-tight leading-tight mb-4 drop-shadow-lg font-serif">
                {{ film.title }}
              </h1>
              
              <div class="flex flex-wrap items-center gap-4 text-sm font-bold">
                <span v-if="filmYear" class="text-slate-300">{{ filmYear }}</span>
                <div v-if="directors.length" class="flex gap-2 text-[#899]">
                  <span class="font-normal">Directed by</span>
                  <template v-for="(dir, index) in directors" :key="dir.idPerson">
                    <span @click="openPerson(dir.idPerson)" class="text-slate-100 hover:text-white border-b border-slate-700 hover:border-slate-100 cursor-pointer">
                      {{ dir.name }}
                    </span>
                    <span v-if="index < directors.length - 1">,</span>
                  </template>
                </div>
              </div>
            </section>

            <section class="synopsis mb-10">
              <p class="text-slate-300 text-lg leading-relaxed italic opacity-90">
                {{ film.overview || 'Sinopsis no disponible.' }}
              </p>
            </section>

            <div class="details-tabs bg-[#1b2228] border border-white/5 rounded-lg overflow-hidden mb-12 shadow-xl">
              <header class="flex justify-between items-center px-6 py-3 border-b border-white/5 bg-white/[0.02]">
                <h3 class="text-[9px] font-black text-yellow-800 uppercase tracking-[0.3em]">Detalles</h3>
                <button @click="isDetailsModalOpen = true" class="text-[9px] font-black text-[#9ab] hover:text-white transition-colors">
                  VER MÁS +
                </button>
              </header>

              <div class="p-6 md:p-8 space-y-10">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                  <div class="space-y-1">
                    <span class="text-[9px] text-[#678] font-black uppercase">Idioma</span>
                    <p class="text-sm text-white uppercase">{{ film.original_language || '—' }}</p>
                  </div>
                  <div class="space-y-1">
                    <span class="text-[9px] text-[#678] font-black uppercase">Duración</span>
                    <p class="text-sm text-white">{{ filmDuration || '—' }}</p>
                  </div>
                  <div class="space-y-1">
                    <span class="text-[9px] text-[#678] font-black uppercase">Género</span>
                    <div class="flex flex-wrap gap-x-2 gap-y-1">
                      <span class="text-[11px] font-bold text-slate-100 bg-white/5 px-2 py-1 rounded hover:bg-[#BE2B0C]/20 hover:text-white cursor-pointer transition-all">{{ film.genre}}</span>
                    </div>
                  </div>
                  
                </div>

                <div v-if="actors.length" class="pt-6 border-t border-white/5">
                  <span class="text-[9px] text-[#678] font-black uppercase tracking-widest block mb-4">Reparto Principal</span>
                  <div class="flex flex-wrap gap-x-2 gap-y-1">
                    <template v-for="(actor, index) in actors" :key="actor.idPerson">
                      <span @click="openPerson(actor.idPerson)" class="text-[11px] font-bold text-slate-100 bg-white/5 px-2 py-1 rounded hover:bg-[#BE2B0C]/20 hover:text-white cursor-pointer transition-all">
                        {{ actor.name }}
                      </span>
                    </template>
                  </div>
                </div>
              </div>
            </div>

            <CommentSection 
              type="film" 
              :entry-id="film.idFilm" 
              :is-authenticated="auth.isAuthenticated" 
              :current-user-id="auth.user?.id"
              accent-class="bg-[#BE2B0C]"
            />

          </div>
        </div>
      </div>
    </div>
  </div>

  <div v-if="isListModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-md" @click="isListModalOpen = false"></div>
    <div class="relative bg-[#1b2228] border border-white/10 w-full max-w-sm rounded-xl p-8 shadow-2xl">
        <h3 class="text-lg font-black text-white uppercase tracking-tighter mb-6 text-center">Añadir a una lista</h3>
        <button @click="goCreateEntry" class="w-full py-4 border-2 border-dashed border-white/10 rounded-lg text-slate-500 text-[10px] hover:border-[#BE2B0C] hover:text-[#BE2B0C] transition-all font-black uppercase tracking-widest">
          + Crear Nueva Lista
        </button>
    </div>
  </div>

  <FilmDetailsModal v-model="isDetailsModalOpen" :film="film" @openPerson="openPerson" />
  <LoginModal v-model="isLoginOpen" />
  <PersonModal v-model="isCastCrewModalOpen" :personId="selectedActorId" />
</template>

<style scoped>
.font-serif {
  font-family: 'Tiempos Headline', Georgia, serif;
}

@media (min-width: 768px) {
  .film-page-wrapper {
    grid-template-columns: 230px 1fr;
  }
}

::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: #14181c; }
::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>