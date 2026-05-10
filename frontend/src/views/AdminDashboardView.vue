<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
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
const perPage = 12

// --- Monitor de cola ---
const monitorOpen    = ref(false)
const monitorActive  = ref(false)
const monitorData    = ref(null)
const monitorError   = ref('')
const monitorLoading = ref(false)
let   monitorTimer   = null

async function fetchQueueStatus() {
  monitorLoading.value = true
  monitorError.value   = ''
  try {
    const { data } = await api.get('/admin/queue-status')
    monitorData.value = data
  } catch (err) {
    monitorError.value = err?.response?.data?.message || 'Error al consultar la cola'
  } finally {
    monitorLoading.value = false
  }
}

function startMonitor() {
  monitorActive.value = true
  fetchQueueStatus()
  monitorTimer = setInterval(fetchQueueStatus, 7000)
}

function stopMonitor() {
  monitorActive.value = false
  clearInterval(monitorTimer)
  monitorTimer = null
}

function toggleMonitor() {
  monitorActive.value ? stopMonitor() : startMonitor()
}

function onVisibilityChange() {
  if (!monitorActive.value) return
  if (document.hidden) {
    clearInterval(monitorTimer)
  } else {
    fetchQueueStatus()
    monitorTimer = setInterval(fetchQueueStatus, 7000)
  }
}

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
  document.addEventListener('visibilitychange', onVisibilityChange)
})

onUnmounted(() => {
  clearInterval(monitorTimer)
  document.removeEventListener('visibilitychange', onVisibilityChange)
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

      <!-- ── Monitor de importación ───────────────────────────────────── -->
      <section class="bg-[#0d1117] border border-slate-700/50 rounded-xl mb-6 overflow-hidden">

        <!-- Cabecera (siempre visible) -->
        <div class="flex items-center justify-between px-4 py-3 cursor-pointer select-none" @click="monitorOpen = !monitorOpen">
          <div class="flex items-center gap-3 min-w-0">
            <div :class="['w-2 h-2 rounded-full flex-shrink-0 transition-colors',
              !monitorData         ? 'bg-slate-600' :
              monitorData.failed_total > 0  ? 'bg-red-500 animate-pulse' :
              monitorData.pending_total > 0 ? 'bg-amber-400 animate-pulse' :
              'bg-emerald-400']"></div>
            <span class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Monitor de importación</span>
            <div v-if="monitorData" class="hidden sm:flex items-center gap-2 text-[10px]">
              <span :class="monitorData.pending_total > 0 ? 'text-amber-400' : 'text-slate-600'">
                {{ monitorData.pending_total }} en cola
              </span>
              <span class="text-slate-700">·</span>
              <span :class="monitorData.failed_total > 0 ? 'text-red-400' : 'text-slate-600'">
                {{ monitorData.failed_total }} fallido{{ monitorData.failed_total !== 1 ? 's' : '' }}
              </span>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button type="button" @click.stop="toggleMonitor"
              :class="['px-2.5 py-1 rounded-md text-[11px] font-bold border transition flex items-center gap-1.5',
                monitorActive
                  ? 'bg-emerald-900/40 text-emerald-400 border-emerald-800/50'
                  : 'bg-slate-800 text-slate-500 border-slate-700 hover:text-white']">
              <span :class="['w-1.5 h-1.5 rounded-full', monitorActive ? 'bg-emerald-400 animate-pulse' : 'bg-slate-600']"></span>
              {{ monitorActive ? 'Activo' : 'Iniciar' }}
            </button>
            <svg :class="['w-4 h-4 text-slate-600 transition-transform', monitorOpen ? 'rotate-180' : '']"
              fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </div>
        </div>

        <!-- Panel expandible -->
        <div v-if="monitorOpen" class="border-t border-slate-800 px-4 py-4 space-y-4">

          <div class="flex items-center gap-3 flex-wrap">
            <button type="button" @click="fetchQueueStatus" :disabled="monitorLoading"
              class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800 text-slate-300 text-xs hover:bg-slate-700 transition disabled:opacity-50">
              <svg :class="['w-3.5 h-3.5', monitorLoading ? 'animate-spin' : '']" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              Actualizar ahora
            </button>
            <span v-if="monitorData" class="text-[10px] text-slate-600">
              {{ new Date(monitorData.checked_at).toLocaleTimeString('es-ES') }}
            </span>
            <span v-if="monitorActive" class="text-[10px] text-emerald-700">· cada 7 s</span>
          </div>

          <p v-if="monitorError" class="text-xs text-red-400">{{ monitorError }}</p>

          <div v-if="!monitorData && !monitorLoading && !monitorError" class="text-xs text-slate-600">
            Pulsa "Iniciar" para monitoreo continuo, o "Actualizar ahora" para una consulta puntual.
          </div>

          <div v-if="monitorData" class="grid grid-cols-2 gap-3">
            <div :class="['rounded-lg p-3 border',
              monitorData.pending_total > 0
                ? 'bg-amber-900/20 border-amber-800/40'
                : 'bg-slate-800/50 border-slate-700/40']">
              <p class="text-[10px] uppercase tracking-widest text-slate-500 mb-1">En cola</p>
              <p :class="['text-2xl font-bold', monitorData.pending_total > 0 ? 'text-amber-400' : 'text-slate-500']">
                {{ monitorData.pending_total }}
              </p>
              <div v-if="Object.keys(monitorData.pending_by_class).length" class="mt-2 space-y-1">
                <div v-for="(count, cls) in monitorData.pending_by_class" :key="cls"
                  class="flex items-center justify-between text-[11px]">
                  <span class="text-slate-500 truncate">{{ cls }}</span>
                  <span class="text-amber-500 font-bold ml-2 flex-shrink-0">{{ count }}</span>
                </div>
              </div>
            </div>
            <div :class="['rounded-lg p-3 border',
              monitorData.failed_total > 0
                ? 'bg-red-900/20 border-red-800/40'
                : 'bg-slate-800/50 border-slate-700/40']">
              <p class="text-[10px] uppercase tracking-widest text-slate-500 mb-1">Fallidos</p>
              <p :class="['text-2xl font-bold', monitorData.failed_total > 0 ? 'text-red-400' : 'text-slate-500']">
                {{ monitorData.failed_total }}
              </p>
            </div>
          </div>

          <div v-if="monitorData?.failed_recent?.length" class="space-y-2">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Últimos errores</p>
            <div v-for="job in monitorData.failed_recent" :key="job.id"
              class="bg-red-950/20 border border-red-900/30 rounded-lg px-3 py-2.5 space-y-1">
              <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-medium text-red-300">{{ job.class }}</span>
                <span class="text-[10px] text-slate-600 flex-shrink-0">{{ job.failed_at }}</span>
              </div>
              <p class="text-[11px] text-slate-500 font-mono break-all leading-relaxed">{{ job.message }}</p>
            </div>
          </div>
          <div v-else-if="monitorData?.failed_total === 0"
            class="flex items-center gap-2 text-xs text-emerald-600">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Sin errores en la cola
          </div>

          <!-- Importadas recientemente -->
          <div v-if="monitorData" class="space-y-2">
            <div class="flex items-center justify-between">
              <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                Importadas últimos 14 días
              </p>
              <span class="text-[10px] text-slate-600">
                {{ monitorData.recent_total }} película{{ monitorData.recent_total !== 1 ? 's' : '' }}
              </span>
            </div>
            <div v-if="monitorData.recently_imported?.length"
              class="max-h-64 overflow-y-auto space-y-1 pr-1">
              <div v-for="film in monitorData.recently_imported" :key="film.id"
                class="flex items-center gap-3 bg-slate-800/50 border border-slate-700/30 rounded-lg px-3 py-2">
                <img v-if="film.frame" :src="film.frame" :alt="film.title"
                  class="w-7 h-10 object-cover rounded flex-shrink-0 bg-slate-700" loading="lazy"/>
                <div v-else class="w-7 h-10 rounded flex-shrink-0 bg-slate-700/60 flex items-center justify-center">
                  <svg class="w-3 h-3 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                  </svg>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-xs text-slate-200 truncate font-medium">{{ film.title }}</p>
                  <p class="text-[10px] text-slate-500">{{ film.year }}</p>
                </div>
                <span class="text-[10px] text-slate-600 flex-shrink-0">
                  {{ new Date(film.imported_at).toLocaleDateString('es-ES', { day:'2-digit', month:'short' }) }}
                </span>
              </div>
            </div>
            <p v-else class="text-xs text-slate-600">No se han importado películas en los últimos 14 días.</p>
          </div>

        </div>
      </section>

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
