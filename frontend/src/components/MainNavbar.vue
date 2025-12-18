<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import logoUrl from '@/assets/img/logoCineClub7.png'
import LoginModal from '@/components/LoginModal.vue'
import ChangePasswordModal from '@/components/ChangePasswordModal.vue'
import api from '@/services/api'


const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const isLoginOpen = ref(false)
const isUserMenuOpen = ref(false)

const isChangePasswordOpen = ref(false)
const userMenuRef = ref(null)

const goHome = () => router.push({ name: 'home' })
const goNews = () => router.push({ name: 'news' })
const goFilmsFeed = () => router.push({ name: 'FilmsFeed' })


const openLogin = () => { isLoginOpen.value = true }

const goChangePassword = () => {
  isUserMenuOpen.value = false
  isChangePasswordOpen.value = true
}

const logout = async () => {
  isUserMenuOpen.value = false
  await auth.logout()
  router.push({ name: 'home' })
}

const goFilm = (film) => {
  isSearchOpen.value = false
  searchQuery.value = ''
  
  router.push({ name: 'film-detail', params: { id: film.idFilm } })
}

// --- Search ---
const searchQuery = ref('')
const searchResults = ref([])
const isSearchOpen = ref(false)
const searchWrapRef = ref(null)
let searchTimer = null

const fetchSearch = () => {
  clearTimeout(searchTimer)

  const q = searchQuery.value.trim()
  if (!q) {
    searchResults.value = []
    isSearchOpen.value = false
    return
  }

  // debounce 250ms para no spamear el backend
  searchTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/films/search', { params: { q } })
      searchResults.value = data?.data || []
      isSearchOpen.value = true
    } catch (e) {
      searchResults.value = []
      isSearchOpen.value = false
    }
  }, 250)
}


// cerrar dropdown click fuera
const onGlobalClick = (ev) => {
  // cerrar menú user
  if (isUserMenuOpen.value && userMenuRef.value && !userMenuRef.value.contains(ev.target)) {
    isUserMenuOpen.value = false
  }

  // cerrar search dropdown
  if (isSearchOpen.value && searchWrapRef.value && !searchWrapRef.value.contains(ev.target)) {
    isSearchOpen.value = false
  }
}


// cerrar con ESC
const onGlobalKeydown = (ev) => {
  if (ev.key === 'Escape') {
    isUserMenuOpen.value = false
  }
}

// cerrar dropdown al cambiar de ruta
watch(
  () => route.fullPath,
  () => { isUserMenuOpen.value = false }
)

onMounted(() => {
  document.addEventListener('click', onGlobalClick, true)
  document.addEventListener('keydown', onGlobalKeydown)
  auth.checkSession?.()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onGlobalClick, true)
  document.removeEventListener('keydown', onGlobalKeydown)
})
</script>

<template>
  <nav class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-40 backdrop-blur">
    <div class="w-full px-4 md:px-6">
      <div class="flex items-center justify-between h-16 gap-4">
        <!-- Logo -->
        <button type="button" class="flex items-center gap-2" @click="goHome">
          <img :src="logoUrl" alt="CinemaClub 7" class="h-10 w-auto object-contain" />
        </button>

       <!-- Logo -->
      <div class="flex items-center gap-4" ref="searchWrapRef">
        <button
          type="button"
          class="text-sm font-semibold text-slate-200 hover:text-white"
          @click="goFilmsFeed"
        >
          Films
        </button>

        <!-- Search -->
        <div class="relative hidden md:block w-[360px]">
          <input
            v-model="searchQuery"
            @input="fetchSearch"
            @focus="fetchSearch"
            type="search"
            placeholder="Buscar películas…"
            class="w-full bg-slate-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-slate-100
                  placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-[#BE2B0C]"
          />

          <!-- Dropdown resultados -->
          <div
            v-if="isSearchOpen && searchResults.length"
            class="absolute mt-2 w-full rounded-xl border border-slate-800 bg-slate-900 shadow-xl overflow-hidden z-50"
          >
            <button
              v-for="film in searchResults"
              :key="film.idFilm"
              type="button"
              class="w-full text-left px-4 py-3 hover:bg-slate-800"
              @click="goFilm(film)"
            >
              <div class="text-sm font-semibold text-slate-100">
                {{ film.title }}
                <span v-if="film.year" class="text-xs text-slate-400"> ({{ film.year }})</span>
              </div>
              <div v-if="film.genre" class="text-xs text-slate-400">
                {{ film.genre }}
              </div>
            </button>
          </div>

          <div
            v-else-if="isSearchOpen && !searchResults.length && searchQuery.trim().length"
            class="absolute mt-2 w-full rounded-xl border border-slate-800 bg-slate-900 shadow-xl px-4 py-3 text-sm text-slate-300 z-50"
          >
            Sin resultados
          </div>
        </div>
      </div>

        <!-- Derecha -->
        <div class="flex items-center gap-4">
          <!-- User -->
          <div v-if="auth.isAuthenticated" class="relative" ref="userMenuRef">
            <button
              type="button"
              class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-slate-800 transition duration-150 ease-out transform hover:scale-[1.01]"
              @click="isUserMenuOpen = !isUserMenuOpen"
            >
              <div class="w-9 h-9 rounded-full bg-slate-700 flex items-center justify-center text-sm font-semibold">
                {{ (auth.user?.name || 'U')[0]?.toUpperCase() }}
              </div>
              <span class="hidden sm:block text-sm font-semibold text-slate-100">
                {{ auth.user?.name || 'Usuario' }}
              </span>
              <svg class="hidden sm:block w-4 h-4 text-slate-300" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
              </svg>
            </button>

            <!-- Dropdown animado -->
            <Transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="opacity-0 translate-y-1 scale-95"
              enter-to-class="opacity-100 translate-y-0 scale-100"
              leave-active-class="transition duration-100 ease-in"
              leave-from-class="opacity-100 translate-y-0 scale-100"
              leave-to-class="opacity-0 translate-y-1 scale-95"
            >
              <div
                v-if="isUserMenuOpen"
                class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-slate-800 bg-slate-900 shadow-xl overflow-hidden"
              >
                <button
                  type="button"
                  class="w-full text-left px-4 py-3 text-sm text-slate-200 hover:bg-slate-800"
                  @click="goChangePassword"
                >
                  Cambiar contraseña
                </button>

                <button
                  type="button"
                  class="w-full text-left px-4 py-3 text-sm text-red-300 hover:bg-slate-800"
                  @click="logout"
                >
                  Logout
                </button>
              </div>
            </Transition>
          </div>

          <!-- Login -->
          <button
            v-else
            type="button"
            class="px-3 py-1.5 rounded-lg border border-slate-600 text-sm font-semibold
                   transition duration-200 ease-out transform hover:scale-[1.03] hover:bg-slate-800"
            @click="openLogin"
          >
            Login
          </button>
        </div>
      </div>
    </div>
  </nav>

  <!-- Modal -->
  <LoginModal v-model="isLoginOpen" />
  <ChangePasswordModal v-model="isChangePasswordOpen" />
</template>



