<script setup>
import { ref, watch, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/services/api'
import { avatarUrl } from '@/composables/useAvatar'

const route  = useRoute()
const router = useRouter()

const query   = ref(route.query.q || '')
const results = ref({ films: [], users: [], entries: [], posts: [] })
const loading = ref(false)

const totalFilms   = computed(() => results.value.films.length)
const totalUsers   = computed(() => results.value.users.length)
const totalDebates = computed(() => results.value.entries.filter(e => e.type === 'user_debate').length)
const totalLists   = computed(() => results.value.entries.filter(e => e.type === 'user_list').length)
const totalReviews = computed(() => results.value.entries.filter(e => e.type === 'user_review').length)
const totalPosts   = computed(() => results.value.posts.length)

const activeFilter = ref('todos')

const allResults = computed(() => [
  ...results.value.films,
  ...results.value.users,
  ...results.value.entries,
  ...results.value.posts,
])

const filteredResults = computed(() => {
  if (activeFilter.value === 'todos')    return allResults.value
  if (activeFilter.value === 'films')    return results.value.films
  if (activeFilter.value === 'reviews')  return results.value.entries.filter(e => e.type === 'user_review')
  if (activeFilter.value === 'debates')  return results.value.entries.filter(e => e.type === 'user_debate')
  if (activeFilter.value === 'listas')   return results.value.entries.filter(e => e.type === 'user_list')
  if (activeFilter.value === 'noticias') return results.value.posts
  if (activeFilter.value === 'comunidad') return results.value.users
  return allResults.value
})

const fetchResults = async () => {
  const q = query.value.trim()
  if (q.length < 2) { results.value = { films: [], users: [], entries: [], posts: [] }; return }
  loading.value = true
  try {
    const { data } = await api.get('/search', { params: { q } })
    results.value = data.data
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const goFilm    = (id) => router.push({ name: 'film-detail', params: { id } })
const goUser    = (name) => router.push({ name: 'user-profile', params: { username: name } })
const goEntry   = (type, id) => router.push(`/entry/${type}/${id}`)
const goPost    = (id) => router.push(`/post-reed/${id}`)

const filterByCategory = (cat) => {
  activeFilter.value = activeFilter.value === cat ? 'todos' : cat
}

const handleKeydown = (e) => {
  if (e.key === 'Enter') {
    router.replace({ query: { q: query.value } })
    fetchResults()
  }
}

const typeLabel = (type) => {
  if (type === 'film')        return 'Film'
  if (type === 'user')        return 'Usuario'
  if (type === 'user_debate') return 'Debate'
  if (type === 'user_list')   return 'Lista'
  if (type === 'user_review') return 'Review'
  if (type === 'post')        return 'Noticia'
  return type
}

const typeColor = (type) => {
  if (type === 'film')        return 'text-brand'
  if (type === 'user')        return 'text-emerald-400'
  if (type === 'user_debate') return 'text-orange-400'
  if (type === 'user_list')   return 'text-yellow-500'
  if (type === 'user_review') return 'text-red-400'
  if (type === 'post')        return 'text-slate-400'
  return 'text-slate-400'
}

const goToResult = (item) => {
  if (item.type === 'film')        goFilm(item.id)
  else if (item.type === 'user')   goUser(item.name)
  else if (item.type === 'post')   goPost(item.id)
  else                             goEntry(item.type, item.id)
}

watch(() => route.query.q, (val) => {
  query.value = val || ''
  activeFilter.value = 'todos'
  fetchResults()
})

onMounted(fetchResults)
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20">
    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-12">

      <!-- Header search -->
      <header class="mb-12">
        <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-600 mb-3">Búsqueda global</p>
        <div class="relative max-w-2xl">
          <input
            v-model="query"
            @keydown="handleKeydown"
            @input="fetchResults"
            type="search"
            placeholder="Buscar películas, usuarios, debates, reviews, noticias…"
            class="w-full bg-slate-900/60 border border-slate-700 rounded-xl px-5 py-4 text-xl font-black text-white
                   placeholder:text-slate-600 placeholder:font-normal focus:outline-none focus:ring-2 focus:ring-brand
                   uppercase italic tracking-tight"
            autofocus
          />
          <div v-if="loading" class="absolute right-4 top-1/2 -translate-y-1/2">
            <div class="w-4 h-4 border-2 border-slate-600 border-t-brand rounded-full animate-spin"></div>
          </div>
        </div>
        <p v-if="query.trim().length >= 2" class="mt-3 text-[11px] text-slate-500 uppercase tracking-widest font-bold">
          {{ allResults.length }} resultados para
          <span class="text-white">"{{ query }}"</span>
        </p>
      </header>

      <!-- Mensaje inicial -->
      <div v-if="query.trim().length < 2" class="flex flex-col items-center justify-center py-24 gap-4 opacity-40">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-16 h-16 text-slate-600">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
        <p class="text-slate-500 text-[11px] uppercase tracking-[0.3em] font-bold">Escribe al menos 2 caracteres</p>
      </div>

      <!-- Resultados + categorías -->
      <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-10">

        <!-- IZQUIERDA: resultados mezclados -->
        <div class="lg:col-span-8 order-2 lg:order-1">

          <div v-if="loading && allResults.length === 0" class="grid grid-cols-2 sm:grid-cols-3 gap-6">
            <div v-for="i in 6" :key="i" class="h-40 bg-slate-800 rounded-xl animate-pulse"></div>
          </div>

          <div v-else-if="allResults.length === 0" class="py-16 border border-dashed border-slate-800 rounded-xl text-center opacity-50">
            <p class="text-slate-500 text-[11px] uppercase tracking-[0.2em] font-bold">Sin resultados para "{{ query }}"</p>
          </div>

          <div v-else class="flex flex-col gap-3">
            <div
              v-for="item in filteredResults" :key="`${item.type}-${item.id}`"
              @click="goToResult(item)"
              class="flex items-center gap-4 p-3 rounded-xl border border-slate-800/50 hover:border-slate-700 hover:bg-slate-900/40 cursor-pointer transition-all group"
            >
              <!-- Miniatura -->
              <div class="flex-shrink-0 w-16 h-24 rounded-lg overflow-hidden bg-slate-800 border border-slate-700">
                <template v-if="item.type === 'user'">
                  <div class="w-full h-full flex items-center justify-center bg-slate-700">
                    <img
                      v-if="avatarUrl(item.avatar)"
                      :src="avatarUrl(item.avatar)"
                      class="w-full h-full object-cover"
                    />
                    <span v-else class="text-lg font-black text-white">{{ item.title[0]?.toUpperCase() }}</span>
                  </div>
                </template>
                <template v-else>
                  <img
                    v-if="item.frame"
                    :src="item.frame"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                    loading="lazy"
                  />
                  <div v-else class="w-full h-full flex items-center justify-center bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-slate-600">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75.125a1.125 1.125 0 01-1.125-1.125v-1.5c0-.621.504-1.125 1.125-1.125" />
                    </svg>
                  </div>
                </template>
              </div>

              <!-- Info -->
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1">
                  <span :class="['text-[8px] font-black uppercase tracking-[2px]', typeColor(item.type)]">
                    {{ typeLabel(item.type) }}
                  </span>
                  <span v-if="item.year" class="text-[8px] text-slate-600 font-bold">{{ item.year }}</span>
                </div>
                <h3 class="text-sm font-black text-white truncate group-hover:text-brand transition-colors uppercase tracking-tight">
                  {{ item.title }}
                </h3>
                <p v-if="item.user && item.type !== 'user'" class="text-[10px] text-slate-500 mt-0.5 truncate">
                  {{ item.user }}
                </p>
                <p v-if="item.genre" class="text-[10px] text-slate-500 mt-0.5 truncate">{{ item.genre }}</p>
              </div>

              <!-- Arrow -->
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="flex-shrink-0 w-4 h-4 text-slate-700 group-hover:text-slate-400 transition-colors">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
              </svg>
            </div>
          </div>
        </div>

        <!-- DERECHA: buscar por categoría -->
        <aside class="lg:col-span-4 order-1 lg:order-2">
          <div class="lg:sticky lg:top-24">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-500 mb-3 border-b border-slate-800 pb-3">
              Filtrar por categoría
            </p>

            <!-- Móvil: scroll horizontal -->
            <div class="flex lg:hidden gap-2 overflow-x-auto pb-2 brand-scroll-h">
              <button @click="activeFilter = 'todos'"
                :class="['flex-shrink-0 px-3 py-2 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-all',
                  activeFilter === 'todos' ? 'border-slate-500 bg-slate-800/70 text-white' : 'border-slate-800 text-slate-400']">
                Todos <span class="ml-1 text-slate-500">{{ allResults.length }}</span>
              </button>
              <button v-if="totalFilms > 0" @click="filterByCategory('films')"
                :class="['flex-shrink-0 px-3 py-2 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-all',
                  activeFilter === 'films' ? 'border-brand/60 bg-slate-900/70 text-white' : 'border-slate-800 text-slate-400']">
                Films <span class="ml-1 text-brand">{{ totalFilms }}</span>
              </button>
              <button v-if="totalReviews > 0" @click="filterByCategory('reviews')"
                :class="['flex-shrink-0 px-3 py-2 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-all',
                  activeFilter === 'reviews' ? 'border-red-500/50 bg-slate-900/70 text-white' : 'border-slate-800 text-slate-400']">
                Reviews <span class="ml-1 text-red-400">{{ totalReviews }}</span>
              </button>
              <button v-if="totalDebates > 0" @click="filterByCategory('debates')"
                :class="['flex-shrink-0 px-3 py-2 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-all',
                  activeFilter === 'debates' ? 'border-orange-500/50 bg-slate-900/70 text-white' : 'border-slate-800 text-slate-400']">
                Debates <span class="ml-1 text-orange-400">{{ totalDebates }}</span>
              </button>
              <button v-if="totalLists > 0" @click="filterByCategory('listas')"
                :class="['flex-shrink-0 px-3 py-2 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-all',
                  activeFilter === 'listas' ? 'border-yellow-500/50 bg-slate-900/70 text-white' : 'border-slate-800 text-slate-400']">
                Listas <span class="ml-1 text-yellow-500">{{ totalLists }}</span>
              </button>
              <button v-if="totalPosts > 0" @click="filterByCategory('noticias')"
                :class="['flex-shrink-0 px-3 py-2 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-all',
                  activeFilter === 'noticias' ? 'border-slate-500/50 bg-slate-900/70 text-white' : 'border-slate-800 text-slate-400']">
                Noticias <span class="ml-1 text-slate-400">{{ totalPosts }}</span>
              </button>
              <button v-if="totalUsers > 0" @click="filterByCategory('comunidad')"
                :class="['flex-shrink-0 px-3 py-2 rounded-lg border text-[10px] font-black uppercase tracking-widest transition-all',
                  activeFilter === 'comunidad' ? 'border-emerald-500/50 bg-slate-900/70 text-white' : 'border-slate-800 text-slate-400']">
                Usuarios <span class="ml-1 text-emerald-400">{{ totalUsers }}</span>
              </button>
            </div>

            <!-- Desktop: lista vertical -->
            <div class="hidden lg:flex flex-col gap-2">
              <button
                @click="activeFilter = 'todos'"
                :class="['flex items-center justify-between px-4 py-3 rounded-lg border transition-all text-left',
                  activeFilter === 'todos'
                    ? 'border-slate-500 bg-slate-800/70'
                    : 'border-slate-800 hover:border-slate-600 hover:bg-slate-900/50']"
              >
                <span :class="['text-[11px] font-black uppercase tracking-widest transition-colors', activeFilter === 'todos' ? 'text-white' : 'text-slate-300']">Todos</span>
                <span class="text-[10px] font-black text-slate-400">{{ allResults.length }}</span>
              </button>

              <button
                @click="filterByCategory('films')"
                :class="['flex items-center justify-between px-4 py-3 rounded-lg border transition-all text-left',
                  activeFilter === 'films'
                    ? 'border-brand/60 bg-slate-900/70'
                    : 'border-slate-800 hover:border-brand/50 hover:bg-slate-900/50']"
              >
                <span :class="['text-[11px] font-black uppercase tracking-widest transition-colors', activeFilter === 'films' ? 'text-white' : 'text-slate-300']">Films</span>
                <span v-if="totalFilms > 0" class="text-[10px] font-black text-brand">{{ totalFilms }}</span>
              </button>

              <button
                @click="filterByCategory('reviews')"
                :class="['flex items-center justify-between px-4 py-3 rounded-lg border transition-all text-left',
                  activeFilter === 'reviews'
                    ? 'border-red-500/50 bg-slate-900/70'
                    : 'border-slate-800 hover:border-red-500/30 hover:bg-slate-900/50']"
              >
                <span :class="['text-[11px] font-black uppercase tracking-widest transition-colors', activeFilter === 'reviews' ? 'text-white' : 'text-slate-300']">Reviews</span>
                <span v-if="totalReviews > 0" class="text-[10px] font-black text-red-400">{{ totalReviews }}</span>
              </button>

              <button
                @click="filterByCategory('debates')"
                :class="['flex items-center justify-between px-4 py-3 rounded-lg border transition-all text-left',
                  activeFilter === 'debates'
                    ? 'border-orange-500/50 bg-slate-900/70'
                    : 'border-slate-800 hover:border-orange-500/30 hover:bg-slate-900/50']"
              >
                <span :class="['text-[11px] font-black uppercase tracking-widest transition-colors', activeFilter === 'debates' ? 'text-white' : 'text-slate-300']">Debates</span>
                <span v-if="totalDebates > 0" class="text-[10px] font-black text-orange-400">{{ totalDebates }}</span>
              </button>

              <button
                @click="filterByCategory('listas')"
                :class="['flex items-center justify-between px-4 py-3 rounded-lg border transition-all text-left',
                  activeFilter === 'listas'
                    ? 'border-yellow-500/50 bg-slate-900/70'
                    : 'border-slate-800 hover:border-yellow-500/30 hover:bg-slate-900/50']"
              >
                <span :class="['text-[11px] font-black uppercase tracking-widest transition-colors', activeFilter === 'listas' ? 'text-white' : 'text-slate-300']">Listas</span>
                <span v-if="totalLists > 0" class="text-[10px] font-black text-yellow-500">{{ totalLists }}</span>
              </button>

              <button
                @click="filterByCategory('noticias')"
                :class="['flex items-center justify-between px-4 py-3 rounded-lg border transition-all text-left',
                  activeFilter === 'noticias'
                    ? 'border-slate-500/50 bg-slate-900/70'
                    : 'border-slate-800 hover:border-slate-500/30 hover:bg-slate-900/50']"
              >
                <span :class="['text-[11px] font-black uppercase tracking-widest transition-colors', activeFilter === 'noticias' ? 'text-white' : 'text-slate-300']">Noticias</span>
                <span v-if="totalPosts > 0" class="text-[10px] font-black text-slate-400">{{ totalPosts }}</span>
              </button>

              <button
                @click="filterByCategory('comunidad')"
                :class="['flex items-center justify-between px-4 py-3 rounded-lg border transition-all text-left',
                  activeFilter === 'comunidad'
                    ? 'border-emerald-500/50 bg-slate-900/70'
                    : 'border-slate-800 hover:border-emerald-500/30 hover:bg-slate-900/50']"
              >
                <span :class="['text-[11px] font-black uppercase tracking-widest transition-colors', activeFilter === 'comunidad' ? 'text-white' : 'text-slate-300']">Usuarios</span>
                <span v-if="totalUsers > 0" class="text-[10px] font-black text-emerald-400">{{ totalUsers }}</span>
              </button>
            </div>
          </div>
        </aside>
      </div>

    </div>
  </div>
</template>

<style scoped>
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

.brand-scroll-h::-webkit-scrollbar { height: 0px; }
</style>
