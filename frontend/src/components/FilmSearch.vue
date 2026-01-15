<template>
  <div class="relative w-full max-w-[450px]" ref="searchWrapRef">
    <div class="relative group">
      <input
        v-model="searchQuery"
        @input="fetchSearch"
        @focus="isSearchOpen = true"
        type="search"
        placeholder="Añadir películas..."
        class="w-full bg-slate-900/60 border border-slate-800 rounded-xl px-4 py-3 text-sm text-slate-100
               placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 transition-all backdrop-blur-md"
      />
      
      <div v-if="isSearching" class="absolute right-4 top-3.5">
        <div class="w-4 h-4 border-2 border-emerald-500/20 border-t-emerald-500 rounded-full animate-spin"></div>
      </div>
    </div>

    <div
      v-if="isSearchOpen && searchResults.length"
      class="absolute mt-3 w-full rounded-2xl border border-slate-800 bg-[#1e2227]/95 backdrop-blur-xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] overflow-hidden z-[100] animate-fade-in"
    >
      <button
        v-for="film in searchResults"
        :key="film.idFilm"
        type="button"
        class="w-full text-left px-4 py-3 hover:bg-emerald-500/10 flex items-center gap-4 border-b border-slate-800/50 last:border-none transition-colors"
        @click="selectFilm(film)"
      >
        <img :src="film.frame || film.poster_url" class="w-12 h-16 object-cover rounded shadow-lg" />
        <div class="flex-1 overflow-hidden">
          <div class="text-sm font-bold text-white truncate">{{ film.title }}</div>
          <div class="text-[10px] text-slate-500 uppercase tracking-widest font-black">
            {{ film.year || 'S/D' }} • {{ film.genre || 'Cine' }}
          </div>
        </div>
      </button>
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