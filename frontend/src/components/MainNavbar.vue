<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import logoUrl from '@/assets/img/logoCineClub7.png'
import LoginModal from '@/components/LoginModal.vue'
import ChangePasswordModal from '@/components/ChangePasswordModal.vue'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const isLoginOpen          = ref(false)
const isUserMenuOpen       = ref(false)
const isChangePasswordOpen = ref(false)
const userMenuRef          = ref(null)

// Navegación
const goHome      = () => router.push({ name: 'home' })
const goFilmsFeed = () => router.push({ name: 'FilmsFeed' })
const goCommunity = () => { isUserMenuOpen.value = false; router.push({ name: 'entry-feed' }) }
const goDebates   = () => router.push({ name: 'entry-feed', query: { tab: 'user_debate' } })
const goNews      = () => router.push({ name: 'post-feed' })
const openLogin   = () => { isLoginOpen.value = true }

const goProfile = () => {
  isUserMenuOpen.value = false
  if (auth.user?.id) router.push({ name: 'user-profile', params: { id: auth.user.id } })
}

const goChangePassword = () => {
  isUserMenuOpen.value = false
  isChangePasswordOpen.value = true
}

const logout = async () => {
  isUserMenuOpen.value = false
  await auth.logout()
  router.push({ name: 'home' })
}

// Search — navega a la vista de búsqueda global
const searchQuery = ref('')

const goToSearch = () => {
  const q = searchQuery.value.trim()
  if (!q) return
  router.push({ name: 'search', query: { q } })
  searchQuery.value = ''
}

const handleSearchKey = (e) => {
  if (e.key === 'Enter') goToSearch()
}

// Cerrar menú user al hacer click fuera
const onGlobalClick = (ev) => {
  if (isUserMenuOpen.value && userMenuRef.value && !userMenuRef.value.contains(ev.target)) {
    isUserMenuOpen.value = false
  }
}

const onGlobalKeydown = (ev) => {
  if (ev.key === 'Escape') isUserMenuOpen.value = false
}

watch(() => route.fullPath, () => { isUserMenuOpen.value = false })

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
  <nav class="sticky top-0 z-40 bg-[#14181C]/70 backdrop-blur-md border-b border-white/5">
    <div class="navbar-wrap max-w-[1100px] mx-auto px-6 md:px-10 lg:px-0">
      <div class="flex items-center h-16 gap-6">

        <!-- Logo -->
        <button type="button" class="flex-shrink-0 flex items-center" @click="goHome">
          <img :src="logoUrl" alt="CinemaClub 7" class="h-10 w-auto object-contain" />
        </button>

        <!-- Nav links — estilo Watch.Rate.Debate. -->
        <div class="hidden md:flex items-center gap-1">
          <button type="button" class="nav-link" @click="goCommunity">Comunidad</button>
          <span class="text-slate-700 font-black select-none">·</span>
          <button type="button" class="nav-link" @click="goFilmsFeed">Films</button>
          <span class="text-slate-700 font-black select-none">·</span>
          <button type="button" class="nav-link" @click="goDebates">Debates</button>
          <span class="text-slate-700 font-black select-none">·</span>
          <button type="button" class="nav-link" @click="goNews">Noticias</button>
        </div>

        <!-- Search — ocupa el espacio central restante -->
        <div class="flex-1 flex justify-center">
          <div class="relative w-full max-w-[340px]">
            <input
              v-model="searchQuery"
              @keydown="handleSearchKey"
              type="search"
              placeholder="Buscar…"
              class="w-full bg-slate-900/40 border border-slate-700 rounded-lg pl-9 pr-3 py-1.5 text-sm text-slate-100
                     placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-brand"
            />
            <!-- Icono lupa -->
            <button
              type="button"
              class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
              @click="goToSearch"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Derecha: usuario o login -->
        <div class="flex-shrink-0 flex items-center">
          <div v-if="auth.isAuthenticated" class="relative" ref="userMenuRef">
            <button
              type="button"
              class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-slate-800 transition duration-150"
              @click="isUserMenuOpen = !isUserMenuOpen"
            >
              <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-sm font-black">
                {{ (auth.user?.name || 'U')[0]?.toUpperCase() }}
              </div>
              <span class="hidden sm:block text-sm font-black uppercase italic tracking-tight text-slate-100">
                {{ auth.user?.name || 'Usuario' }}
              </span>
              <svg class="hidden sm:block w-4 h-4 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.94a.75.75 0 111.08 1.04l-4.24 4.5a.75.75 0 01-1.08 0l-4.24-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
              </svg>
            </button>

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
                class="absolute right-0 mt-2 w-52 origin-top-right rounded-xl border border-slate-800 bg-slate-900/95 shadow-xl overflow-hidden"
              >
                <button type="button" class="w-full text-left px-4 py-3 text-sm text-slate-200 hover:bg-slate-800" @click="goProfile">Mi perfil</button>
                <button type="button" class="w-full text-left px-4 py-3 text-sm text-slate-200 hover:bg-slate-800" @click="goCommunity">Comunidad</button>
                <button type="button" class="w-full text-left px-4 py-3 text-sm text-slate-200 hover:bg-slate-800" @click="goChangePassword">Cambiar contraseña</button>
                <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-300 hover:bg-slate-800" @click="logout">Logout</button>
              </div>
            </Transition>
          </div>

          <button
            v-else
            type="button"
            class="px-3 py-1.5 rounded-lg border border-slate-600 text-xs font-black uppercase italic tracking-tight text-white transition duration-200 hover:bg-slate-800"
            @click="openLogin"
          >
            Login
          </button>
        </div>

      </div>
    </div>
  </nav>

  <LoginModal v-model="isLoginOpen" />
  <ChangePasswordModal v-model="isChangePasswordOpen" />
</template>

<style scoped>
.navbar-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

/* Estilo tipográfico tipo "Watch. Rate. Debate." */
.nav-link {
  font-size: 0.9rem;       /* ~14px */
  font-weight: 900;
  text-transform: uppercase;
  font-style: italic;
  letter-spacing: -0.03em;
  color: #94a3b8;           /* slate-400 */
  padding: 0.25rem 0.5rem;
  transition: color 150ms;
  white-space: nowrap;
}

.nav-link:hover {
  color: #ffffff;
}
</style>
