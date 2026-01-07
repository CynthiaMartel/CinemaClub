<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import LoginModal from '@/components/LoginModal.vue'
import { storeToRefs } from 'pinia';
import { useUserFilmActionsStore } from '@/stores/user_film_actions'

const route = useRoute()
const auth = useAuthStore()
const userActionsStore = useUserFilmActionsStore()


// Variables estáticas
const film = ref(null)
const comments = ref([])
const isLoading = ref(true)
const error = ref(null)
const newComment = ref('')
const isLoginOpen = ref(false)
const isSending = ref(false)
const deletingId = ref(null)

const openLogin = () => {
  isLoginOpen.value = true
}

// Variables computadas
const directors = computed(() => {
  if (!film.value?.cast) return []
  return film.value.cast.filter(person => person.pivot?.role === 'Director')
})

const actors = computed(() => {
  if (!film.value?.cast) return []
  // Filtramos actores y los ordenamos por su orden de crédito oficial *campo credit_order en film_cast_pivot es el orden que da TMDB API al reparto según aparezca en la lista de créditos
  return film.value.cast
    .filter(person => person.pivot?.role === 'Actor')
    .sort((a, b) => (a.pivot?.credit_order ?? 0) - (b.pivot?.credit_order ?? 0))
    .slice(0, 12) // Mostramos los 12 principales para no saturar la ficha
})

// Computed properties existentes
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

// Acciones
const fetchFilm = async () => {
  isLoading.value = true
  error.value = null
  try {
    const id = route.params.id
    
    const { data } = await api.get(`/films/${id}`)
    film.value = data
    await fetchComments()
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
    if (response.data && response.data.data) {
      comments.value = response.data.data
    }
  } catch (e) {
    console.error("Error loading comments:", e)
  }
}

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
  } catch (e) {
    alert("Could not post your comment.")
  } finally {
    isSending.value = false
  }
}

const deleteComment = async (commentId) => {
  if (!confirm('Are you sure you want to delete this comment?')) return
  deletingId.value = commentId
  try {
    await api.delete(`/comments/${commentId}/delete`)
    comments.value = comments.value.filter(c => c.id !== commentId)
  } catch (e) {
    console.error("Error deleting comment:", e)
    alert("Could not delete the comment. Please try again.")
  } finally {
    deletingId.value = null
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'Recent'
  return new Date(dateStr).toLocaleDateString('en-US', {
    day: '2-digit', month: 'short', year: 'numeric'
  })
}

onMounted(fetchFilm)


// VOTACIÓN USERS
//Variable reactiva para que estas dos constantes de user_film_actions.js en /stores/user_film_actions, funcinen en v-model del html
const { userVote, isSavingRate } = storeToRefs(userActionsStore);

// Sincronizar userVote cuando se carga la película
watch(() => film.value, (newFilm) => {
    if (newFilm) {
        // Si la película ya tiene una nota del usuario guardada en bd, la reflejamos en las estrellas
        userVote.value = newFilm.user_action?.rating || 0;
    }
}, { deep: true });

// Acción para guardar la nota
const handleSaveRating = () => {
    userActionsStore.saveRating(film.value.idFilm, film);
}
</script>

<template> 
  <div class="min-h-screen bg-slate-950 text-slate-100 font-sans">

    <div v-if="isLoading" class="flex flex-col items-center justify-center h-screen gap-4">
      <div class="w-12 h-12 border-4 border-emerald-500/20 border-t-emerald-500 rounded-full animate-spin"></div>
      <p class="text-slate-400">Consulting CinemaClub archives...</p>
    </div>

    <div v-else-if="error" class="flex items-center justify-center h-screen">
      <div class="bg-red-500/10 border border-red-500/50 p-6 rounded-2xl text-center">
        <p class="text-red-400">{{ error }}</p>
        <button @click="fetchFilm" class="mt-4 text-red-300 underline cursor-pointer">Retry</button>
      </div>
    </div>

    <div v-else-if="film" class="flex flex-col">
      <header
        class="relative h-80 md:h-[500px] flex items-end px-6 md:px-16 pb-12 bg-cover bg-center"
        :style="{ backgroundImage: `url(${film.backdrop || film.frame || ''})` }"
      >
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent" />
        <div class="relative z-10 w-full max-w-6xl mx-auto">
          <div class="flex items-center gap-3 mb-6">
            <div v-if="filmYear" class="flex items-center gap-2 px-3 py-1 rounded-full border border-emerald-500/40 bg-emerald-500/10 backdrop-blur-md shadow-lg">
              <span class="text-[10px] font-black text-emerald-500 uppercase tracking-widest">Year</span>
              <span class="text-sm font-bold text-white">{{ filmYear }}</span>
            </div>
            <span v-if="filmYear && filmDuration" class="w-1 h-1 bg-slate-500 rounded-full"></span>
            <div v-if="filmDuration" class="flex items-center gap-2 px-3 py-1 rounded-full border border-slate-100/20 bg-slate-100/10 backdrop-blur-md shadow-lg">
              <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Runtime</span>
              <span class="text-sm font-bold text-white">{{ filmDuration }}</span>
            </div>
          </div>
          <h1 class="text-4xl md:text-7xl font-black text-white drop-shadow-2xl tracking-tight">
            {{ film.title }}
          </h1>
        </div>
      </header>

      <!-- Menos espacio arriba para que el frame quede más pegado al título -->
      <main class="max-w-7xl mx-auto px-4 md:px-16 pt-6 pb-12">
        
        <!-- NUEVO GRID: Frame + Sinopsis (mismo bloque) -->
        <section class="grid gap-10 md:grid-cols-12 items-start">

          
          <!-- COLUMNA IZQUIERDA: Frame + Género + Ratings -->
          <aside class="md:col-span-4 lg:col-span-3">
          
            <div class="sticky top-4 flex flex-col gap-4 max-w-[220px]">
              <div class="relative group">
                <img 
                  v-if="film.frame" 
                  :src="film.frame" 
                  class="w-full rounded-2xl shadow-2xl border border-slate-800 transition-transform duration-300 group-hover:scale-[1.02]" 
                />
              </div>

              
              <div v-if="film.genre" class="flex flex-col px-1 mt-3">
                <span class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-2 text-center">Género</span>
                <div class="flex flex-wrap gap-1 justify-center">
                  <span v-for="g in (Array.isArray(film.genre) ? film.genre : film.genre.split(','))" :key="g" 
                    class="text-[10px] bg-slate-800 text-slate-300 px-2 py-0.5 rounded border border-slate-700 uppercase font-medium">
                    {{ g.trim() }}
                  </span>
                </div>
              </div>
              

              <div class="bg-slate-900/50 border border-slate-800 p-4 rounded-2xl space-y-4 shadow-xl backdrop-blur-sm">
                <h3 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest text-center">Ratings</h3>
                <div class="grid grid-cols-2 gap-3">
                  <div class="text-center p-2 bg-slate-950/50 rounded-xl border border-slate-800">
                    <p class="text-[9px] text-slate-500 font-bold uppercase mb-1">TMDB</p>
                    <p class="text-emerald-400 font-black text-lg">{{ formattedTMDB }}</p>
                  </div>
                  <div class="text-center p-2 bg-slate-950/50 rounded-xl border border-slate-800">
                    <p class="text-[9px] text-slate-500 font-bold uppercase mb-1">Club</p>
                    <p class="text-yellow-500 font-black text-lg">
                      {{ film.globalRate > 0 ? Number(film.globalRate).toFixed(1) : '—' }}
                    </p>
                  </div>
                </div>
              </div>

              <div class="bg-slate-900/50 border border-slate-800 p-4 rounded-2xl space-y-3 shadow-xl mt-4">
                <div v-if="!auth.isAuthenticated" class="text-center space-y-1">
                  <h3 class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                    Tu puntuación
                  </h3>
                  
                  <span class="block text-[9px] text-slate-400 leading-tight">
                    <span @click="openLogin" class="text-brand font-bold cursor-pointer">Login</span> 
                    para puntuar
                  </span>
                </div>
                
                <div v-if="auth.isAuthenticated" class="star-rating flex justify-center">
                  <template v-for="star in [5, 4, 3, 2, 1]" :key="star">
                    <input 
                      type="radio" 
                      :id="'star' + star" 
                      name="rating" 
                      :value="star" 
                      v-model="userVote" 
                      @change="handleSaveRating"
                      :disabled="isSavingRate"
                    />
                    <label :for="'star' + star" :title="star + ' estrellas'"></label>
                  </template>
                </div>
                
                <p v-if="isSavingRate" class="text-[9px] text-emerald-500 text-center animate-pulse">Guardando...</p>
                
              </div>
            </div>
          </aside>

          <!-- COLUMNA DERECHA: Sinopsis + resto del contenido -->
          <!-- COLUMNA DERECHA: Sinopsis + resto del contenido -->
          <div class="md:col-span-8 lg:col-span-9 flex flex-col gap-8">

            <!-- Sinopsis ahora dentro del grid, a la derecha del frame -->
            <div class="space-y-4">
              <h2 class="text-2xl font-bold text-white flex items-center gap-2">
                <span class="w-2 h-1 bg-emerald-500 rounded-full"></span> Sinopsis
              </h2>
              <p class="text-slate-300 text-lg leading-relaxed italic">
                {{ film.overview || 'No description available.' }}
              </p>
            </div>

            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl overflow-hidden shadow-xl backdrop-blur-sm">
              <div class="bg-slate-800/50 px-5 py-3 border-b border-slate-700">
                <h3 class="text-xs font-black text-emerald-500 uppercase tracking-widest">Ficha Técnica</h3>
              </div>
              <div class="p-5 space-y-4">
                <div v-if="film.original_title" class="flex flex-col">
                  <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">Título original</span>
                  <span class="text-sm text-slate-200">{{ film.original_title }}</span>
                </div>
                
                <div v-if="directors.length" class="flex flex-col">
                  <span class="text-[10px] text-slate-500 font-bold uppercase tracking-tighter">Director</span>
                  <div class="flex flex-wrap gap-x-2">
                    <template v-for="(dir, index) in directors" :key="dir.idPerson">
                      <router-link :to="`/person/${dir.idPerson}`" class="text-sm text-emerald-400 hover:text-emerald-300 hover:underline transition-colors uppercase">
                        {{ dir.name }}
                      </router-link>
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
                      <router-link :to="`/person/${actor.idPerson}`" class="text-slate-200 hover:text-emerald-400 transition-colors">
                        {{ actor.name }}
                      </router-link>
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
                <span class="w-2 h-1 bg-emerald-500 rounded-full"></span> Comunidad
              </h2>

              <div v-if="auth.isAuthenticated" class="bg-slate-900/40 p-6 rounded-2xl border border-slate-800 space-y-4">
                <textarea 
                  v-model="newComment"
                  rows="3"
                  class="w-full bg-slate-950 border border-slate-800 rounded-xl p-4 text-slate-200 focus:ring-2 focus:ring-emerald-500 outline-none transition-all"
                  placeholder="What did you think of the movie?"
                ></textarea>
                <div class="flex justify-end">
                  <button 
                    @click="postComment"
                    :disabled="!newComment.trim() || isSending"
                    class="bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white px-8 py-2 rounded-full font-bold transition-all cursor-pointer flex items-center gap-2 shadow-lg shadow-emerald-900/20"
                  >
                    <span v-if="isSending" class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin"></span>
                    {{ isSending ? 'Posting...' : 'Post Review' }}
                  </button>
                </div>
              </div>

              <div v-else class="bg-slate-900/30 p-8 rounded-2xl border border-dashed border-slate-700 text-center">
                <p class="text-slate-400">
                  Debes <span @click="openLogin" class="text-emerald-500 font-bold cursor-pointer underline">hacer login</span> para dejar un comentario.
                </p>
              </div>

              <div class="space-y-6">
                <div v-if="comments.length === 0" class="text-slate-600 italic text-center py-4 text-sm">
                  No comments yet. Be the first!
                </div>
                
                <div v-for="comment in comments" :key="comment.id" class="flex gap-4 p-5 rounded-2xl bg-slate-900/20 border border-slate-800/50 hover:bg-slate-900/30 transition-colors">
                  <div class="w-10 h-10 bg-emerald-500/20 rounded-full flex items-center justify-center text-emerald-500 font-bold shrink-0 border border-emerald-500/20">
                    {{ comment.user?.name?.charAt(0).toUpperCase() || 'U' }}
                  </div>
                  <div class="flex-1 space-y-2">
                    <div class="flex items-center justify-between">
                      <h4 class="font-bold text-sm text-slate-200">{{ comment.user?.name || 'User' }}</h4>
                      <span class="text-[10px] text-slate-500 uppercase font-bold tracking-widest">
                        {{ formatDate(comment.created_at) }}
                      </span>
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

  <LoginModal v-model="isLoginOpen" />
</template>


<style>
/* Estilos para las estrellas de CinemaClub */
.star-rating {
  display: flex;
  flex-direction: row-reverse;
  justify-content: flex-start;
  gap: 0.25rem;
}

.star-rating input {
  display: none;
}

.star-rating label {
  cursor: pointer;
  font-size: 1.5rem;
  transition: transform 0.2s;
}

.star-rating label::before {
  font-family: "bootstrap-icons";
  content: "\f588"; /* bi-star (vacía) */
  color: #475569; /* slate-500 */
}

.star-rating label:hover::before,
.star-rating label:hover ~ label::before {
  content: "\f586"; /* bi-star-fill (llena) */
  color: #10b981; /* esmeralda */
}

.star-rating input:checked ~ label::before {
  content: "\f586";
  color: #ef4444; /* rojo (o el color que prefieras) */
}
</style>