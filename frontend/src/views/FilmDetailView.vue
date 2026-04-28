<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
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
import AddToListModal from '@/components/AddToListModal.vue'
import WatchProviders from '@/components/WatchProviders.vue'

const route = useRoute()
const auth = useAuthStore()
const userActionsStore = useUserFilmActionsStore()
const { userVote } = storeToRefs(userActionsStore)

// Variables de estado
const film = ref(null)
const isLoading = ref(true)
const error = ref(null)
const isLoginOpen = ref(false)
const isDetailsModalOpen = ref(false)
const isCastCrewModalOpen = ref(false)
const isListModalOpen = ref(false)
const selectedActorId = ref(null)

const openLogin = () => { isLoginOpen.value = true }

// Entries que mencionan esta película
const filmEntries = ref({ lists: [], debates: [], reviews: [] })
const activeEntriesTab = ref('lists')
const isLoadingEntries = ref(false)

const hasAnyEntries = computed(() =>
  filmEntries.value.lists.length > 0 ||
  filmEntries.value.debates.length > 0 ||
  filmEntries.value.reviews.length > 0
)

const activeEntries = computed(() => filmEntries.value[activeEntriesTab.value])

const ENTRY_LIMIT = 6
const showAllEntries = ref(false)
watch(activeEntriesTab, () => { showAllEntries.value = false })
const visibleEntries = computed(() =>
  showAllEntries.value ? activeEntries.value : activeEntries.value.slice(0, ENTRY_LIMIT)
)
const hasMoreEntries = computed(() => activeEntries.value.length > ENTRY_LIMIT)

const entryTabs = [
  { key: 'lists',   label: 'Listas',   type: 'user_list' },
  { key: 'debates', label: 'Debates',  type: 'user_debate' },
  { key: 'reviews', label: 'Reseñas',  type: 'user_review' },
]

const fetchFilmEntries = async (filmId) => {
  isLoadingEntries.value = true
  try {
    const [listsRes, debatesRes, reviewsRes] = await Promise.all([
      api.get(`/user_entries/feed`, { params: { idFilm: filmId, type: 'user_list' } }),
      api.get(`/user_entries/feed`, { params: { idFilm: filmId, type: 'user_debate' } }),
      api.get(`/user_entries/feed`, { params: { idFilm: filmId, type: 'user_review' } }),
    ])
    filmEntries.value.lists   = listsRes.data?.data?.data   || []
    filmEntries.value.debates = debatesRes.data?.data?.data || []
    filmEntries.value.reviews = reviewsRes.data?.data?.data || []

    // Asegurarse de que la tab activa tenga resultados
    if (filmEntries.value.lists.length === 0 && filmEntries.value.debates.length > 0) activeEntriesTab.value = 'debates'
    else if (filmEntries.value.lists.length === 0 && filmEntries.value.reviews.length > 0) activeEntriesTab.value = 'reviews'
  } catch (e) {
    console.error('Error fetching film entries:', e)
  } finally {
    isLoadingEntries.value = false
  }
}

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

const countryNames = new Intl.DisplayNames(['es'], { type: 'region' })
const originCountries = computed(() => {
  if (!film.value?.origin_country) return []
  return film.value.origin_country
    .split(',')
    .map(c => c.trim().toUpperCase())
    .filter(Boolean)
    .map(code => {
      try { return countryNames.of(code) || code } catch { return code }
    })
})

// Traducción de sinopsis
const translatedOverview = ref(null)
const isTranslating = ref(false)
const showTranslated = ref(false)

const translateOverview = async () => {
  if (translatedOverview.value) { showTranslated.value = true; return }
  isTranslating.value = true
  try {
    const text = encodeURIComponent(film.value.overview)
    const res = await fetch(`https://api.mymemory.translated.net/get?q=${text}&langpair=en|es`)
    const json = await res.json()
    translatedOverview.value = json.responseData?.translatedText || null
    if (translatedOverview.value) showTranslated.value = true
  } catch (e) {
    console.error('Error al traducir:', e)
  } finally {
    isTranslating.value = false
  }
}

const toggleTranslation = () => {
  if (showTranslated.value) { showTranslated.value = false; return }
  translateOverview()
}


// Carga de datos
const fetchFilm = async () => {
  isLoading.value = true
  error.value = null
  try {
    const id = route.params.id
    const { data } = await api.get(`/films/${id}`)
    film.value = data
    await Promise.all([fetchUserFilmActions(), fetchFilmEntries(id)])
  } catch (e) {
    error.value = 'No se pudo cargar la información de la película.'
  } finally {
    isLoading.value = false
  }
}

const stripHtml = (html) => {
  if (!html) return ''
  return html.replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 160)
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
      <p class="text-slate-500 font-black uppercase tracking-[0.3em] text-[10px]">FilmoClub is loading...</p>
    </div>

    <div v-else-if="error" class="flex items-center justify-center h-screen px-4">
      <div class="bg-red-950/20 border border-red-900/50 p-8 rounded-3xl text-center max-w-sm backdrop-blur-md">
        <p class="text-red-500 font-bold mb-6 text-sm uppercase tracking-widest">{{ error }}</p>
        <button @click="fetchFilm" class="w-full py-3 bg-red-900/20 hover:bg-red-900/40 text-red-200 rounded-xl transition-all uppercase text-[10px] font-black tracking-widest border border-red-900/30">Reintentar</button>
      </div>
    </div>

    <div v-else-if="film" class="relative">
      
      <header class="absolute top-0 left-0 w-full h-[320px] sm:h-[420px] md:h-[550px] overflow-hidden z-0">
        <div 
          class="absolute inset-0 bg-cover bg-center transition-transform duration-[4s] scale-105 opacity-50"
          :style="{ backgroundImage: `url(${film.backdrop || film.frame || ''})` }"
        ></div>
        
        <div class="absolute inset-0 bg-gradient-to-t from-[#14181c] via-transparent to-transparent"></div>
        
        <div class="absolute inset-0 hidden md:block bg-gradient-to-r from-[#14181c] via-transparent to-[#14181c]"></div>
        <div class="absolute inset-0 hidden md:block shadow-[inset_0_0_150px_rgba(20,24,28,1)]"></div>
      </header>

      <div class="content-wrap relative z-10 mx-auto max-w-[1200px] px-4 sm:px-8 md:px-16 lg:px-24">

        <div class="film-page-wrapper pt-[200px] sm:pt-[240px] md:pt-[280px] grid grid-cols-1 md:grid-cols-[230px_1fr] gap-x-8 lg:gap-x-16 pb-16 md:pb-20">
          
          <aside class="flex flex-col gap-6 md:sticky md:top-10 self-start">
            <div class="relative group w-40 sm:w-48 md:w-full mx-auto md:mx-0 shadow-[0_0_20px_rgba(0,0,0,0.5)] rounded-lg overflow-hidden border border-white/10 bg-[#1b2228] transition-all duration-500 hover:scale-[1.05] hover:border-white/30 cursor-pointer">
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
                <button @click="userActionsStore.toggleFavorite(film.idFilm, film)" :class="film.user_action?.is_favorite ? 'text-[#BE2B0C] bg-[#BE2B0C]/10' : 'hover:text-white'" class="action-btn aspect-square flex items-center justify-center rounded transition-all relative">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z" /></svg>
                  <span class="action-tooltip">Favorito</span>
                </button>
                <button @click="userActionsStore.toggleWatched(film.idFilm, film)" :class="film.user_action?.watched ? 'text-[#00c020] bg-[#00c020]/10' : 'hover:text-white'" class="action-btn aspect-square flex items-center justify-center rounded transition-all relative">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" /><circle cx="12" cy="12" r="3" /></svg>
                  <span class="action-tooltip">Vista</span>
                </button>
                <button @click="userActionsStore.toggleWatchLater(film.idFilm, film)" :class="film.user_action?.watch_later ? 'text-[#34a8c4] bg-[#34a8c4]/10' : 'hover:text-white'" class="action-btn aspect-square flex items-center justify-center rounded transition-all relative">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></svg>
                  <span class="action-tooltip">Ver más tarde</span>
                </button>
                <button @click="isListModalOpen = true" class="action-btn aspect-square flex items-center justify-center rounded hover:text-white transition-all relative">
                  <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19" /><line x1="5" y1="12" x2="19" y2="12" /></svg>
                  <span class="action-tooltip">Añadir a lista</span>
                </button>
              </div>

              <div v-if="!auth.isAuthenticated"
                   @click="openLogin"
                   class="absolute inset-0 bg-slate-950/90 backdrop-blur-sm opacity-0 group-hover/actions:opacity-100 transition-all duration-300 flex items-center justify-center rounded-lg cursor-pointer p-4 text-center z-20">
                <p class="text-[9px] font-black text-white uppercase tracking-[0.2em]">Login para interactuar</p>
              </div>
            </div>

            <WatchProviders :filmId="film.idFilm" />
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
              <p class="text-slate-300 text-lg leading-relaxed italic opacity-90 transition-opacity duration-300" :class="isTranslating ? 'opacity-40' : 'opacity-90'">
                {{ showTranslated && translatedOverview ? translatedOverview : film.overview || 'Sinopsis no disponible.' }}
              </p>
              <div v-if="film.overview" class="flex items-center gap-3 mt-4">
                <button
                  @click="toggleTranslation"
                  :disabled="isTranslating"
                  class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-[#678] hover:text-white border border-[#2a3240] hover:border-[#445] px-3 py-1.5 rounded transition-all disabled:opacity-40"
                >
                  <svg v-if="isTranslating" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M5 8l6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/></svg>
                  {{ isTranslating ? 'Traduciendo...' : showTranslated ? 'Ver original' : 'Traducir al español' }}
                </button>
                <span v-if="showTranslated && translatedOverview" class="text-[9px] text-[#445]">vía MyMemory</span>
              </div>
            </section>

            <div class="details-tabs bg-[#1b2228] border border-white/5 rounded-lg overflow-hidden mb-24 shadow-xl">
              <header class="flex justify-between items-center px-6 py-4 border-b border-white/5 bg-white/[0.02]">
                <h3 class="text-xs font-black text-white uppercase tracking-[0.3em]">Detalles</h3>
                <button @click="isDetailsModalOpen = true" class="text-[9px] font-black text-[#9ab] hover:text-white transition-colors">
                  VER MÁS +
                </button>
              </header>

              <div class="p-6 md:p-8 space-y-10">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                  <div class="space-y-1.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Idioma original</span>
                    <p class="text-sm font-bold text-white uppercase">{{ film.original_language || '—' }}</p>
                  </div>
                  <div class="space-y-1.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Duración</span>
                    <p class="text-sm font-bold text-white">{{ filmDuration || '—' }}</p>
                  </div>
                  <div class="space-y-1.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Género</span>
                    <div class="flex flex-wrap gap-x-2 gap-y-1">
                      <span
                        v-for="genre in (film.genre || '').split(',').map(g => g.trim()).filter(Boolean)"
                        :key="genre"
                        class="text-[11px] font-bold text-slate-100 bg-white/5 px-2 py-1 rounded hover:bg-[#BE2B0C]/20 hover:text-white cursor-pointer transition-all"
                      >{{ genre }}</span>
                    </div>
                  </div>

                  <div v-if="originCountries.length" class="space-y-1.5">
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">País de origen</span>
                    <div class="flex flex-wrap gap-x-2 gap-y-1">
                      <span
                        v-for="country in originCountries"
                        :key="country"
                        class="text-[11px] font-bold text-slate-100 bg-white/5 px-2 py-1 rounded"
                      >{{ country }}</span>
                    </div>
                  </div>

                </div>

                <div v-if="actors.length" class="pt-6 border-t border-white/5">
                  <span class="text-[9px] text-[#678] font-black uppercase tracking-widest block mb-4">Reparto Principal</span>
                  <div class="flex flex-wrap gap-x-2 gap-y-1">
                    <template v-for="actor in actors" :key="actor.idPerson">
                      <span @click="openPerson(actor.idPerson)" class="text-[11px] font-bold text-slate-100 bg-white/5 px-2 py-1 rounded hover:bg-[#BE2B0C]/20 hover:text-white cursor-pointer transition-all">
                        {{ actor.name }}
                      </span>
                    </template>
                  </div>
                </div>
              </div>
            </div>

            <!-- Aparece en FilmoClub -->
            <div class="mb-14 pt-16">

              <!-- Header accent + CTA -->
              <div class="flex flex-wrap items-start justify-between gap-6 border-l-4 border-[#BE2B0C] pl-4 mb-8">
                <h3 class="text-lg font-black uppercase italic tracking-tighter text-white leading-none pt-0.5">Aparece en FilmoClub</h3>

                <div v-if="auth.isAuthenticated" class="flex flex-col items-end gap-2">
                  <p class="text-[9px] text-[#678] font-black uppercase tracking-wider">Crea un debate, review o lista:</p>
                  <router-link
                    :to="{ name: 'create-entry' }"
                    class="flex items-center gap-1.5 px-4 py-2 rounded-full text-[10px] font-black uppercase tracking-widest border border-[#BE2B0C]/50 text-[#BE2B0C] hover:bg-[#BE2B0C] hover:text-white transition-all duration-300"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Crear Entrada
                  </router-link>
                </div>

                <div v-else class="flex flex-col items-end gap-1.5 text-right">
                  <p class="text-[9px] text-[#678] font-black uppercase tracking-wider">Crea un debate, review o lista:</p>
                  <p class="text-[10px] text-[#9ab] leading-relaxed">
                    <button @click="openLogin" class="text-[#BE2B0C] font-black hover:underline">Regístrate</button>
                    <span class="text-[#678]"> o </span>
                    <button @click="openLogin" class="font-bold hover:text-white hover:underline">inicia sesión</button>
                    <span class="text-[#678]"> si ya tienes cuenta para acceder</span>
                  </p>
                </div>
              </div>

              <!-- Tabs y entries (solo si hay contenido) -->
              <template v-if="hasAnyEntries || isLoadingEntries">
              <div class="entries-tabs-row">
                <button
                  v-for="tab in entryTabs"
                  :key="tab.key"
                  @click="activeEntriesTab = tab.key"
                  class="px-4 py-2 rounded text-[10px] font-black uppercase tracking-widest border transition-all duration-200"
                  :class="activeEntriesTab === tab.key
                    ? 'bg-white/10 text-white border-white/30'
                    : 'bg-transparent text-[#678] border-[#2a3240] hover:border-[#445] hover:text-[#9ab]'"
                >
                  {{ tab.label }}
                  <span v-if="filmEntries[tab.key].length" class="ml-1 opacity-60">{{ filmEntries[tab.key].length }}</span>
                </button>
              </div>

              <!-- Skeleton loader -->
              <div v-if="isLoadingEntries" class="flex gap-4 overflow-hidden">
                <div v-for="i in 4" :key="i" class="flex-shrink-0 w-[120px]">
                  <div class="aspect-[2/3] rounded-lg bg-white/5 animate-pulse mb-2"></div>
                  <div class="h-2.5 bg-white/5 rounded animate-pulse mb-1.5"></div>
                  <div class="h-2 bg-white/5 rounded animate-pulse w-2/3"></div>
                </div>
              </div>

              <!-- Cards horizontales -->
              <template v-else>
                <div v-if="activeEntries.length" class="entries-scroll" :class="activeEntriesTab === 'lists' ? 'entries-grid' : 'entries-list'">

                  <router-link
                    v-for="entry in visibleEntries"
                    :key="entry.id"
                    :to="{ name: 'entry-detail', params: { type: entry.type, id: entry.id } }"
                    class="group cursor-pointer"
                    :class="activeEntriesTab === 'lists' ? 'flex-shrink-0 w-[200px]' : 'block'"
                  >
                    <!-- LISTA: posters solapados -->
                    <template v-if="activeEntriesTab === 'lists'">
                      <div class="entry-poster-wrap mb-3 pl-1">
                        <ul class="entry-poster-stack">
                          <li
                            v-for="(film, idx) in entry.films?.slice(0, 4)"
                            :key="idx"
                            class="entry-poster-item"
                            :style="{ zIndex: idx * 10 }"
                          >
                            <img :src="film.frame" class="entry-poster-img" />
                          </li>
                          <li v-if="!entry.films?.length" class="entry-poster-item" style="z-index:0">
                            <div class="entry-poster-img bg-[#1b2228] border border-white/10 flex items-center justify-center">
                              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#445]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                            </div>
                          </li>
                        </ul>
                      </div>
                      <h4 class="text-[11px] font-black uppercase text-white group-hover:text-[#BE2B0C] transition-colors leading-tight truncate px-0.5">{{ entry.title }}</h4>
                      <p class="text-[9px] text-[#678] font-bold uppercase mt-1 px-0.5 truncate tracking-wider">{{ entry.user?.name }}</p>
                    </template>

                    <!-- RESEÑA: tarjeta horizontal tipo Letterboxd -->
                    <template v-else-if="activeEntriesTab === 'reviews'">
                      <div class="flex gap-4 bg-[#1b2228]/50 hover:bg-[#1b2228] border border-white/5 hover:border-white/15 rounded-lg p-3 transition-all duration-200">
                        <div class="flex-shrink-0">
                          <div class="w-[52px] h-[78px] rounded overflow-hidden border border-white/10 bg-[#0d1117]">
                            <img v-if="entry.films?.[0]?.frame" :src="entry.films[0].frame" class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center">
                              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#334]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            </div>
                          </div>
                        </div>
                        <div class="flex flex-col gap-1 min-w-0 flex-1 py-0.5">
                          <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full bg-[#BE2B0C]/20 flex items-center justify-center text-[9px] font-black text-[#BE2B0C] flex-shrink-0 uppercase">{{ entry.user?.name?.[0] }}</div>
                            <span class="text-[10px] font-black text-[#9ab] uppercase tracking-wider truncate">{{ entry.user?.name }}</span>
                            <span v-if="entry.likes_count" class="text-[9px] text-[#445] flex-shrink-0 ml-auto">{{ entry.likes_count }} ♥</span>
                          </div>
                          <h4 class="text-[12px] font-black text-white group-hover:text-[#BE2B0C] transition-colors leading-snug line-clamp-1 mt-0.5">{{ entry.title }}</h4>
                          <p v-if="entry.content" class="text-[11px] text-[#567] leading-relaxed line-clamp-2">{{ stripHtml(entry.content) }}</p>
                        </div>
                      </div>
                    </template>

                    <!-- DEBATE: tarjeta editorial con acento naranja -->
                    <template v-else-if="activeEntriesTab === 'debates'">
                      <div class="flex gap-4 bg-[#171c20] hover:bg-[#1c2228] border border-white/5 hover:border-orange-400/20 rounded-lg p-4 transition-all duration-200 relative overflow-hidden">
                        <div v-if="entry.films?.[0]?.frame" class="absolute inset-0 opacity-[0.05] bg-cover bg-center pointer-events-none" :style="{ backgroundImage: `url(${entry.films[0].frame})` }"></div>
                        <div class="flex-shrink-0 relative z-10">
                          <div class="w-[40px] h-[60px] rounded overflow-hidden border border-orange-400/15 bg-[#0d1117]">
                            <img v-if="entry.films?.[0]?.frame" :src="entry.films[0].frame" class="w-full h-full object-cover opacity-60 grayscale" />
                            <div v-else class="w-full h-full flex items-center justify-center">
                              <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3 text-orange-400/40" fill="currentColor" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            </div>
                          </div>
                        </div>
                        <div class="flex flex-col gap-1.5 min-w-0 flex-1 relative z-10">
                          <div class="flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-2.5 h-2.5 text-orange-400 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            <span class="text-[8px] font-black uppercase tracking-[0.2em] text-orange-400">Debate</span>
                            <span v-if="entry.likes_count" class="text-[9px] text-[#445] ml-auto">{{ entry.likes_count }} ♥</span>
                          </div>
                          <h4 class="text-[13px] font-black text-white group-hover:text-orange-300 transition-colors leading-snug line-clamp-2">{{ entry.title }}</h4>
                          <p v-if="entry.content" class="text-[11px] text-[#567] line-clamp-2 leading-relaxed">{{ stripHtml(entry.content) }}</p>
                          <span class="text-[9px] text-[#9ab] font-bold pt-0.5">{{ entry.user?.name }}</span>
                        </div>
                      </div>
                    </template>
                  </router-link>

                </div>

                <div v-if="hasMoreEntries && !showAllEntries" class="mt-4 text-center">
                  <button
                    @click="showAllEntries = true"
                    class="text-[9px] font-black uppercase tracking-widest text-[#678] hover:text-white border border-[#2a3240] hover:border-[#445] px-5 py-2 rounded transition-all"
                  >
                    Ver los {{ activeEntries.length - ENTRY_LIMIT }} restantes
                  </button>
                </div>

                <p v-else-if="!activeEntries.length" class="text-[10px] text-[#678] font-black uppercase tracking-widest py-4">Sin resultados aún</p>
              </template>
              </template>
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

  <AddToListModal v-model="isListModalOpen" :filmId="film?.idFilm" />
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

/* Tabs de entries */
.entries-tabs-row { display: flex; gap: 16px; margin-top: 40px; margin-bottom: 24px; }

/* Grid horizontal entries (listas) */
.entries-grid { display: flex; gap: 32px; overflow-x: auto; padding-bottom: 16px; }

/* Grid 2 columnas para reviews y debates */
.entries-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 8px;
}
@media (max-width: 640px) {
  .entries-list { grid-template-columns: 1fr; }
}

/* Scroll horizontal entries */
.entries-scroll { scrollbar-width: thin; scrollbar-color: #2a3240 transparent; }
.entries-scroll::-webkit-scrollbar { height: 3px; }
.entries-scroll::-webkit-scrollbar-thumb { background: #2a3240; border-radius: 4px; }

/* Tooltip de acciones */
.action-btn:hover .action-tooltip {
  opacity: 1;
}
.action-tooltip {
  position: absolute;
  bottom: calc(100% + 6px);
  left: 50%;
  transform: translateX(-50%);
  background: #0d1117;
  color: #e2e8f0;
  font-size: 9px;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  padding: 4px 8px;
  border-radius: 6px;
  border: 1px solid rgba(255,255,255,0.1);
  white-space: nowrap;
  opacity: 0;
  transition: opacity 0.15s ease;
  pointer-events: none;
  z-index: 50;
}
.action-tooltip::after {
  content: '';
  position: absolute;
  top: 100%;
  left: 50%;
  transform: translateX(-50%);
  border: 4px solid transparent;
  border-top-color: #0d1117;
}

/* Poster stack para listas */
.entry-poster-wrap { height: 110px; }
.entry-poster-stack { display: flex; height: 110px; position: relative; }
.entry-poster-item { position: relative; width: 76px; height: 110px; margin-left: -55px; transition: transform 0.35s ease; }
.entry-poster-item:first-child { margin-left: 0; }
.entry-poster-img { width: 76px; height: 110px; object-fit: cover; border: 1.5px solid #0f1113; border-radius: 6px; box-shadow: 8px 0 20px rgba(0,0,0,0.6); }
.group:hover .entry-poster-item { transform: translateY(-6px) rotate(-1deg); }
</style>