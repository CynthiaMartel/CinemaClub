<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import LoginModal from '@/components/LoginModal.vue'
import { storeToRefs } from 'pinia';

import { useUserFilmActionsStore } from '@/stores/user_film_actions'
import RatingIt from '@/components/RatingIt.vue'
import PersonModal from '@/components/CastCrewModal.vue'
import FilmDetailsModal from '@/components/FilmDetailsModal.vue' 

const route = useRoute()
const auth = useAuthStore()
const userActionsStore = useUserFilmActionsStore()

const isCastCrewModalOpen = ref(false)
const selectedActorId = ref(null)

// Variables base
const film = ref(null)
const comments = ref([])
const isLoading = ref(true)
const error = ref(null)
const newComment = ref('')
const isLoginOpen = ref(false)
const isSending = ref(false)
const deletingId = ref(null)
const isDetailsModalOpen = ref(false)

// Estado para el modal de listas
const isListModalOpen = ref(false)

const openLogin = () => {
  isLoginOpen.value = true
}

const openPerson = (id) => {
  selectedActorId.value = id
  isCastCrewModalOpen.value = true
}

// Variables computadas
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
  const totalMinutes = film.value.duration
  const hours = Math.floor(totalMinutes / 60)
  const minutes = totalMinutes % 60
  return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`
})

const formattedTMDB = computed(() => {
  return film.value?.vote_average ? Number(film.value.vote_average).toFixed(1) : '—'
})

const hasExtraDetails = computed(() => {
  return (film.value?.awards?.length > 0) || 
          (film.value?.nominations?.length > 0) || 
          (film.value?.alternative_titles?.length > 0);
});

// Acciones
// Acciones al cambiar el estado de login
watch(() => auth.isAuthenticated, async (isLoged) => {
    if (isLoged) { 
        // Si se loguea, refrescamos la película para traer sus datos personales
        await fetchFilm(); 
    } else { 
        // Si sale, limpiamos comentarios y las acciones del usuario en la UI
        comments.value = []; 
        userVote.value = 0;
        if (film.value) {
            film.value.user_action = null; 
        }
    }
});

// Funciones fetch para traer los datos que el usuario tenga recogidos en cada film
const fetchFilm = async () => {
  isLoading.value = true
  error.value = null
  try {
    const id = route.params.id
    const { data } = await api.get(`/films/${id}`)
    film.value = data
    await Promise.all([fetchComments(), fetchUserFilmActions()])
  } catch (e) {
    error.value = 'Could not load movie information.'
  } finally {
    isLoading.value = false
  }
}

const fetchComments = async () => {
  try {
    const type = 'film'
    const id = route.params.id
    const response = await api.get(`/comments/${type}/${id}`)
    if (response.data && response.data.data) { comments.value = response.data.data }
  } catch (e) { console.error("Error loading comments:", e) }
}



const fetchUserFilmActions = async () => {
  if (!film.value || !auth.isAuthenticated) return;

  try {
    const id = route.params.id;
    const response = await api.get(`/films/show-user-action/${id}`);
    
    if (response.data && response.data.success) {
      // 1. Guardamos TODO el objeto (rating, is_favorite, watched, watch_later) 
      // dentro de la propiedad user_action de la película.
      film.value.user_action = response.data.data;

      // 2. Sincronizamos el rating con la store para que RatingIt sepa qué pintar
      userVote.value = response.data.data.rating || null;
    }
  } catch (e) { console.error("Error loading user actions rating/fav/watched/watch_later:", e); }
}

// Comentarios
const postComment = async () => {
  if (!newComment.value.trim() || isSending.value) return
  isSending.value = true
  try {
    const type = 'film'
    const id = film.value.idFilm
    const response = await api.post(`/comments/${type}/${id}/create`, {
      comment: newComment.value,
      visibility: 'public'
    })
    if (response.data && response.data.data) {
      comments.value.unshift(response.data.data)
      newComment.value = ''
    }
  } catch (e) { alert("Could not post your comment.") } 
  finally { isSending.value = false }
}

const deleteComment = async (commentId) => {
  if (!confirm('¿Seguro que quieres borrar este comentario?')) return
  deletingId.value = commentId
  try {
    await api.delete(`/comments/${commentId}/delete`)
    comments.value = comments.value.filter(c => c.id !== commentId)
  } catch (e) { alert("No se pudo borrar este comentario.") } 
  finally { deletingId.value = null }
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'Recent'
  return new Date(dateStr).toLocaleDateString('en-US', {
    day: '2-digit', month: 'short', year: 'numeric'
  })
}

onMounted(fetchFilm)

// Watcher de seguridad para la store
const { userVote, isSavingRate } = storeToRefs(userActionsStore);

watch(() => film.value, (newFilm) => {
    if (newFilm?.user_action) { 
        userVote.value = newFilm.user_action.rating || 0; 
    }
}, { deep: true });

// Vigilar cambios en el ID de la ruta para recargar los datos
watch(
  () => route.params.id, 
  (newId) => {
    if (newId) {
      fetchFilm(); // Volver a cargar los datos de la nueva película
    }
  }
);
</script>

<template>
  <div class="min-h-screen text-slate-100 font-sans">

    <div v-if="isLoading" class="flex flex-col items-center justify-center h-screen gap-4">
      <div class="w-12 h-12 border-4 border-emerald-500/20 border-t-red-600 rounded-full animate-spin"></div>
      <p class="text-slate-400">Consulting CinemaClub archives...</p>
    </div>

    <div v-else-if="error" class="flex items-center justify-center h-screen">
      <div class="bg-red-500/10 border border-red-500/50 p-6 rounded-2xl text-center">
        <p class="text-red-400">{{ error }}</p>
        <button @click="fetchFilm" class="mt-4 text-red-300 underline cursor-pointer">Reintentar</button>
      </div>
    </div>

    <div v-else-if="film" class="flex flex-col">
      <header
        class="relative h-64 md:h-[400px] flex items-end px-6 md:px-16 bg-cover bg-center"
        :style="{ backgroundImage: `url(${film.backdrop || film.frame || ''})` }"
      >
        <div class="absolute inset-0 bg-gradient-to-t from-[#17191c] via-[#17191c]/50 to-transparent" />
      </header> 

      <main class="max-w-7xl mx-auto px-4 md:px-16 pb-12 relative -mt-20 z-10">
        
        <section class="grid gap-10 md:grid-cols-12 items-start">

          <aside class="md:col-span-4 lg:col-span-3 sticky top-6">
            <div class="flex flex-col gap-3 max-w-[220px] mx-auto md:mx-0">
              
              <div class="flex flex-wrap items-center gap-1.5">
                <div v-if="filmYear" class="text-[9px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded border border-slate-700 uppercase font-medium">
                  {{ filmYear }}
                </div>
                <div v-if="filmDuration" class="text-[9px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded border border-slate-700 uppercase font-medium">
                  {{ filmDuration }}
                </div>
                <template v-if="film.genre">
                   <span v-for="g in (Array.isArray(film.genre) ? film.genre : film.genre.split(','))" :key="g" 
                    class="text-[9px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded border border-slate-700 uppercase font-medium">
                    {{ g.trim() }}
                  </span>
                </template>
              </div>

              <div class="relative group">
                <img v-if="film.frame" :src="film.frame" class="w-full rounded-lg shadow-2xl border border-slate-800" />
              </div>

              <div class="bg-slate-900/40 border border-slate-800 p-3 rounded-xl shadow-xl backdrop-blur-md">
                <h3 class="text-[9px] font-bold text-slate-500 uppercase tracking-widest text-center border-b border-slate-800 pb-2 mb-2">Rating Club</h3>
                <div class="text-center">
                  <p class="text-brand font-black text-2xl">
                    {{ film.globalRate > 0 ? Number(film.globalRate).toFixed(1) : '—' }}
                  </p>
                </div>
              </div>

              <div class="bg-slate-900/40 border border-slate-800 p-3 rounded-xl shadow-xl backdrop-blur-md relative group">
                
                <RatingIt v-if="auth.isAuthenticated || true" :filmId="film.idFilm" :filmRef="film" />
                
                <div class="flex items-center justify-around mt-3 pt-3 border-t border-slate-800/50">
  
                <button 
                  @click="userActionsStore.toggleFavorite(film.idFilm, film)"
                  :class="film.user_action?.is_favorite ? 'text-red-500' : 'text-slate-500 hover:text-red-400'"
                  :title="film.user_action?.is_favorite ? 'Like marcado' : 'Añadir a favoritos'"
                  class="transition-all duration-300 cursor-pointer p-1 transform hover:scale-110"
                >
                  <svg 
                    xmlns="http://www.w3.org/2000/svg" 
                    class="h-5 w-5" 
                    viewBox="0 0 24 24" 
                    fill="none" 
                    stroke="currentColor" 
                    stroke-width="2" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                  >
                    <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" />
                  </svg>
                </button>

                <button 
                  @click="userActionsStore.toggleWatched(film.idFilm, film)"
                  :class="film.user_action?.watched ? 'text-yellow-600' : 'text-slate-500 hover:text-yellow-600'"
                  :title="film.user_action?.watched ? 'Visto marcado' : 'Marcar como vista'"
                  class="transition-all duration-300 cursor-pointer p-1 transform hover:scale-110"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg>
                </button>

                <button 
                  @click="userActionsStore.toggleWatchLater(film.idFilm, film)"
                  :class="film.user_action?.watch_later ? 'text-emerald-400' : 'text-slate-500 hover:text-emerald-400'"
                  :title="film.user_action?.watch_later ? 'Ver más tarde marcado' : 'Ver más tarde'"
                  class="transition-all duration-300 cursor-pointer p-1 transform hover:scale-110" 
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10" />
                    <polyline points="12 6 12 12 16 14" />
                  </svg>
                </button>

                <button @click="isListModalOpen = true" class="text-slate-500 hover:text-yellow-500 transition-all duration-300 cursor-pointer p-1 transform hover:scale-110" title="Añadir a lista">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                  </svg>
                </button>
              </div>

                <div v-if="!auth.isAuthenticated" 
                  @click="openLogin"
                  class="absolute inset-0 bg-slate-900/90 backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center rounded-xl cursor-pointer z-20">
                  <p class="text-[10px] font-black text-brand uppercase tracking-widest text-center px-4">
                    Login para interactuar
                  </p>
                </div>

                <p v-if="isSavingRate" class="text-[8px] text-orange-500 text-center animate-pulse mt-1">Guardando...</p>
              </div>
            </div>
          </aside>

          <div class="md:col-span-8 lg:col-span-9 flex flex-col gap-8">
            <div class="space-y-4">
              <div class="flex flex-wrap items-baseline gap-x-6 gap-y-4 border-b border-slate-800 pb-6">
                <h1 class="text-4xl md:text-6xl font-black text-white tracking-tight leading-none">
                  {{ film.title }}
                </h1>
                <div v-if="directors.length" class="flex items-center gap-2">
                  <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest whitespace-nowrap">Dirigida por</span>
                  <div class="flex flex-wrap gap-1">
                    <template v-for="(dir, index) in directors" :key="dir.idPerson">
                      <span @click="openPerson(dir.idPerson)" class="text-sm font-bold text-yellow-600 hover:text-yellow-500 transition-colors cursor-pointer">
                        {{ dir.name }}
                      </span>
                      <span v-if="index < directors.length - 1" class="text-slate-600">, </span>
                    </template>
                  </div>
                </div>
              </div>

              <div class="pt-2">
                <p class="text-slate-300 text-lg leading-relaxed italic">
                  {{ film.overview || 'No hay sinopsis disponible.' }}
                </p>
              </div>
            </div>

            <div class="bg-slate-900/30 border border-slate-800 rounded-2xl overflow-hidden shadow-xl backdrop-blur-sm">
              <div class="bg-slate-900/30 px-5 py-3 border-b border-slate-700 flex justify-between items-center">
                <h3 class="text-sm font-black text-yellow-600 uppercase tracking-widest">Ficha Técnica</h3>
                <button v-if="hasExtraDetails" @click="isDetailsModalOpen = true" class="text-[10px] font-bold bg-slate-700 hover:bg-slate-600 text-slate-200 px-3 py-1 rounded-full transition-colors uppercase tracking-tighter flex items-center gap-1">
                  <span>Ver más detalles</span>
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                  </svg>
                </button>
              </div>
              <div class="p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div v-if="film.original_title" class="flex flex-col">
                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">Título original</span>
                    <span class="text-sm text-slate-200">{{ film.original_title }}</span>
                  </div>
                  <div class="flex flex-col">
                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">TMDB Rating</span>
                    <span class="text-sm text-yellow-500 font-bold">{{ formattedTMDB }} <span class="text-slate-500 text-[10px]">/ 10</span></span>
                  </div>
                </div>
                
                <div v-if="directors.length" class="flex flex-col">
                  <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">Director</span>
                  <div class="flex flex-wrap gap-x-2">
                    <template v-for="(dir, index) in directors" :key="dir.idPerson">
                      <span @click="openPerson(dir.idPerson)" class="text-slate-200 hover:text-yellow-600 underline decoration-slate-500/40 underline-offset-4 transition-all duration-300 cursor-pointer">
                        {{ dir.name }}
                      </span>
                      <span v-if="index < directors.length - 1" class="text-slate-600">|</span>
                    </template>
                  </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div v-if="film.origin_country" class="flex flex-col">
                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">País</span>
                    <span class="text-sm text-slate-200">{{ film.origin_country }}</span>
                  </div>
                  <div v-if="film.original_language" class="flex flex-col">
                    <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">Idioma</span>
                    <span class="text-sm text-slate-200 uppercase">{{ film.original_language }}</span>
                  </div>
                </div>

                <div v-if="actors.length" class="flex flex-col pt-2 border-t border-slate-800">
                  <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter mb-2">Reparto Principal</span>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-1 gap-x-4">
                    <div v-for="actor in actors" :key="actor.idPerson" class="text-xs flex items-baseline gap-2">
                      <span @click="openPerson(actor.idPerson)" class="text-slate-200 hover:text-yellow-600 underline decoration-slate-500/40 underline-offset-4 transition-all duration-300 cursor-pointer">
                        {{ actor.name }}
                      </span>
                      <span v-if="actor.pivot?.character_name" class="text-slate-500 italic text-[10px] truncate">
                        {{ actor.pivot.character_name }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <section class="space-y-8 pt-8 border-t border-slate-800">
              <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                <span class="w-2 h-1 bg-brand rounded-full"></span> Comunidad
              </h2>

              <div class="space-y-4">
                <div v-if="auth.isAuthenticated" class="bg-slate-900/40 p-6 rounded-2xl border border-slate-800 space-y-4">
                  <textarea v-model="newComment" rows="3" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-4 text-slate-200 focus:ring-2 focus:ring-yellow-600 outline-none transition-all" placeholder="¿Qué piensas de la película?"></textarea>
                  <div class="flex justify-end">
                    <button @click="postComment" :disabled="!newComment.trim() || isSending" class="bg-yellow-600 hover:bg-yellow-700 text-white px-8 py-2 rounded-full font-bold transition-all cursor-pointer">
                      Comentar
                    </button>
                  </div>
                </div>
                <div v-else class="bg-slate-900/30 p-8 rounded-2xl border border-dashed border-slate-700 text-center">
                  <p class="text-slate-400">¡Haz <span @click="openLogin" class="text-yellow-600 font-bold cursor-pointer underline">login</span> para comentar!</p>
                </div>
              </div>

              <div class="space-y-6 pt-6 border-t border-slate-800/60">
                <div v-for="comment in comments" :key="comment.id" class="flex gap-4 p-5 rounded-2xl bg-slate-900/20 border border-slate-800/50 hover:bg-slate-900/30 transition-colors">
                  <div class="w-10 h-10 bg-yellow-500/20 rounded-full flex items-center justify-center text-yellow-600 font-bold shrink-0 border border-yellow-500/20">
                    {{ comment.user?.name?.charAt(0).toUpperCase() || 'U' }}
                  </div>
                  <div class="flex-1 space-y-2">
                    <div class="flex items-center justify-between">
                      <h4 class="font-bold text-sm text-slate-200">{{ comment.user?.name }}</h4>
                      <span class="text-[10px] text-slate-500 uppercase font-bold tracking-widest">{{ formatDate(comment.created_at) }}</span>
                    </div>
                    <p class="text-sm text-slate-400 leading-relaxed">"{{ comment.comment }}"</p>
                    
                    <div class="flex justify-end pt-2" v-if="auth.isAuthenticated && (auth.user?.id === comment.user_id || auth.user?.role === 'admin')">
                      <button @click="deleteComment(comment.id)" :disabled="deletingId === comment.id" class="text-[10px] uppercase tracking-tighter font-bold text-red-400/60 hover:text-red-400 transition-colors flex items-center gap-1 cursor-pointer disabled:opacity-50">
                        <span v-if="deletingId === comment.id" class="w-3 h-3 border-2 border-red-400/20 border-t-red-400 rounded-full animate-spin"></span>
                        {{ deletingId === comment.id ? 'Deleting...' : 'Delete comment' }}
                      </button>
                    </div>

                  </div>
                </div> 
              </div>
            </section>
          </div>
        </section>
      </main>
    </div>
  </div>

  <div v-if="isListModalOpen" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/80 backdrop-blur-md" @click="isListModalOpen = false"></div>
    <div class="relative bg-slate-900 border border-slate-800 w-full max-w-md rounded-3xl p-6 shadow-2xl">
       <div class="flex justify-between items-center mb-6">
         <h3 class="text-xl font-black text-white">Añadir a una lista</h3>
         <button @click="isListModalOpen = false" class="text-slate-500 hover:text-white transition-colors">
           <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
             <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
           </svg>
         </button>
       </div>
       <div class="space-y-2 max-h-60 overflow-y-auto mb-6 pr-2">
          <div class="p-4 bg-slate-950/50 rounded-xl border border-slate-800 hover:border-yellow-600/50 cursor-pointer transition-colors text-sm text-slate-400">
            Favoritas de 2024
          </div>
       </div>
       <button class="w-full py-3 border border-dashed border-slate-700 rounded-xl text-slate-400 text-sm hover:border-yellow-600 hover:text-yellow-600 transition-all font-bold">
         + Crear nueva lista
       </button>
    </div>
  </div>

  <FilmDetailsModal v-model="isDetailsModalOpen" :film="film" @openPerson="openPerson" />
  <LoginModal v-model="isLoginOpen" />
  <PersonModal v-model="isCastCrewModalOpen" :personId="selectedActorId" />
</template>

<style scoped>
.sticky {
  position: sticky;
  align-self: flex-start;
}

html {
  scroll-behavior: smooth;
}
</style>

