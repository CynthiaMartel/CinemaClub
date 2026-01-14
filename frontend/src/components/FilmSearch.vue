<template>
  <div class="relative w-full max-w-[400px]" ref="searchWrapRef">
    <div class="relative">
      <input
        v-model="searchQuery"
        @input="fetchSearch"
        @focus="isSearchOpen = true"
        type="search"
        placeholder="Buscar películas por título..."
        class="w-full bg-slate-900/40 border border-slate-700 rounded-lg px-3 py-2.5 text-sm text-slate-100
               placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#00c020] transition-all"
      />
      
      <div v-if="isSearching" class="absolute right-3 top-3">
        <svg class="animate-spin h-4 w-4 text-[#00c020]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
    </div>

    <div
      v-if="isSearchOpen && searchResults.length"
      class="absolute mt-2 w-full rounded-xl border border-slate-800 bg-slate-950 shadow-2xl overflow-hidden z-50 animate-fade-in"
    >
      <button
        v-for="film in searchResults"
        :key="film.idFilm"
        type="button"
        class="w-full text-left px-4 py-3 hover:bg-slate-800 flex items-center gap-3 border-b border-slate-900 last:border-none transition-colors"
        @click="selectFilm(film)"
      >
        <img :src="film.frame || '/poster-placeholder.jpg'" class="w-10 h-14 object-cover rounded shadow-md" />
        <div class="flex-1 overflow-hidden">
          <div class="text-sm font-semibold text-slate-100 truncate">{{ film.title }}</div>
          <div class="text-[11px] text-slate-400 truncate italic">{{ film.original_title }}</div>
        </div>
      </button>
    </div>

    <div
      v-else-if="isSearchOpen && !searchResults.length && searchQuery.trim().length > 2 && !isSearching"
      class="absolute mt-2 w-full rounded-xl border border-slate-800 bg-slate-900 shadow-xl px-4 py-3 text-sm text-slate-400 z-50"
    >
      No se encontraron coincidencias.
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'

const emit = defineEmits(['select-film'])

const searchQuery = ref('')
const searchResults = ref([])
const isSearchOpen = ref(false)
const isSearching = ref(false)
const searchWrapRef = ref(null)
let searchTimer = null

const fetchSearch = () => {
  clearTimeout(searchTimer)
  const q = searchQuery.value.trim()

  if (q.length < 2) {
    searchResults.value = []
    isSearchOpen.value = false
    return
  }

  isSearching.value = true

  searchTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/films/search', { params: { q } })
      searchResults.value = data.data || data
      isSearchOpen.value = true
    } catch (e) {
      searchResults.value = []
    } finally {
      isSearching.value = false
    }
  }, 400) 
}

const selectFilm = (film) => {
  emit('select-film', film)
  searchQuery.value = ''
  isSearchOpen.value = false
  searchResults.value = []
}

const handleClickOutside = (event) => {
  if (searchWrapRef.value && !searchWrapRef.value.contains(event.target)) {
    isSearchOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', handleClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', handleClickOutside))
</script>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>