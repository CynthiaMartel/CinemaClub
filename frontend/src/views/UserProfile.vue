<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import EditProfileModal from '@/components/EditProfileModal.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const error = ref(null)
const user_profiles = ref(null) 
const userData_fromFilmsActions = ref(null)
const isLoading = ref(true)
const isEditProfileModalOpen = ref(false)

const isFollowing = ref(false) 
const savingFollow = ref(false)

const profileBlockedMessage = ref(null)

// Variables para contenido
const userDebates = ref([])
const userLists = ref([])
const savedLists = ref([])
const userReviews = ref([])
const userDiary = ref([])
const userWatchlist = ref([])

// --- VARIABLES SOCIALES ---
const followers = ref([])
const followings = ref([])
const isMenuOpen = ref(false) 
const isBlocking = ref(false) 

// --- PAGINACIÓN de diary --
const diaryPage = ref(1)
const diaryLastPage = ref(1)
const isLoadingDiary = ref(false)

// Orden de Secciones (Izquierda): Debates, Reviews, Listas Creadas
const contentSections = ref([
  { title: 'Debates Creados', btnLabel: 'DEBATE', type: 'user_debate' },
  { title: 'Reseñas', btnLabel: 'RESEÑA', type: 'user_review' },
  { title: 'Listas Creadas', btnLabel: 'LISTA', type: 'user_list' }
])

const goCreateEntry = () => {
  if (auth.user?.id) {
    router.push({ name: 'create-entry', params: { id: auth.user.id } })
  }
}

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

// 2. Cargar lo que el usuario HA GUARDADO (Su Colección )
const fetchSavedLists = async () => {
  try {
    const userId = route.params.id
    const { data } = await api.get(`/user_profiles/${userId}/saved-lists`)
    savedLists.value = data.data || []
  } catch (err) {
    console.error("Error cargando guardadas:", err)
  }
}

// 3.Cargar DIARIO (Películas vistas o puntuadas  ) con PAGINACIÓN
const fetchUserDiary = async (page = 1, append = false) => { 
  try {
    if (page === 1) isLoading.value = true
    else isLoadingDiary.value = true

    const userId = route.params.id
    
    const { data } = await api.get(`/my_films_diary/${userId}`, { 
        params: { 
            type: 'diary',
            page: page,
            per_page: 20 
        } 
    }) 
    
    if (append) {
        userDiary.value = [...userDiary.value, ...data.data]
    } else {
        userDiary.value = data.data || []
    }

    diaryPage.value = data.pagination.current_page
    diaryLastPage.value = data.pagination.last_page

  } catch (err) {
    console.error("Error cargando diario:", err)
  } finally {
    if (page === 1) isLoading.value = false
    isLoadingDiary.value = false
  }
}

const loadMoreDiary = () => {
    if (diaryPage.value < diaryLastPage.value) {
        fetchUserDiary(diaryPage.value + 1, true)
    }
}

// 4. Cargar WATCHLIST
const fetchUserWatchlist = async () => {
  try {
    const userId = route.params.id
    const { data } = await api.get(`/my_films_diary/${userId}`, { params: { type: 'watch_later' } })
    userWatchlist.value = data.data || []
  } catch (err) {
    console.error("Error cargando watchlist:", err)
  }
}

const fetchUserStats = async () => {
  const userId = route.params.id
  const { data } = await api.get(`/user_films/stats/${userId}`)
  userData_fromFilmsActions.value = data.user
}

const fetchProfile = async () => {
  const userId = route.params.id
  
  // Reseteamos el mensaje de bloqueo antes de cargar
  if (typeof profileBlockedMessage !== 'undefined') {
      profileBlockedMessage.value = null
  }

  try {
      const { data } = await api.get(`/user_profiles/show/${userId}`)
      
      // Si todo va bien, guardamos los datos
      user_profiles.value = data.data
      
      if (data.meta && data.meta.is_following) {
          isFollowing.value = true
      } else {
          isFollowing.value = false
      }

  } catch (e) {
      // AQUÍ DETECTAMOS EL BLOQUEO
      if (e.response && e.response.status === 403) {
          console.warn("Acceso denegado por bloqueo")
                    
          user_profiles.value = null
        
          profileBlockedMessage.value = e.response.data.message || "No puedes ver este perfil."
          
      } else {
          // Errores normales (404, 500, etc.)
          console.error("Error cargando perfil:", e)
          error.value = "Error al cargar el perfil." 
      }
  }
}

// --Cargar Socials (Followers/Followings) 
const fetchSocials = async () => {
  const userId = route.params.id
  try {
    const [followersRes, followingsRes] = await Promise.all([
      api.get(`/user_friends/followers/${userId}`),
      api.get(`/user_friends/followings/${userId}`)
    ])
    followers.value = followersRes.data || []
    followings.value = followingsRes.data || []
  } catch (e) {
    console.error("Error cargando socials:", e)
  }
}

// --- Bloquear Usuario 
const blockUser = async () => {
  if (!confirm("¿Estás seguro de que quieres bloquear a este usuario? Dejarás de seguirlo automáticamente.")) return

  const profileId = route.params.id
  isBlocking.value = true // Activar loading
  
  try {
    await api.post(`/user_friends/${profileId}/block`)  
    
    isFollowing.value = false
    isMenuOpen.value = false
    
    await Promise.all([
        fetchSocials(),
        fetchProfile() 
    ])

    alert("Usuario bloqueado correctamente.")
  } catch (e) {
    console.error("Error bloqueando usuario:", e)
    alert("Error al bloquear usuario.")
  } finally {
    isBlocking.value = false // Desactivar loading
  }
}

// --- Helpers ---
const showBlockedList = () => {
  isMenuOpen.value = false
  alert("Aquí se abrirá el modal con la lista de bloqueados")
}

const goToProfile = (userId) => {
    router.push({ name: 'user-profile', params: { id: userId } })
}

// Obtener inicial para el avatar con inicial del nombre del user
const getInitial = (name) => {
    return name ? name.charAt(0).toUpperCase() : '?'
}

const loadAll = async () => {
  isLoading.value = true
  error.value = null
  userDiary.value = []
  diaryPage.value = 1
  
  try {
    await Promise.all([
      fetchProfile(), 
      fetchUserStats(), 
      fetchUserEntries(), 
      fetchSavedLists(),
      fetchUserDiary(1, false),
      fetchUserWatchlist(),
      fetchSocials() 
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

const diaryGrouped = computed(() => {
  if (!userDiary.value || userDiary.value.length === 0) return {}

  const grouped = {}
  const monthNames = ["ENE", "FEB", "MAR", "ABR", "MAY", "JUN", "JUL", "AGO", "SEP", "OCT", "NOV", "DIC"]

  userDiary.value.forEach(diaryFilm => {
    const date = new Date(diaryFilm.updated_at)
    const key = `${monthNames[date.getMonth()]} ${date.getFullYear()}`
    
    if (!grouped[key]) {
      grouped[key] = []
    }
    grouped[key].push(diaryFilm)
  })

  return grouped
})

const orderedDiaryKeys = computed(() => {
  return Object.keys(diaryGrouped.value) 
})


//////
const toggleFollow = async () => {
  if (savingFollow.value) return;
  const profileId = route.params.id; 
  if (auth.user?.id == profileId) return; 

  savingFollow.value = true;
  // Guardamos el estado anterior por si falla la API
  const previousState = isFollowing.value; 
  
  // Optimismo en la UI: cambiamos el botón inmediatamente antes de que responda la API
  isFollowing.value = !isFollowing.value;

  try {
    if (previousState === true) {
        // Si le seguíamos, hacemos Unfollow
        await api.delete(`/user_friends/${profileId}/unfollow`);
    } else {
        // Si no le seguíamos, hacemos Follow
        await api.post(`/user_friends/${profileId}/follow`);
    }

    // AQUÍ EL CAMBIO: Recargamos los datos para actualizar contadores y listas
    await Promise.all([
        fetchSocials(),
        fetchProfile()
    ]);

  } catch (e) {
    console.error("Error en Follow/Unfollow", e);
    // Si falla, revertimos el botón
    isFollowing.value = previousState; 
    
    if (e.response && e.response.status === 403) {
       alert("No puedes realizar esta acción (posible bloqueo).")
    }
  } finally {
    savingFollow.value = false;
  }
};
///////////

watch(() => route.params.id, (newId) => {
  if (newId) loadAll()
}, { immediate: true })

onMounted(loadAll)
</script>


<template>
  <div class="min-h-screen text-slate-100 font-sans bg-[#14181c] overflow-x-hidden pb-20">
    
    <div v-if="isLoading && diaryPage === 1" class="flex flex-col items-center justify-center h-screen gap-4">
      <div class="w-12 h-12 border-4 border-slate-800 border-t-brand rounded-full animate-spin"></div>
      <p class="text-slate-400 text-sm uppercase tracking-widest">Cargando perfil...</p>
    </div>

    <div v-else-if="profileBlockedMessage" class="flex flex-col items-center justify-center h-[80vh] gap-6 px-4 text-center">
      <div class="w-24 h-24 bg-slate-800 rounded-full flex items-center justify-center mb-2">
         <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-10 h-10 text-slate-400">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
         </svg>
      </div>
      <h2 class="text-2xl md:text-3xl font-black text-white uppercase tracking-tighter">
          Perfil no disponible
      </h2>
      <p class="text-slate-400 text-sm md:text-base max-w-md leading-relaxed">
          {{ profileBlockedMessage }}
      </p>
      <button 
        @click="router.push('/')" 
        class="mt-4 px-6 py-2 bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold uppercase tracking-widest rounded transition-colors"
      >
        Volver al inicio
      </button>
    </div>

    <div v-else-if="user_profiles" class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10 relative z-10">
      
      <header class="flex flex-col md:flex-row items-center md:items-start gap-6 md:gap-10 mb-12">
        <div class="flex-shrink-0">
          <div class="w-32 h-32 md:w-40 md:h-40 rounded-full border-4 border-slate-800 shadow-2xl overflow-hidden bg-slate-700 flex items-center justify-center shrink-0">
             <img 
               v-if="user_profiles.avatar" 
               :src="`/storage/${user_profiles.avatar}`" 
               class="w-full h-full object-cover" 
             />
             <span v-else class="text-6xl font-black text-slate-400 select-none">
                {{ getInitial(user_profiles.user.name) }}
             </span>
          </div>
        </div>

        <div class="flex flex-col flex-grow text-center md:text-left w-full mt-2">
          <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <h1 class="text-3xl md:text-5xl font-black text-white uppercase italic leading-none tracking-tighter">
                {{ user_profiles.user.name }}
            </h1>
            
            <button 
              v-if="auth.user?.id === user_profiles.user.id"
              @click="isEditProfileModalOpen = true" 
              class="bg-brand hover:bg-slate-800 text-white font-bold py-1.5 px-4 rounded shadow-lg disabled:opacity-50 transition-all uppercase tracking-widest text-[10px]"
            >
              Editar Perfil
            </button>
          </div>
          
          <div class="flex items-center justify-center md:justify-start gap-2 text-brand font-bold uppercase tracking-[0.2em] text-[10px] md:text-xs my-3">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
              </svg>
              {{ user_profiles.location || 'Aquí mismo' }}
          </div>

          <div class="text-slate-300 text-sm leading-relaxed whitespace-pre-line font-light max-w-2xl mx-auto md:mx-0">
            {{ user_profiles.bio || ' ' }}
          </div>

          <div v-if="auth.user && auth.user.id !== user_profiles.user.id" class="mt-6 flex justify-center md:justify-start">
            <button 
              @click="toggleFollow" 
              :disabled="savingFollow"
              class="font-black py-2 px-8 rounded-full shadow-lg transition-all uppercase tracking-[0.15em] text-[10px] flex items-center gap-2 group border border-transparent"
              :class="isFollowing 
                ? 'bg-slate-800 text-slate-400 border-slate-700 hover:border-red-900/50 hover:text-red-400' 
                : 'bg-[#BE2B0C] text-white hover:bg-[#a3240a]'"
            >
              <span v-if="savingFollow" class="w-3 h-3 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
              <span v-else>
                 {{ isFollowing ? 'Siguiendo' : 'Seguir' }}
              </span>
            </button>
          </div>

        </div>
      </header>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16">
        
        <div class="lg:col-span-8 flex flex-col gap-10 order-2 lg:order-1">
          
          <section v-if="userData_fromFilmsActions" class="grid grid-cols-2 md:grid-cols-4 gap-4 border-y border-slate-800 py-6">
             <div class="text-center border-r border-slate-800/30">
               <p class="text-2xl md:text-3xl font-black text-white">{{ userData_fromFilmsActions.stats.films_seen }}</p>
               <p class="text-[9px] md:text-[10px] uppercase tracking-widest font-bold text-slate-500">Películas</p>
             </div>
             <div class="text-center border-r border-slate-800/30">
               <p class="text-2xl md:text-3xl font-black text-white">{{ userData_fromFilmsActions.stats.films_seen_this_year }}</p>
               <p class="text-[9px] md:text-[10px] uppercase tracking-widest font-bold text-slate-500">Este año</p>
             </div>
             <div class="text-center border-r border-slate-800/30">
               <p class="text-2xl md:text-3xl font-black text-white">{{ userData_fromFilmsActions.stats.films_rated }}</p>
               <p class="text-[9px] md:text-[10px] uppercase tracking-widest font-bold text-slate-500">Ratings</p>
             </div>
             <div class="text-center">
               <p class="text-2xl md:text-3xl font-black text-white">{{ userLists ? userLists.length : 0 }}</p>
               <p class="text-[9px] md:text-[10px] uppercase tracking-widest font-bold text-slate-500">Listas</p>
             </div>
          </section>

          <section>
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
              <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-500 italic">Mis imprescindibles</h2>
            </div>
            
            <div class="grid grid-cols-3 gap-4" v-if="user_profiles">
              <div 
                v-for="(film, index) in user_profiles.top_films?.slice(0,3)" 
                :key="film.idFilm || index"
                class="aspect-[2/3] bg-slate-800 rounded-lg overflow-hidden border border-slate-700 hover:border-brand/80 hover:scale-[1.02] transition-all shadow-2xl group relative cursor-pointer"
                 @click="router.push(`/films/${film.id}`)"
              >
                <img :src="film.frame || film.poster_url" class="w-full h-full object-cover" loading="lazy" />
              </div>
              <div 
                v-for="i in Math.max(0, 3 - (user_profiles.top_films?.length || 0))" 
                :key="'empty-' + i"
                class="aspect-[2/3] border border-dashed border-slate-800 rounded-lg flex items-center justify-center text-slate-700 text-xs"
              >
                +
              </div>
            </div>
          </section>

          <section v-for="section in contentSections" :key="section.title" class="flex flex-col">
            <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-2">
              <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">{{ section.title }}</h2>
              <button @click="goCreateEntry" class="text-brand text-[10px] font-bold uppercase tracking-widest">+ CREAR</button>
            </div>

            <div v-if="getSectionData(section.type).length > 0" class="brand-scroll flex flex-row-reverse gap-8 overflow-x-auto pb-8 pt-4 rtl-container">
              <div 
                v-for="item in getSectionData(section.type)" 
                :key="item.id" 
                @click="router.push(`/entry/${item.type}/${item.id}`)"
                class="flex-shrink-0 group cursor-pointer ltr-content transition-transform hover:-translate-y-1 duration-300"
                :class="section.type === 'user_review' ? 'w-[160px] md:w-[200px]' : 'w-auto'"
              >
                <div v-if="section.type === 'user_list'" class="w-[180px] md:w-[220px]">
                  <div class="poster-stack-container">
                    <ul class="poster-list-overlapped">
                      <li v-for="(film, idx) in item.films?.slice(0, 5)" :key="idx" class="poster-item" :style="{ zIndex: idx * 10 }">
                        <img :src="film.frame || '/default-poster.webp'" class="poster-img" loading="lazy" />
                      </li>
                    </ul>
                  </div>
                  <h3 class="text-[12px] font-black text-white uppercase mt-4 truncate group-hover:text-brand transition-colors">{{ item.title }}</h3>
                  <p class="text-[9px] text-slate-500 font-bold uppercase mt-1 italic">{{ item.films?.length }} FILMS</p>
                </div>

                <div v-else-if="section.type === 'user_review'" class="review-vertical-card flex flex-col h-full bg-slate-900/40 border border-slate-800 rounded-lg overflow-hidden hover:border-brand/50 transition-all shadow-xl">
                  <div class="relative aspect-[2/3] w-full overflow-hidden">
                    <img :src="item.films?.[0]?.frame || '/default-poster.webp'" class="w-full h-full object-cover opacity-50" loading="lazy" />
                    <div class="absolute top-2 left-2 bg-black/60 px-2 py-1 rounded text-[8px] font-bold text-slate-200">
                      {{ new Date(item.created_at).toLocaleDateString() }}
                    </div>
                  </div>
                  <div class="p-3 flex flex-col gap-1.5">
                    <h3 class="text-[11px] font-black text-brand uppercase leading-tight line-clamp-2">{{ item.title }}</h3>
                    <p class="text-[9px] font-bold text-slate-400 uppercase truncate">{{ item.films?.[0]?.title }}</p>
                    <p class="text-[10px] text-slate-500 italic line-clamp-2 mt-1">"{{ item.content }}"</p>
                  </div>
                </div>

                <div v-else class="flex flex-col">
                  <div class="relative w-36 md:w-44 aspect-video bg-slate-900 border border-slate-800 rounded-lg overflow-hidden shadow-lg">
                    <img :src="item.films?.[0]?.frame || '/default-debate.webp'" class="w-full h-full object-cover opacity-40 group-hover:scale-110 transition-transform duration-700" loading="lazy" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/80 to-transparent"></div>
                  </div>
                  <h3 class="text-[10px] font-black text-white uppercase mt-3 truncate w-32 md:w-40 group-hover:text-brand transition-colors px-1">{{ item.title }}</h3>
                </div>
              </div>
            </div>
            <div v-else class="py-10 border border-dashed border-slate-800 rounded text-center opacity-40 text-[9px] uppercase tracking-widest italic font-bold">Nada por aquí</div>
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
                      <img :src="film.frame || '/default-poster.webp'" class="poster-img" loading="lazy" />
                    </li>
                  </ul>
                </div>
                <h3 class="font-black text-[12px] text-slate-100 uppercase truncate group-hover:text-yellow-500 transition-colors">{{ list.title }}</h3>
                <p class="text-[9px] text-slate-500 font-bold uppercase mt-1">De: {{ list.user?.name }}</p>
              </div>
            </div>
            <div v-else class="py-10 border border-dashed border-slate-800 rounded text-center opacity-40">
              <p class="text-slate-500 text-[9px] uppercase tracking-widest italic font-bold">Aún no has guardado ninguna lista.</p>
            </div>
          </section>

        </div>

        <aside class="lg:col-span-4 flex flex-col gap-10 order-1 lg:order-2">
          
          <section class="bg-slate-900/20 p-6 rounded-2xl border border-slate-800/50 relative">
             <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
                <h2 class="text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 italic">Comunidad</h2>
                
                <div v-if="auth.user && auth.user.id !== user_profiles.user.id" class="relative">
                   <button @click="isMenuOpen = !isMenuOpen" class="text-slate-500 hover:text-white transition-colors p-1">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM12.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0zM18.75 12a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />
                      </svg>
                   </button>
                   
                   <div v-if="isMenuOpen" class="absolute right-0 top-full mt-2 w-48 bg-[#1a1f26] border border-slate-700 rounded shadow-xl z-50 overflow-hidden">
                      <button 
                        @click="blockUser" 
                        :disabled="isBlocking"
                        class="w-full text-left px-4 py-3 text-[10px] uppercase font-bold text-red-400 hover:bg-slate-800 transition-colors border-b border-slate-800"
                      >
                         {{ isBlocking ? 'Bloqueando...' : 'Bloquear usuario' }}
                      </button>
                      <button 
                        @click="showBlockedList" 
                        class="w-full text-left px-4 py-3 text-[10px] uppercase font-bold text-slate-400 hover:bg-slate-800 transition-colors"
                      >
                         Mostrar lista de bloqueados
                      </button>
                   </div>
                </div>
             </div>

             <div class="grid grid-cols-2 gap-4 h-auto max-h-[300px]">
                
                <div class="flex flex-col h-full bg-slate-900/30 rounded p-2 overflow-hidden">
                    <div class="text-center mb-3 border-b border-slate-800 pb-2">
                         <p class="text-xl font-black text-white">{{ followers.length }}</p>
                         <p class="text-[8px] uppercase tracking-widest text-slate-500 font-bold">Followers</p>
                    </div>
                    
                    <div class="overflow-y-auto brand-scroll pr-1 flex-grow">
                        <div class="grid grid-cols-3 sm:grid-cols-3 gap-2 justify-items-center">
                           <div 
                              v-for="user in followers" 
                              :key="user.id"
                              @click="goToProfile(user.id)"
                              class="flex flex-col items-center gap-1 group cursor-pointer w-full"
                            >
                                <div class="w-8 h-8 md:w-9 md:h-9 flex-shrink-0 group-hover:scale-110 transition-transform">
                                    <img 
                                      v-if="user.avatar || (user.profile && user.profile.avatar)"
                                      :src="user.avatar ? `/storage/${user.avatar}` : `/storage/${user.profile.avatar}`" 
                                      class="w-full h-full rounded-full object-cover border border-slate-700 shadow-sm"
                                    />
                                    <div v-else class="w-full h-full rounded-full bg-slate-700 flex items-center justify-center border border-slate-600 shadow-sm">
                                       <span class="text-[10px] font-bold text-white select-none">{{ getInitial(user.name) }}</span>
                                    </div>
                                </div>
                                <p class="text-[8px] font-bold text-slate-400 group-hover:text-white truncate w-full text-center">{{ user.name }}</p>
                            </div>
                        </div>

                        <div v-if="followers.length === 0" class="text-center mt-4">
                           <p class="text-[8px] text-slate-600 italic">Nadie por aquí.</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col h-full bg-slate-900/30 rounded p-2 overflow-hidden">
                    <div class="text-center mb-3 border-b border-slate-800 pb-2">
                         <p class="text-xl font-black text-white">{{ followings.length }}</p>
                         <p class="text-[8px] uppercase tracking-widest text-slate-500 font-bold">Following</p>
                    </div>

                    <div class="overflow-y-auto brand-scroll pr-1 flex-grow">
                        <div class="grid grid-cols-3 sm:grid-cols-3 gap-2 justify-items-center">
                           <div 
                              v-for="user in followings" 
                              :key="user.id"
                              @click="goToProfile(user.id)"
                              class="flex flex-col items-center gap-1 group cursor-pointer w-full"
                            >
                                <div class="w-8 h-8 md:w-9 md:h-9 flex-shrink-0 group-hover:scale-110 transition-transform">
                                    <img 
                                      v-if="user.avatar || (user.profile && user.profile.avatar)"
                                      :src="user.avatar ? `/storage/${user.avatar}` : `/storage/${user.profile.avatar}`" 
                                      class="w-full h-full rounded-full object-cover border border-slate-700 shadow-sm"
                                    />
                                    <div v-else class="w-full h-full rounded-full bg-slate-700 flex items-center justify-center border border-slate-600 shadow-sm">
                                       <span class="text-[10px] font-bold text-white select-none">{{ getInitial(user.name) }}</span>
                                    </div>
                                </div>
                                <p class="text-[8px] font-bold text-slate-400 group-hover:text-white truncate w-full text-center">{{ user.name }}</p>
                            </div>
                        </div>

                        <div v-if="followings.length === 0" class="text-center mt-4">
                           <p class="text-[8px] text-slate-600 italic">Sin seguidos.</p>
                        </div>
                    </div>
                </div>
             </div>

          </section>

          <section class="bg-slate-900/20 p-6 rounded-2xl border border-slate-800/50">
            <div class="flex items-center justify-between mb-8 border-b border-slate-800 pb-2">
              <h2 class="text-[10px] font-bold uppercase tracking-[0.2em] text-green-500 italic w-full text-center">Diary Film</h2>
            </div>

            <div v-if="userDiary.length > 0" class="flex flex-col gap-8">
              <div v-for="monthKey in orderedDiaryKeys" :key="monthKey" class="flex flex-col gap-4">
                <h3 class="text-[10px] font-black text-slate-600 uppercase tracking-widest border-l-4 border-brand pl-3 sticky top-0 bg-[#14181c] z-10 py-2">
                  {{ monthKey }}
                </h3>

                <div class="grid grid-cols-1 gap-2">
                  <div 
                    v-for="diaryFilm in diaryGrouped[monthKey]" 
                    :key="diaryFilm.id"
                    class="flex items-center group hover:bg-slate-800/40 p-2 rounded transition-colors cursor-pointer"
                      @click="router.push(`/films/${diaryFilm.film_id}`)"
                  >
                    <div class="w-10 flex-shrink-0 text-right pr-3 border-r border-slate-800">
                      <span class="text-base font-bold text-slate-400 group-hover:text-white block leading-none">
                          {{ new Date(diaryFilm.updated_at).getDate() }}
                      </span>
                      <span class="text-[8px] uppercase text-slate-600 font-bold tracking-widest">Día</span>
                    </div>

                    <div class="flex items-center gap-3 pl-3 flex-grow">
                      <div class="w-8 h-[48px] flex-shrink-0 rounded overflow-hidden border border-transparent group-hover:border-slate-500">
                        <img :src="diaryFilm.film?.frame || '/default-poster.webp'" class="w-full h-full object-cover" loading="lazy" />
                      </div>

                      <div class="flex flex-col justify-center min-w-0">
                        <h4 class="text-xs font-bold text-slate-200 group-hover:text-brand transition-colors line-clamp-1 truncate">
                          {{ diaryFilm.film?.title }}
                        </h4>
                        
                        <div class="flex items-center gap-2 mt-1">
                          <div v-if="diaryFilm.rating" class="flex items-center gap-0.5">
                              <span class="text-[8px] text-slate-500 uppercase font-black">Rated</span>
                              <span class="text-yellow-500 text-[8px]">★</span>
                          </div>
                          <span v-else class="text-green-500 text-[8px] font-black uppercase">Watched</span>
                          <span v-if="diaryFilm.is_favorite" class="text-red-500 text-[8px]">❤</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="diaryPage < diaryLastPage" class="mt-4 text-center">
                  <button 
                      @click="loadMoreDiary" 
                      :disabled="isLoadingDiary"
                      class="text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 hover:text-brand transition-colors disabled:opacity-50"
                  >
                    {{ isLoadingDiary ? 'Cargando...' : '+ Ver más historial' }}
                  </button>
              </div>
            </div>
            <div v-else class="py-8 border border-dashed border-slate-800 rounded text-center opacity-40">
              <p class="text-slate-500 text-[8px] uppercase tracking-widest italic">Sin actividad.</p>
            </div>
          </section>

          <section class="bg-slate-900/20 p-6 rounded-2xl border border-slate-800/50">
             <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
              <h2 class="text-[10px] font-bold uppercase tracking-[0.2em] text-blue-400 italic">Watchlist</h2>
              <span class="text-[9px] font-bold text-slate-500">{{ userWatchlist.length }} FILMS</span>
            </div>
            
            <div v-if="userWatchlist.length > 0" class="grid grid-cols-4 gap-2">
                <div 
                  v-for="item in userWatchlist.slice(0, 16)" 
                  :key="item.id" 
                  class="aspect-[2/3] relative rounded overflow-hidden border border-slate-800 hover:border-blue-500 transition-colors cursor-pointer group"
                   @click="router.push(`/films/${item.film_id}`)"
                >
                    <img :src="item.film.frame || '/default-poster.webp'" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy" />
                </div>
                <div v-if="userWatchlist.length > 16" class="col-span-4 text-center pt-2">
                   <p class="text-[8px] text-slate-500 italic">+ {{ userWatchlist.length - 16 }} más...</p>
                </div>
            </div>
            <div v-else class="py-4 text-center">
               <p class="text-[8px] text-slate-600 italic">Lista vacía.</p>
            </div>
          </section>

        </aside>

      </div>
    </div>

    <EditProfileModal 
      v-model="isEditProfileModalOpen" 
      :userId="route.params.id"
      :initialData="user_profiles" 
      @updated="loadAll"  
    />

    <LoginModal v-model="isLoginOpen" />
  </div>
</template>

<style scoped>
/* Alineación principal */
.content-wrap {
    width: 100%;
    margin-left: auto;
    margin-right: auto;
}

/* Scrollbar */
.brand-scroll::-webkit-scrollbar {
    height: 4px;
    width: 4px;
}
.brand-scroll::-webkit-scrollbar-track {
    background: #1e293b; 
    border-radius: 10px;
}
.brand-scroll::-webkit-scrollbar-thumb {
    background: #de6f25; 
    border-radius: 10px;
}

/* RTL para scrolls horizontales */
.rtl-container { direction: rtl; }
.ltr-content { direction: ltr; }

/* POSTER STACK (Efecto solapado) */
.poster-list-overlapped {
    display: flex;
    height: 150px;
    position: relative;
    padding-left: 20px;
}
.poster-item {
    position: relative;
    width: 80px;
    height: 120px;
    margin-left: -40px; 
    transition: all 0.3s ease;
}
.poster-item:first-child { margin-left: 0; }
.poster-img {
    width: 80px;
    height: 120px;
    object-fit: cover;
    border: 2px solid #14181c;
    border-radius: 4px;
    box-shadow: 4px 0 15px rgba(0,0,0,0.5);
}
.group:hover .poster-item {
    transform: translateY(-5px);
}

/* Truncados */
.line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

@media (hover: none) {
    .brand-scroll::-webkit-scrollbar { height: 0px; }
}
</style>