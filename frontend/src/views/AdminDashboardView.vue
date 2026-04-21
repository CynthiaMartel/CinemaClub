<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const auth = useAuthStore()

// --- Estado lista ---
const films = ref([])
const isLoading = ref(true)
const currentPage = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = 24

// --- Estado búsqueda ---
const searchQuery = ref('')
const isSearching = ref(false)
let searchTimeout = null

// --- Estado eliminación ---
const deletingId = ref(null)
const confirmDeleteId = ref(null)

// --- Toast ligero ---
const toast = ref(null)
function showToast(msg, type = 'ok') {
  toast.value = { msg, type }
  setTimeout(() => (toast.value = null), 3000)
}

// --- Cargar films paginados ---
async function fetchFilms(page = 1) {
  isLoading.value = true
  try {
    const { data } = await api.get('/films', { params: { page, per_page: perPage } })
    const pagination = data.data
    films.value = pagination.data || []
    currentPage.value = pagination.current_page
    lastPage.value = pagination.last_page
    total.value = pagination.total
  } catch {
    showToast('Error al cargar películas', 'err')
  } finally {
    isLoading.value = false
  }
}

// --- Búsqueda con debounce ---
function onSearchInput() {
  clearTimeout(searchTimeout)
  if (!searchQuery.value.trim()) {
    fetchFilms(1)
    return
  }
  searchTimeout = setTimeout(doSearch, 350)
}

async function doSearch() {
  isSearching.value = true
  try {
    const { data } = await api.get('/films/search', { params: { q: searchQuery.value.trim() } })
    films.value = data.data || []
    lastPage.value = 1
    currentPage.value = 1
    total.value = films.value.length
  } catch {
    showToast('Error en la búsqueda', 'err')
  } finally {
    isSearching.value = false
  }
}

function clearSearch() {
  searchQuery.value = ''
  fetchFilms(1)
}

// --- Paginación ---
function goPage(page) {
  if (page < 1 || page > lastPage.value) return
  fetchFilms(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const pageNumbers = computed(() => {
  const pages = []
  const range = 2
  for (let i = Math.max(1, currentPage.value - range); i <= Math.min(lastPage.value, currentPage.value + range); i++) {
    pages.push(i)
  }
  return pages
})

// --- CRUD ---
function goCreate() {
  router.push({ name: 'admin-film-create' })
}

function goEdit(id) {
  router.push({ name: 'admin-film-edit', params: { id } })
}

function goDetail(id) {
  router.push({ name: 'film-detail', params: { id } })
}

function askDelete(id) {
  confirmDeleteId.value = id
}

function cancelDelete() {
  confirmDeleteId.value = null
}

async function confirmDelete(id) {
  deletingId.value = id
  confirmDeleteId.value = null
  try {
    await api.delete(`/films/${id}/delete`)
    films.value = films.value.filter(f => f.idFilm !== id)
    total.value = Math.max(0, total.value - 1)
    showToast('Película eliminada correctamente')
  } catch {
    showToast('Error al eliminar la película', 'err')
  } finally {
    deletingId.value = null
  }
}

onMounted(() => {
  if (!auth.user || auth.user.idRol != 1) {
    router.replace({ name: 'home' })
    return
  }
  fetchFilms()
})
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20">

    <!-- Toast -->
    <Transition name="fade">
      <div
        v-if="toast"
        :class="[
          'fixed top-5 right-5 z-50 px-5 py-3 rounded-lg text-sm font-medium shadow-xl',
          toast.type === 'err' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'
        ]"
      >{{ toast.msg }}</div>
    </Transition>

    <!-- Modal confirmación borrado -->
    <Transition name="fade">
      <div v-if="confirmDeleteId" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm px-4">
        <div class="bg-[#1c2128] border border-slate-700 rounded-xl p-6 max-w-sm w-full">
          <h3 class="text-base font-semibold text-white mb-2">Eliminar película</h3>
          <p class="text-sm text-slate-400 mb-6">Esta acción es irreversible. ¿Seguro que quieres eliminarla?</p>
          <div class="flex gap-3 justify-end">
            <button @click="cancelDelete" class="px-4 py-2 rounded-lg text-sm text-slate-300 hover:text-white transition">Cancelar</button>
            <button @click="confirmDelete(confirmDeleteId)" class="px-4 py-2 rounded-lg text-sm font-semibold bg-red-600 hover:bg-red-500 text-white transition">Eliminar</button>
          </div>
        </div>
      </div>
    </Transition>

    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10">

      <!-- Cabecera -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500 block mb-1">Panel de administración</span>
          <h1 class="text-xl font-bold text-white">Gestión de Películas</h1>
          <p class="text-xs text-slate-500 mt-1">{{ total }} películas en la base de datos</p>
        </div>
        <button
          @click="goCreate"
          class="flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#00e054] text-[#14181c] text-sm font-bold hover:bg-[#00c94a] transition-colors flex-shrink-0"
        >
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
          Añadir película
        </button>
      </div>

      <!-- Barra de búsqueda -->
      <div class="relative mb-6">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 1 5 11a6 6 0 0 1 12 0z"/>
        </svg>
        <input
          v-model="searchQuery"
          @input="onSearchInput"
          type="text"
          placeholder="Buscar por título, título original, género..."
          class="w-full bg-[#1c2128] border border-slate-700 rounded-lg pl-10 pr-10 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-[#00e054]/60 transition"
        />
        <button v-if="searchQuery" @click="clearSearch" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Tabla/lista de films -->
      <div class="rounded-xl border border-slate-800 overflow-hidden">

        <!-- Cabecera tabla — solo desktop -->
        <div class="hidden sm:grid table-cols gap-x-4 px-4 py-2.5 bg-[#1c2128] border-b border-slate-800">
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">ID</span>
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Título</span>
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 text-center">Año</span>
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 hidden md:block">Género</span>
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 text-center">Nota</span>
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 text-right">Acciones</span>
        </div>

        <!-- Loading skeleton -->
        <div v-if="isLoading || isSearching">
          <!-- Skeleton móvil -->
          <div v-for="i in 6" :key="i" class="sm:hidden flex items-center gap-3 px-4 py-3 border-b border-slate-800/50 animate-pulse">
            <div class="w-8 h-11 rounded bg-slate-800 flex-shrink-0"></div>
            <div class="flex-1 space-y-2">
              <div class="h-3 bg-slate-800 rounded w-3/4"></div>
              <div class="h-2.5 bg-slate-800 rounded w-1/3"></div>
            </div>
            <div class="w-16 h-7 bg-slate-800 rounded flex-shrink-0"></div>
          </div>
          <!-- Skeleton desktop -->
          <div v-for="i in 8" :key="'d'+i" class="hidden sm:grid table-cols gap-x-4 px-4 py-3.5 border-b border-slate-800/50 animate-pulse">
            <div class="h-3 bg-slate-800 rounded w-8"></div>
            <div class="h-3 bg-slate-800 rounded w-3/4"></div>
            <div class="h-3 bg-slate-800 rounded w-12 mx-auto"></div>
            <div class="h-3 bg-slate-800 rounded w-20 hidden md:block"></div>
            <div class="h-3 bg-slate-800 rounded w-8 mx-auto"></div>
            <div class="h-3 bg-slate-800 rounded w-16 ml-auto"></div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="films.length === 0" class="py-16 text-center">
          <p class="text-slate-500 text-sm">No se encontraron películas.</p>
          <button v-if="searchQuery" @click="clearSearch" class="mt-3 text-xs text-[#00e054] hover:underline">Limpiar búsqueda</button>
        </div>

        <!-- Filas -->
        <div v-else>
          <div
            v-for="film in films"
            :key="film.idFilm"
            class="group border-b border-slate-800/50 hover:bg-white/[0.02] transition-colors"
          >
            <!-- MÓVIL: tarjeta horizontal -->
            <div class="sm:hidden flex items-center gap-3 px-4 py-3">
              <div class="w-9 h-12 rounded flex-shrink-0 overflow-hidden bg-slate-800">
                <img v-if="film.frame" :src="film.frame" :alt="film.title" class="w-full h-full object-cover" loading="lazy" />
              </div>
              <div class="flex-1 min-w-0">
                <button @click="goDetail(film.idFilm)" class="text-sm font-medium text-slate-100 hover:text-[#00e054] transition truncate block text-left w-full">
                  {{ film.title }}
                </button>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="text-xs text-slate-500">{{ film.year || '—' }}</span>
                  <span v-if="film.genre" class="text-xs text-slate-600 truncate">· {{ film.genre }}</span>
                </div>
              </div>
              <div class="flex items-center gap-1 flex-shrink-0">
                <button @click="goEdit(film.idFilm)" title="Editar" class="p-2 rounded text-slate-500 hover:text-[#00e054] hover:bg-[#00e054]/10 transition">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button v-if="deletingId === film.idFilm" disabled class="p-2 rounded text-slate-600">
                  <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                </button>
                <button v-else @click="askDelete(film.idFilm)" title="Eliminar" class="p-2 rounded text-slate-500 hover:text-red-400 hover:bg-red-400/10 transition">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </div>

            <!-- DESKTOP: fila de tabla -->
            <div class="hidden sm:grid table-cols gap-x-4 px-4 py-3 items-center">
              <span class="text-xs text-slate-600 font-mono">{{ film.idFilm }}</span>

              <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-11 rounded flex-shrink-0 overflow-hidden bg-slate-800">
                  <img v-if="film.frame" :src="film.frame" :alt="film.title" class="w-full h-full object-cover" loading="lazy" />
                </div>
                <div class="min-w-0">
                  <button @click="goDetail(film.idFilm)" class="text-sm font-medium text-slate-100 hover:text-[#00e054] transition truncate block text-left max-w-[220px]">
                    {{ film.title }}
                  </button>
                  <span v-if="film.original_title && film.original_title !== film.title" class="text-xs text-slate-500 truncate block max-w-[220px]">{{ film.original_title }}</span>
                </div>
              </div>

              <span class="text-xs text-slate-400 text-center">{{ film.year || '—' }}</span>
              <span class="text-xs text-slate-400 truncate hidden md:block">{{ film.genre || '—' }}</span>
              <span class="text-xs text-center" :class="film.vote_average ? 'text-[#00e054]' : 'text-slate-600'">
                {{ film.vote_average ? film.vote_average.toFixed(1) : '—' }}
              </span>

              <div class="flex items-center justify-end gap-1.5">
                <button @click="goEdit(film.idFilm)" title="Editar" class="p-1.5 rounded text-slate-500 hover:text-[#00e054] hover:bg-[#00e054]/10 transition">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
                <button v-if="deletingId === film.idFilm" disabled class="p-1.5 rounded text-slate-600">
                  <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                </button>
                <button v-else @click="askDelete(film.idFilm)" title="Eliminar" class="p-1.5 rounded text-slate-500 hover:text-red-400 hover:bg-red-400/10 transition">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Paginación (solo cuando no hay búsqueda activa) -->
      <div v-if="!searchQuery && lastPage > 1" class="flex items-center justify-center gap-1.5 mt-8">
        <button @click="goPage(currentPage - 1)" :disabled="currentPage === 1" class="px-3 py-1.5 rounded text-xs text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition">← Anterior</button>

        <button v-if="pageNumbers[0] > 1" @click="goPage(1)" class="px-3 py-1.5 rounded text-xs text-slate-400 hover:text-white transition">1</button>
        <span v-if="pageNumbers[0] > 2" class="text-slate-600 text-xs px-1">…</span>

        <button
          v-for="p in pageNumbers"
          :key="p"
          @click="goPage(p)"
          :class="[
            'px-3 py-1.5 rounded text-xs font-medium transition',
            p === currentPage ? 'bg-[#00e054] text-[#14181c]' : 'text-slate-400 hover:text-white'
          ]"
        >{{ p }}</button>

        <span v-if="pageNumbers[pageNumbers.length - 1] < lastPage - 1" class="text-slate-600 text-xs px-1">…</span>
        <button v-if="pageNumbers[pageNumbers.length - 1] < lastPage" @click="goPage(lastPage)" class="px-3 py-1.5 rounded text-xs text-slate-400 hover:text-white transition">{{ lastPage }}</button>

        <button @click="goPage(currentPage + 1)" :disabled="currentPage === lastPage" class="px-3 py-1.5 rounded text-xs text-slate-400 hover:text-white disabled:opacity-30 disabled:cursor-not-allowed transition">Siguiente →</button>
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

/* Columnas de la tabla: ID | Título | Año | Género(md+) | Nota | Acciones */
.table-cols {
  display: grid;
  grid-template-columns: 52px 1fr 64px 0px 64px 88px;
}
@media (min-width: 768px) {
  .table-cols {
    grid-template-columns: 52px 1fr 64px 110px 64px 88px;
  }
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
