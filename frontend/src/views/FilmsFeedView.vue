<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const router = useRouter()

const trendingFilms = ref([])
const allFilms = ref([])
const isLoadingTrending = ref(true)
const isLoadingAll = ref(true)
const currentPage = ref(1)
const lastPage = ref(1)
const isLoadingMore = ref(false)

const fetchTrending = async () => {
  try {
    const { data } = await api.get('/films/trending', { params: { per_page: 20 } })
    trendingFilms.value = data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    isLoadingTrending.value = false
  }
}

const fetchAllFilms = async (loadMore = false) => {
  if (loadMore) {
    isLoadingMore.value = true
  } else {
    isLoadingAll.value = true
    allFilms.value = []
    currentPage.value = 1
  }

  try {
    const { data } = await api.get('/films', { params: { page: currentPage.value, per_page: 24 } })
    const pagination = data.data
    allFilms.value = [...allFilms.value, ...(pagination.data || [])]
    currentPage.value = pagination.current_page
    lastPage.value = pagination.last_page
  } catch (e) {
    console.error(e)
  } finally {
    isLoadingAll.value = false
    isLoadingMore.value = false
  }
}

const loadMore = async () => {
  if (currentPage.value >= lastPage.value || isLoadingMore.value) return
  currentPage.value++
  await fetchAllFilms(true)
}

const goFilm = (id) => router.push({ name: 'film-detail', params: { id } })

onMounted(() => {
  fetchTrending()
  fetchAllFilms()
})
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20">
    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-12">

      <!-- Populares esta semana -->
      <section class="mb-20">
        <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
          <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Populares esta semana</h2>
          <span class="text-[9px] font-bold text-slate-600 uppercase tracking-widest">Actividad Global</span>
        </div>

        <div v-if="isLoadingTrending" class="flex gap-4 overflow-x-auto pb-4">
          <div v-for="i in 8" :key="i" class="flex-shrink-0 w-[140px]">
            <div class="aspect-[2/3] rounded bg-slate-800 animate-pulse"></div>
          </div>
        </div>

        <div v-else class="brand-scroll flex gap-4 overflow-x-auto pb-6">
          <div
            v-for="film in trendingFilms" :key="film.idFilm"
            class="flex-shrink-0 w-[140px] md:w-[155px] group cursor-pointer"
            @click="goFilm(film.idFilm)"
          >
            <div class="aspect-[2/3] rounded overflow-hidden border border-slate-800 group-hover:border-brand transition-all shadow-lg relative">
              <img :src="film.frame || '/default-poster.webp'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
              <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
              </div>
            </div>
            <h3 class="mt-2 text-[11px] font-bold text-slate-300 truncate group-hover:text-brand transition-colors">{{ film.title }}</h3>
            <p v-if="film.year" class="text-[9px] text-slate-600 font-bold uppercase mt-0.5">{{ film.year }}</p>
          </div>
        </div>
      </section>

      <!-- Todos los films -->
      <section>
        <div class="flex items-center justify-between mb-6 border-b border-slate-800 pb-2">
          <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Todos los films</h2>
          <span class="text-[9px] font-bold text-slate-600 uppercase tracking-widest">Más recientes primero</span>
        </div>

        <div v-if="isLoadingAll" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-4">
          <div v-for="i in 24" :key="i" class="aspect-[2/3] rounded bg-slate-800 animate-pulse"></div>
        </div>

        <div v-else>
          <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-x-4 gap-y-8">
            <div
              v-for="film in allFilms" :key="film.idFilm"
              class="group cursor-pointer"
              @click="goFilm(film.idFilm)"
            >
              <div class="aspect-[2/3] rounded overflow-hidden border border-slate-800 group-hover:border-brand transition-all shadow-lg relative">
                <img :src="film.frame || '/default-poster.webp'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" />
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-white">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                  </svg>
                </div>
              </div>
              <h3 class="mt-2 text-[10px] font-bold text-slate-300 truncate group-hover:text-brand transition-colors leading-tight">{{ film.title }}</h3>
              <p v-if="film.year" class="text-[9px] text-slate-600 font-bold uppercase mt-0.5">{{ film.year }}</p>
            </div>
          </div>

          <!-- Cargar más -->
          <div v-if="currentPage < lastPage" class="flex justify-center mt-12">
            <button
              @click="loadMore"
              :disabled="isLoadingMore"
              class="text-[10px] font-black uppercase tracking-[0.4em] text-slate-500 hover:text-white transition-colors py-3 px-10 border border-slate-700 hover:border-brand hover:bg-brand/10 rounded-full disabled:opacity-40"
            >
              {{ isLoadingMore ? 'Cargando...' : 'Cargar más' }}
            </button>
          </div>
        </div>
      </section>

    </div>
  </div>
</template>

<style scoped>
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

.brand-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.brand-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 10px; }
.brand-scroll::-webkit-scrollbar-thumb { background: var(--brand-color, #dd6a23); border-radius: 10px; }

@media (hover: none) {
  .brand-scroll::-webkit-scrollbar { height: 0; width: 0; }
}
</style>
