<script setup>
import { useAuthStore } from '@/stores/auth'
import { computed, ref, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import RegisterModal from '@/components/RegisterModal.vue'
import HomeBackdropModal from '@/components/HomeBackdropModal.vue'
import StarDisplay from '@/components/StarDisplay.vue'
import { useNavState } from '@/composables/useNavState'

const auth = useAuthStore()
const router = useRouter()
const { isUserMenuOpen } = useNavState()

// --- ESTADO ---
const isRegisterOpen = ref(false)
const isLoading = ref(false)
const isBackdropModalOpen = ref(false)

// Persistencia del fondo del Hero
const heroFilmId = ref(localStorage.getItem('home_hero_id') || 5190)
const heroFilmData = ref(null)

// Datos de secciones
const popularFilms = ref([])
const popularDebates = ref([])
const popularLists = ref([])
const popularReviews = ref([])

// ESTADO para Noticias Posts y Actividad
const journalPosts = ref([])
const friendsActivity = ref([])  // film_actions de personas seguidas
const friendsEntries = ref([])   // debates, reviews, listas de personas seguidas

// Paginación para Entradas de mi comunidad
const friendsEntriesPage = ref(1)
const friendsEntriesPerPage = 6

const paginatedFriendsEntries = computed(() => {
  const start = (friendsEntriesPage.value - 1) * friendsEntriesPerPage
  return friendsEntries.value.slice(start, start + friendsEntriesPerPage)
})

const friendsEntriesTotalPages = computed(() =>
  Math.ceil(friendsEntries.value.length / friendsEntriesPerPage)
)

// Saludo personalizado con nombre de usuario logueado
const welcomeMessage = computed(() => {
  if (!auth.user?.name) return "La red social para amantes del cine."
  const name = auth.user.name.charAt(0).toUpperCase() + auth.user.name.slice(1).toLowerCase()
  return `¡Qué alegría tenerte de vuelta, ${name}!`
})

const isAdmin = computed(() => {
  if (!auth.isAuthenticated || !auth.user) return false;
  const roleId = parseInt(auth.user.idRol);
  const roleName = String(auth.user.role || '').toLowerCase();
  return roleId === 1 || roleName === 'admin';
});

const openRegister = () => { isRegisterOpen.value = true }

const openBackdropModal = () => {
    isBackdropModalOpen.value = true
}

const sortContentByRecent = (contentArray) => {
    return contentArray.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
}

const fetchDashboardData = async () => {
  isLoading.value = true
  
  try {
    const promises = [
        api.get('/user_entries/feed', { params: { page: 1 } }),
        api.get('/films/trending', { params: { per_page: 15 } }),
        api.get(`/films/${heroFilmId.value}`),
        api.get('/post-index') 
    ];

    if (auth.isAuthenticated) {
        promises.push(api.get('/feed').catch(() => null))
    }

    const results = await Promise.allSettled(promises);

    const [feedResult, filmsResult, heroResult, postsResult, activityResult] = results;

    if (feedResult.status === 'fulfilled') {
        const allEntries = feedResult.value.data.data.data;
        popularDebates.value = sortContentByRecent(allEntries.filter(e => e.type === 'user_debate'));
        popularLists.value = sortContentByRecent(allEntries.filter(e => e.type === 'user_list'));
        popularReviews.value = sortContentByRecent(allEntries.filter(e => e.type === 'user_review'));
    }

    if (filmsResult.status === 'fulfilled') {
        popularFilms.value = filmsResult.value.data.data || [];
    }

    if (heroResult.status === 'fulfilled') {
        heroFilmData.value = heroResult.value.data;
    }

    if (postsResult.status === 'fulfilled') {
        const posts = postsResult.value.data.data || postsResult.value.data || [];
        journalPosts.value = posts;
    }

    if (activityResult && activityResult.status === 'fulfilled' && activityResult.value) {
        const allFeed = activityResult.value.data?.feed || [];
        friendsActivity.value = allFeed.filter(item => item.type === 'film_action');
        friendsEntries.value = allFeed.filter(item =>
            ['user_debate', 'user_review', 'user_list'].includes(item.type)
        );
    }

  } catch (error) {
    console.error("Error cargando dashboard:", error)
  } finally {
    isLoading.value = false
  }
}

const formatShortDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const month = date.toLocaleString('en-US', { month: 'short' });
    const day = date.getDate();
    return `${month} ${day}`;
};

const handleBackdropChange = async (film) => {
    if (!film) return;

    try {
        heroFilmId.value = film.idFilm
        localStorage.setItem('home_hero_id', film.idFilm)
        const res = await api.get(`/films/${film.idFilm}`)
        heroFilmData.value = res.data.data || res.data 
    } catch (e) {
        console.error("Error cambiando backdrop", e)
    }
}

const goToEntry = (type, id) => {
    router.push(`/entry/${type}/${id}`)
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('es-ES', { 
        day: '2-digit', month: 'short', year: 'numeric' 
    })
}

// Función para iniciales de usuario (usado en avatares vacíos)
const getInitial = (name) => {
    return name ? name.charAt(0).toUpperCase() : '?'
}

onMounted(() => {
    fetchDashboardData()
})

// Fallback: si auth resuelve después del mount (edge case), re-fetch el feed
watch(() => auth.isAuthenticated, (isAuth) => {
    if (isAuth && friendsActivity.value.length === 0 && friendsEntries.value.length === 0) {
        fetchDashboardData()
    }
})
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20">
    
    <div class="relative h-[44vh] md:h-[50vh] w-full flex items-center justify-center">
      <div class="absolute inset-0 z-0 overflow-hidden">
        <img 
          v-if="heroFilmData?.backdrop"
          :src="heroFilmData.backdrop" 
          class="w-full h-full object-cover opacity-30 animate-ken-burns"
          alt="Hero Backdrop"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-[#14181c] via-[#14181c]/20 to-[#14181c]/60"></div>
      </div>

      <div>  
        <div v-if="isAdmin" class="absolute top-4 left-4 sm:top-10 sm:left-10 z-50 transition-opacity duration-150" :class="{ 'opacity-0 pointer-events-none sm:opacity-100 sm:pointer-events-auto': isUserMenuOpen }">
              <button @click="openBackdropModal" class="flex items-center gap-2 bg-brand/20 hover:bg-brand text-white border border-brand/50 px-3 sm:px-4 py-2 rounded text-[10px] font-black uppercase tracking-widest transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                  <span class="hidden sm:inline">Cambiar Backdrop</span>
                  <span class="sm:hidden">Backdrop</span>
              </button>
          </div>

        <HomeBackdropModal 
            v-model="isBackdropModalOpen" 
            @change-backdrop="handleBackdropChange" 
        />
      </div>

      <div class="relative z-10 text-center px-4 sm:px-6 max-w-4xl animate-fade-in-up">
        <h1 class="text-2xl sm:text-3xl md:text-5xl lg:text-6xl font-black text-white uppercase italic tracking-tighter leading-[0.9] mb-3">
            Watch. Rate. Debate.
        </h1>
        <p class="text-slate-400 font-bold uppercase tracking-[0.2em] sm:tracking-[0.4em] text-[10px] md:text-xs mb-4 md:mb-5">
            {{ welcomeMessage }}
        </p>

        <div class="flex flex-col items-center gap-3 md:gap-5">
            <p class="hidden sm:block text-slate-300 text-sm md:text-base max-w-2xl font-light leading-relaxed">
                Descubre películas, puntúalas, crea listas y debate con otras personas. La comunidad cinéfila empieza aquí.
            </p>

            <button
                v-if="!auth.isAuthenticated"
                @click="openRegister"
                class="bg-brand hover:bg-brand-dark text-white px-6 sm:px-8 py-2.5 sm:py-3 rounded font-black uppercase tracking-widest transition-all hover:scale-105 shadow-xl text-sm"
            >
                ¡Únete a la Comunidad!
            </button>
        </div>
      </div>
    </div>

    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0">
      
      <div v-if="isLoading" class="flex justify-center py-20">
          <div class="w-10 h-10 border-2 border-slate-800 border-t-brand rounded-full animate-spin"></div>
      </div>

      <div v-else class="flex flex-col gap-14">

        <!-- ── ACTIVIDAD DE MI COMUNIDAD (solo si autenticado) ──────────── -->
        <template v-if="auth.isAuthenticated && (friendsActivity.length > 0 || friendsEntries.length > 0)">

          <!-- Films: rated, watched, liked -->
          <section v-if="friendsActivity.length > 0">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
              <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Visionados de mi comunidad</h2>
              <button @click="router.push('/feed')" class="flex items-center gap-1 text-[9px] font-bold text-slate-500 uppercase tracking-widest hover:text-brand transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                Ver todo
              </button>
            </div>

            <ul class="brand-scroll flex gap-4 overflow-x-auto pb-6">
              <li
                v-for="(activity, index) in friendsActivity"
                :key="activity.film_id + '-' + index"
                class="flex-shrink-0 w-[160px] sm:w-[180px] group cursor-pointer"
                @click="router.push(`/films/${activity.film_id}`)"
              >
                <div class="relative w-[160px] h-[240px] sm:w-[180px] sm:h-[270px] rounded overflow-hidden border border-white/10 group-hover:border-white/40 transition-colors shadow-lg">
                  <img :src="activity.film_frame || '/default-poster.webp'" :alt="activity.film_title" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
                  <!-- Avatar + nombre usuario -->
                  <div class="absolute bottom-1 left-1 bg-[#14181c]/90 rounded px-1.5 py-1 flex items-center gap-1.5 backdrop-blur-sm border border-slate-700/50">
                    <div class="w-4 h-4 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center flex-shrink-0">
                      <span class="text-[7px] font-black text-white">{{ getInitial(activity.user) }}</span>
                    </div>
                    <span class="text-[9px] font-bold text-slate-200 truncate max-w-[90px]">{{ activity.user }}</span>
                  </div>
                </div>

                <!-- Iconos + estrellas debajo del poster -->
                <div class="flex flex-col items-center gap-1 mt-2">
                  <div class="flex items-center justify-center gap-1.5">
                    <StarDisplay v-if="activity.rating" :rating="activity.rating" :lg="true" />
                    <span v-if="activity.rating" class="text-[11px] font-black text-slate-200 leading-none tabular-nums">{{ activity.rating }}</span>
                    <svg v-if="activity.is_favorite" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-brand flex-shrink-0" title="Favorita">
                      <path d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z" />
                    </svg>
                    <svg v-if="activity.watched" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-emerald-400 flex-shrink-0" title="Vista">
                      <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /><path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <time class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">{{ formatShortDate(activity.updated_at) }}</time>
                </div>
              </li>
            </ul>
          </section>

          <!-- Entradas: debates, reviews, listas -->
          <section v-if="friendsEntries.length > 0">
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
              <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Debates, reviews, listas de mi comunidad</h2>
              <button @click="router.push('/feed')" class="text-[9px] font-bold text-slate-500 uppercase tracking-widest hover:text-brand transition-colors">Ver todo</button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-5">
              <div
                v-for="(entry, index) in paginatedFriendsEntries"
                :key="'entry-' + entry.entry_id + '-' + index"
                class="group cursor-pointer"
                @click="goToEntry(entry.type, entry.entry_id)"
              >
                <!-- ── THUMBNAIL (varía según tipo) ────────────────── -->
                <div class="mb-2.5">

                  <!-- LISTA: fila solapada de posters, estilo Letterboxd -->
                  <template v-if="entry.type === 'user_list'">
                    <div v-if="entry.films?.length" class="mini-poster-stack">
                      <ul class="mini-poster-list">
                        <li
                          v-for="(film, fi) in entry.films.slice(0, 5)"
                          :key="film.idFilm"
                          class="mini-poster-item"
                          :style="{ zIndex: fi * 10 }"
                          @click.stop="router.push(`/films/${film.idFilm}`)"
                        >
                          <img :src="film.frame || '/default-poster.webp'" :alt="film.title" class="mini-poster-img" loading="lazy" />
                        </li>
                      </ul>
                    </div>
                    <div v-else class="w-[70px] h-[105px] bg-slate-800/60 rounded border border-slate-700/50 flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-slate-600"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" /></svg>
                    </div>
                  </template>

                  <!-- DEBATE: poster portrait + bocadillo naranja -->
                  <template v-else-if="entry.type === 'user_debate'">
                    <div class="relative w-[90px] h-[135px] sm:w-[100px] sm:h-[150px] rounded overflow-hidden border border-white/10 group-hover:border-orange-500/40 transition-colors shadow-md"
                      @click.stop="entry.films?.[0]?.idFilm && router.push(`/films/${entry.films[0].idFilm}`)">
                      <img :src="entry.films?.[0]?.frame || '/default-poster.webp'" :alt="entry.title"
                        class="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500" loading="lazy" />
                      <div class="absolute top-1 left-1 w-4 h-4 rounded-full bg-slate-900/90 border border-slate-700 flex items-center justify-center text-orange-400">
                        <i class="bi bi-chat-quote-fill text-[7px]"></i>
                      </div>
                    </div>
                  </template>

                  <!-- REVIEW: poster portrait sepia + icono globo blanco -->
                  <template v-else>
                    <div class="relative w-[90px] h-[135px] sm:w-[100px] sm:h-[150px] rounded overflow-hidden border border-white/10 group-hover:border-slate-400/50 transition-colors shadow-md"
                      @click.stop="entry.films?.[0]?.idFilm && router.push(`/films/${entry.films[0].idFilm}`)">
                      <img :src="entry.films?.[0]?.frame || '/default-poster.webp'" :alt="entry.title"
                        class="w-full h-full object-cover opacity-75 sepia-[0.5] group-hover:sepia-0 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500" loading="lazy" />
                      <div class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5">
                          <path fill-rule="evenodd" d="M4.804 21.644A6.707 6.707 0 0 0 6 21.75a6.721 6.721 0 0 0 3.583-1.029c.774.182 1.584.279 2.417.279 5.322 0 9.75-3.97 9.75-9 0-5.03-4.428-9-9.75-9s-9.75 3.97-9.75 9c0 2.409 1.025 4.587 2.674 6.192.232.226.277.428.254.543a3.73 3.73 0 0 1-.814 1.686.75.75 0 0 0 .44 1.223 4.58 4.58 0 0 0 .744-.072z" clip-rule="evenodd" />
                        </svg>
                      </div>
                    </div>
                  </template>

                </div>

                <!-- TÍTULO -->
                <h3 class="text-[12px] font-black uppercase text-slate-200 line-clamp-2 mb-2 leading-tight group-hover:text-white transition-colors">{{ entry.title }}</h3>

                <!-- ATTRIBUTION BLOCK -->
                <div class="flex items-start gap-1.5">
                  <div class="w-5 h-5 rounded-full bg-slate-700 border border-slate-500 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <span class="text-[7px] font-black text-white">{{ getInitial(entry.user) }}</span>
                  </div>
                  <div class="min-w-0">
                    <p class="text-[11px] font-bold text-slate-100 truncate">{{ entry.user }}</p>
                    <div class="flex items-center gap-1 flex-wrap mt-0.5">
                      <span class="text-[9px] font-black uppercase tracking-wide px-1.5 py-0.5 rounded"
                        :class="{
                          'text-orange-300 bg-orange-500/15': entry.type === 'user_debate',
                          'text-slate-200 bg-slate-600/40': entry.type === 'user_review',
                          'text-emerald-300 bg-emerald-500/15': entry.type === 'user_list'
                        }">
                        {{ entry.type === 'user_debate' ? 'Debate' : entry.type === 'user_review' ? 'Reseña' : 'Lista' }}
                      </span>
                      <span class="text-slate-600 text-[8px]">·</span>
                      <span class="text-[8px] text-slate-400 uppercase font-bold">{{ formatShortDate(entry.updated_at) }}</span>
                      <span class="text-slate-600 text-[8px]">·</span>
                      <span class="flex items-center gap-0.5 text-[8px] text-slate-300 font-bold">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5 text-brand" aria-hidden="true">
                          <path d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z" />
                        </svg>
                        {{ entry.likes_count || 0 }}
                      </span>
                    </div>
                  </div>
                </div>

              </div>
            </div>

            <!-- Paginación -->
            <div v-if="friendsEntriesTotalPages > 1" class="flex items-center justify-center gap-3 mt-6 pt-4 border-t border-slate-800/50">
              <button
                @click="friendsEntriesPage--"
                :disabled="friendsEntriesPage === 1"
                class="w-7 h-7 flex items-center justify-center rounded border border-slate-700 text-slate-400 hover:border-brand hover:text-brand disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
              </button>
              <span class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">{{ friendsEntriesPage }} / {{ friendsEntriesTotalPages }}</span>
              <button
                @click="friendsEntriesPage++"
                :disabled="friendsEntriesPage === friendsEntriesTotalPages"
                class="w-7 h-7 flex items-center justify-center rounded border border-slate-700 text-slate-400 hover:border-brand hover:text-brand disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
              </button>
            </div>
          </section>

        </template>

        <!-- ── POPULARES ESTA SEMANA ────────────────────────────────────── -->
        <section>
          <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
            <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Populares esta semana</h2>
            <span class="text-[9px] font-bold text-slate-600 uppercase tracking-widest">Actividad Global</span>
          </div>

          <div v-if="popularFilms.length > 0" class="brand-scroll flex gap-4 overflow-x-auto pb-6">
            <div
              v-for="film in popularFilms" :key="film.idFilm"
              class="flex-shrink-0 w-[155px] md:w-[175px] lg:w-[195px] group cursor-pointer"
              @click="router.push(`/films/${film.idFilm}`)"
            >
              <div class="aspect-[2/3] rounded overflow-hidden border border-white/10 group-hover:border-white/40 transition-all shadow-lg relative">
                <img :src="film.frame || '/default-poster.webp'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </div>
              </div>
              <h3 class="mt-2 text-[11px] font-bold text-slate-300 truncate group-hover:text-brand transition-colors">{{ film.title }}</h3>
            </div>
          </div>

          <!-- Botón recomendador -->
          <div class="mt-10 pt-6 border-t border-slate-800/50">
            <button
              @click="router.push({ name: 'recommender' })"
              class="w-full group relative overflow-hidden rounded-xl bg-[#1b2228] border border-white/10 hover:border-amber-500/50 transition-all duration-300 hover:bg-amber-500/5 px-4 sm:px-8 py-4 sm:py-6 flex items-center justify-between gap-4 sm:gap-6 shadow-md"
            >
              <div class="absolute -left-8 top-1/2 -translate-y-1/2 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none"></div>
              <div class="flex items-center gap-5 relative z-10">
                <div class="w-12 h-12 rounded-full bg-amber-500/10 border border-amber-500/30 flex items-center justify-center flex-shrink-0 group-hover:bg-amber-500/20 group-hover:border-amber-500/60 transition-all">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-amber-500">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                  </svg>
                </div>
                <div class="text-left">
                  <p class="text-lg md:text-xl font-black uppercase italic tracking-tighter text-white leading-tight group-hover:text-amber-500 transition-colors">
                    No sé qué ver
                  </p>
                </div>
              </div>
              <div class="flex items-center gap-2 relative z-10 flex-shrink-0">
                <span class="hidden sm:block text-[10px] font-black uppercase tracking-widest text-slate-500 group-hover:text-amber-500 transition-colors">Empezar</span>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 text-slate-600 group-hover:text-amber-500 transition-colors group-hover:translate-x-0.5 transform duration-200">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
              </div>
            </button>
          </div>
        </section>

        <!-- ── DEBATES EN LLAMAS ───────────────────────────────────────── -->
        <section>
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
              <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Debates en llamas</h2>
              <button @click="router.push({ name: 'entry-feed', query: { tab: 'user_debate' } })" class="text-[9px] font-bold text-slate-500 uppercase tracking-widest hover:text-brand transition-colors">Ver todos</button>
            </div>

            <div v-if="popularDebates.length > 0" class="flex flex-col divide-y divide-slate-800/60">
              <div
                v-for="debate in popularDebates.slice(0, 8)" :key="debate.id"
                @click="goToEntry('user_debate', debate.id)"
                class="group cursor-pointer flex gap-4 py-4 first:pt-0 hover:bg-white/[0.02] transition-colors -mx-2 px-2 rounded"
              >
                <!-- Imagen cuadrada izquierda — crop fill como Letterboxd journal -->
                <div class="flex-shrink-0 w-[155px] h-[100px] sm:w-[175px] sm:h-[115px] rounded overflow-hidden bg-slate-900">
                  <img
                    :src="debate.films?.[0]?.frame || '/default-debate.webp'"
                    class="w-full h-full object-cover object-top opacity-75 group-hover:opacity-100 group-hover:scale-105 transition-all duration-400"
                  />
                </div>
                <!-- Texto derecha -->
                <div class="flex flex-col justify-center min-w-0 flex-1">
                  <div class="flex items-center gap-1.5 mb-1.5">
                    <i class="bi bi-chat-quote-fill text-[10px] text-orange-400" aria-hidden="true"></i>
                    <span class="text-[9px] font-black uppercase tracking-[0.2em] text-orange-400 bg-orange-500/15 px-1.5 py-0.5 rounded">Debate</span>
                  </div>
                  <h3 class="text-[12px] font-black uppercase text-slate-200 line-clamp-2 leading-tight group-hover:text-white transition-colors mb-1.5">{{ debate.title }}</h3>
                  <div class="flex items-center gap-1.5">
                    <div class="w-4 h-4 rounded-full bg-slate-700 border border-slate-500 flex items-center justify-center flex-shrink-0">
                      <span class="text-[6px] font-black text-white">{{ getInitial(debate.user?.name) }}</span>
                    </div>
                    <span class="text-[10px] font-bold text-slate-200 truncate">{{ debate.user?.name }}</span>
                  </div>
                </div>
              </div>
            </div>
        </section>

        <section>
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
              <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Journal</h2>
            </div>
            
            <div v-if="journalPosts.length > 0" class="max-h-[600px] overflow-y-auto brand-scroll pr-4 pb-4">
                 <div class="masonry-grid-journal">
                     <article 
                        v-for="post in journalPosts.slice(0, 15)" :key="post.id"
                        @click="router.push(`/post-reed/${post.id}`)"
                        class="masonry-item-journal mb-6 group cursor-pointer bg-[#1b2228] border border-white/10 rounded-lg p-4 hover:border-white/40 transition-all flex flex-col shadow-md"
                     >
                        <div class="rounded-md overflow-hidden mb-4 bg-[#1b2228] relative w-full h-auto">
                            <img :src="post.img || '/default-poster.webp'" class="w-full h-auto object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500" loading="lazy" />
                            <div v-if="parseInt(post.visible) === 0" class="absolute top-2 right-2 bg-amber-500 text-black px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest shadow-md z-10">
                                Borrador
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[8px] text-slate-400 uppercase tracking-widest font-bold">{{ formatDate(post.created_at) }}</span>
                            <span class="w-1 h-1 rounded-full bg-slate-600"></span>
                            <span class="text-[8px] text-slate-300 uppercase tracking-widest font-bold">{{ post.editorName || 'CC' }}</span>
                        </div>

                        <h3 class="text-sm md:text-base font-serif font-black text-white mb-2 group-hover:text-brand transition-colors leading-tight">{{ post.title }}</h3>
                        <p class="text-[10px] md:text-xs text-slate-400 font-light line-clamp-3">{{ post.subtitle }}</p>
                     </article>
                 </div>

                 <div v-if="journalPosts.length > 15" class="w-full flex justify-center mt-6 pt-4 border-t border-slate-800/50">
                    <button @click="router.push({ name: 'post-feed' })" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-white transition-colors py-2.5 px-8 border border-slate-700 hover:border-brand hover:bg-brand/10 rounded-full">
                        Ver todo el Journal
                    </button>
                 </div>
            </div>
            <div v-else class="py-10 border border-dashed border-slate-800 rounded text-center opacity-40">
                <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold italic">Aún no hay publicaciones.</p>
            </div>
        </section>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-10">
            <div class="lg:col-span-4">
                <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
                  <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Listas Populares</h2>
                  <button @click="router.push({ name: 'entry-feed', query: { tab: 'user_list' } })" class="text-[9px] font-bold text-slate-500 uppercase tracking-widest hover:text-brand transition-colors">
                    Ver más
                  </button>
                </div>
                <div class="flex flex-col gap-6">
                    <div
                        v-for="list in popularLists.slice(0, 4)" :key="list.id"
                        @click="goToEntry('user_list', list.id)"
                        class="group cursor-pointer"
                    >
                        <div v-if="list.films?.length" class="mini-poster-stack mb-2.5">
                            <ul class="mini-poster-list">
                                <li
                                    v-for="(film, idx) in list.films.slice(0, 5)"
                                    :key="film.idFilm || idx"
                                    class="mini-poster-item"
                                    :style="{ zIndex: idx * 10 }"
                                >
                                    <img :src="film.frame || '/default-poster.webp'" :alt="film.title" class="mini-poster-img" loading="lazy" />
                                </li>
                            </ul>
                        </div>
                        <div v-else class="w-[90px] h-[135px] bg-slate-800/60 rounded border border-slate-700/50 flex items-center justify-center mb-2.5">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-slate-600"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" /></svg>
                        </div>
                        <h3 class="text-[12px] font-black uppercase text-slate-200 line-clamp-1 group-hover:text-brand transition-colors">{{ list.title }}</h3>
                        <div class="flex items-center gap-1.5 mt-1">
                            <div class="w-4 h-4 rounded-full bg-slate-700 border border-slate-500 flex items-center justify-center flex-shrink-0">
                                <span class="text-[7px] font-black text-white">{{ getInitial(list.user?.name) }}</span>
                            </div>
                            <p class="text-[10px] font-bold text-slate-200 truncate">{{ list.user?.name }}</p>
                        </div>
                        <p class="text-[9px] text-slate-400 uppercase mt-1 font-bold">{{ list.films?.length }} Films</p>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8">
                <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
                  <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Reviews Populares</h2>
                  <button @click="router.push({ name: 'entry-feed', query: { tab: 'user_review' } })" class="text-[9px] font-bold text-slate-500 uppercase tracking-widest hover:text-brand transition-colors">
                     Ver más
                  </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-8 mt-4">
                    <div 
                        v-for="review in popularReviews.slice(0, 4)" :key="review.id"
                        @click="goToEntry('user_review', review.id)"
                        class="flex gap-4 group cursor-pointer relative"
                    >
                        <div class="flex-shrink-0 w-[80px] sm:w-[90px]">
                            <div class="aspect-[2/3] rounded border border-slate-700 group-hover:border-brand transition-colors overflow-hidden shadow-md">
                                <img :src="review.films?.[0]?.frame" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                            </div>
                        </div>

                        <div class="flex flex-col flex-grow min-w-0">
                            <div class="flex items-center gap-2 mb-1.5">
                                <div class="w-5 h-5 rounded-full bg-slate-700 border border-slate-500 flex items-center justify-center flex-shrink-0">
                                    <span class="text-[8px] font-black text-white">{{ getInitial(review.user?.name) }}</span>
                                </div>
                                <span class="text-[11px] font-bold text-slate-100 truncate hover:text-brand transition-colors">{{ review.user?.name }}</span>
                            </div>

                            <div class="flex items-baseline gap-1.5 mb-2">
                                <h3 class="text-[14px] font-black text-white group-hover:text-brand transition-colors truncate">{{ review.films?.[0]?.title }}</h3>
                                <span v-if="review.films?.[0]?.year" class="text-[10px] text-slate-500 font-bold">{{ review.films?.[0]?.year }}</span>
                            </div>

                            <div v-if="review.rating" class="flex items-center gap-2 mb-2">
                                <StarDisplay :rating="review.rating" />
                            </div>

                            <div class="ck-content text-[12px] text-slate-400 font-light line-clamp-3 mb-3 leading-relaxed" v-html="review.content"></div>

                            <div class="mt-auto flex items-center gap-4 text-[10px] font-bold">
                                <div class="flex items-center gap-1.5 text-slate-300 hover:text-brand transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-brand" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ review.likes_count || 0 }} likes</span>
                                </div>
                                <div class="flex items-center gap-1.5 text-slate-400 hover:text-slate-200 transition-colors">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-slate-300" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd" />
                                    </svg>
                                    <span>{{ review.comments_count || 0}}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

      </div>
    </div>

    <RegisterModal v-model="isRegisterOpen" />
  </div>
</template>

<style scoped>
/* Contenedor principal */
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

/* Tipografía Serif */
.font-serif {
  font-family: 'Tiempos Headline', Georgia, serif;
}

/* --- MASONRY GRID (ESTILO PINTEREST) PARA JOURNAL --- */
.masonry-grid-journal {
    column-count: 1;
    column-gap: 1.5rem;
}
@media (min-width: 640px) { .masonry-grid-journal { column-count: 2; } }
@media (min-width: 1024px) { .masonry-grid-journal { column-count: 3; } }

.masonry-item-journal {
    break-inside: avoid;
    display: inline-block;
    width: 100%;
}

/* Animación de fondo Ken Burns */
@keyframes ken-burns {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}
.animate-ken-burns {
  animation: ken-burns 30s ease-in-out infinite;
}

@keyframes fade-in-up {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
  animation: fade-in-up 1.2s ease-out forwards;
}

/* Mini poster stack (Entradas de mi comunidad — listas / Listas Populares) */
.mini-poster-stack { overflow: hidden; }
.mini-poster-list { display: flex; height: 135px; position: relative; list-style: none; padding: 0; margin: 0; }
.mini-poster-item { position: relative; width: 90px; height: 135px; margin-left: -63px; flex-shrink: 0; transition: transform 0.4s ease; }
.mini-poster-item:first-child { margin-left: 0; }
.mini-poster-img { width: 90px; height: 135px; object-fit: cover; border: 1.5px solid #14181c; border-radius: 5px; box-shadow: 8px 0 18px rgba(0,0,0,0.6); }
.group:hover .mini-poster-item { transform: translateY(-6px) rotate(-1deg); }

/* Scrollbars personalizadas */
.brand-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.brand-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 10px; }
.brand-scroll::-webkit-scrollbar-thumb { background: var(--brand-color, #dd6a23); border-radius: 10px; }

/* Truncado de texto */
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

@media (hover: none) {
  .brand-scroll::-webkit-scrollbar { height: 0px; width: 0px; }
}
</style>