<script setup>
import { ref, computed, nextTick, onMounted, onBeforeUnmount, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useInstallPrompt } from '@/composables/useInstallPrompt'
import logoUrl from '@/assets/img/logoCineClub7.png'
import LoginModal from '@/components/LoginModal.vue'
import ChangePasswordModal from '@/components/ChangePasswordModal.vue'
import { avatarUrl } from '@/composables/useAvatar'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const { canInstall, install } = useInstallPrompt()

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
const goEditorial = () => { isUserMenuOpen.value = false; router.push({ name: 'editorial-inbox' }) }

const isEditorOrAdmin = computed(() => {
  if (!auth.isAuthenticated || !auth.user) return false
  return auth.user.idRol === 1 || auth.user.idRol === 2
})
const openLogin   = () => { isLoginOpen.value = true }

const goProfile = () => {
  isUserMenuOpen.value = false
  if (auth.user?.name) router.push({ name: 'user-profile', params: { username: auth.user.name } })
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

// Search
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

const isScrolled = ref(false)
const updateScroll = () => { isScrolled.value = window.scrollY > 40 }

const isMobileMenuOpen = ref(false)

// Cerrar menú user al hacer click fuera
const onGlobalClick = (ev) => {
  if (isUserMenuOpen.value && userMenuRef.value && !userMenuRef.value.contains(ev.target)) {
    isUserMenuOpen.value = false
  }
}

const onGlobalKeydown = (ev) => {
  if (ev.key === 'Escape') {
    isUserMenuOpen.value = false
    isMobileMenuOpen.value = false
  }
}

watch(() => route.fullPath, () => {
  isUserMenuOpen.value = false
  isMobileMenuOpen.value = false
  isScrolled.value = false
  nextTick(updateScroll)
})

onMounted(() => {
  document.addEventListener('click', onGlobalClick, true)
  document.addEventListener('keydown', onGlobalKeydown)
  window.addEventListener('scroll', updateScroll, { passive: true })
  updateScroll()
  auth.checkSession?.()
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onGlobalClick, true)
  document.removeEventListener('keydown', onGlobalKeydown)
  window.removeEventListener('scroll', updateScroll)
})
</script>

<template>
  <!-- El nav es transparente; el gradiente se extiende por debajo para fundirse con el hero -->
  <nav class="site-header sticky top-0 z-40">

    <!-- Capa de blur/gradiente: siempre en el DOM (evita flash de composición GPU),
         solo transicionamos la opacidad. Esto corrige el destello horizontal en
         Chrome/Safari causado por crear·destruir backdrop-filter en sticky. -->
    <div class="nav-backdrop" :class="{ 'is-scrolled': isScrolled }" aria-hidden="true" />

    <div class="navbar-wrap max-w-[1100px] mx-auto px-4 sm:px-6 md:px-10 lg:px-0">
      <div class="flex items-center h-16 gap-4 md:gap-8">

        <!-- Logo -->
        <button type="button" class="flex-shrink-0 flex items-center" @click="goHome">
          <img :src="logoUrl" alt="CinemaClub 7" class="h-8 md:h-9 w-auto object-contain" />
        </button>

        <!-- Nav links — desktop -->
        <div class="hidden md:flex items-center gap-6">
          <button type="button" class="nav-link" @click="goCommunity">Comunidad</button>
          <button type="button" class="nav-link" @click="goFilmsFeed">Films</button>
          <button type="button" class="nav-link" @click="goDebates">Debates</button>
          <button type="button" class="nav-link nav-link--noticias" @click="goNews">Noticias</button>
          <button v-if="isEditorOrAdmin" type="button" class="nav-link nav-link--editorial" @click="goEditorial">Editorial</button>
        </div>

        <!-- Search — sin marco, icono integrado -->
        <div class="flex-1 flex justify-center">
          <div class="relative w-full max-w-[220px] sm:max-w-[300px]">
            <input
              v-model="searchQuery"
              @keydown="handleSearchKey"
              type="search"
              placeholder="Buscar…"
              class="search-input w-full bg-transparent pl-8 pr-3 py-1.5 text-sm text-slate-300
                     placeholder:text-slate-600 focus:outline-none"
            />
            <button
              type="button"
              class="absolute left-0 top-1/2 -translate-y-1/2 text-slate-600 hover:text-slate-400 transition-colors"
              @click="goToSearch"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Derecha: usuario o login + hamburguesa -->
        <div class="flex-shrink-0 flex items-center gap-2">
          <div v-if="auth.isAuthenticated" class="relative" ref="userMenuRef">
            <button
              type="button"
              class="flex items-center gap-2 px-2 py-1 rounded-lg hover:bg-white/5 transition duration-150"
              @click="isUserMenuOpen = !isUserMenuOpen"
            >
              <div class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center text-sm font-black text-white flex-shrink-0">
                <img
                  v-if="avatarUrl(auth.user?.avatar)"
                  :src="avatarUrl(auth.user?.avatar)"
                  class="w-full h-full object-cover"
                />
                <span v-else>{{ (auth.user?.name || 'U')[0]?.toUpperCase() }}</span>
              </div>
              <span class="hidden sm:block text-[11px] font-bold uppercase tracking-wider text-slate-300">
                {{ auth.user?.name || 'Usuario' }}
              </span>
              <svg class="hidden sm:block w-3 h-3 text-slate-500" viewBox="0 0 20 20" fill="currentColor">
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
                class="absolute right-0 mt-2 w-52 origin-top-right rounded-xl border border-white/10 bg-[#1b2228] shadow-xl overflow-hidden"
              >
                <button type="button" class="w-full text-left px-4 py-3 text-sm text-slate-200 hover:bg-slate-800" @click="goProfile">Mi perfil</button>
                <button type="button" class="w-full text-left px-4 py-3 text-sm text-slate-200 hover:bg-slate-800" @click="goCommunity">Comunidad</button>
                <button v-if="isEditorOrAdmin" type="button" class="w-full text-left px-4 py-3 text-sm text-slate-200 hover:bg-slate-800" @click="goEditorial">Panel editorial</button>
                <button type="button" class="w-full text-left px-4 py-3 text-sm text-slate-200 hover:bg-slate-800" @click="goChangePassword">Cambiar contraseña</button>
                <button type="button" class="w-full text-left px-4 py-3 text-sm text-red-300 hover:bg-slate-800" @click="logout">Logout</button>
              </div>
            </Transition>
          </div>

          <button
            v-else
            type="button"
            class="text-[11px] font-bold uppercase tracking-wider text-slate-400 hover:text-white transition-colors"
            @click="openLogin"
          >
            Entrar
          </button>

          <!-- Botón instalar PWA — solo aparece cuando el browser lo soporta y no está instalada -->
          <button
            v-if="canInstall"
            type="button"
            class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-white/10
                   text-[10px] font-bold uppercase tracking-wider text-slate-400 hover:text-white
                   hover:border-white/20 transition-colors"
            @click="install"
            aria-label="Instalar CinemaClub"
          >
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Instalar
          </button>

          <!-- Hamburguesa — solo móvil -->
          <button
            type="button"
            class="md:hidden flex flex-col justify-center items-center w-8 h-8 gap-1.5 ml-1"
            :aria-label="isMobileMenuOpen ? 'Cerrar menú' : 'Abrir menú'"
            @click="isMobileMenuOpen = !isMobileMenuOpen"
          >
            <span class="block w-5 h-0.5 bg-slate-400 transition-all duration-200"
              :class="isMobileMenuOpen ? 'rotate-45 translate-y-2' : ''" />
            <span class="block w-5 h-0.5 bg-slate-400 transition-all duration-200"
              :class="isMobileMenuOpen ? 'opacity-0' : ''" />
            <span class="block w-5 h-0.5 bg-slate-400 transition-all duration-200"
              :class="isMobileMenuOpen ? '-rotate-45 -translate-y-2' : ''" />
          </button>
        </div>

      </div>
    </div>

    <!-- Menú móvil -->
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 -translate-y-2"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-2"
    >
      <div
        v-if="isMobileMenuOpen"
        class="md:hidden absolute left-0 right-0 top-16 z-50 bg-[#14181c]/95 backdrop-blur-md border-b border-white/10 shadow-2xl"
      >
        <nav class="flex flex-col px-6 py-4 gap-1">
          <button type="button" class="mobile-nav-link" @click="goCommunity">Comunidad</button>
          <button type="button" class="mobile-nav-link" @click="goFilmsFeed">Films</button>
          <button type="button" class="mobile-nav-link" @click="goDebates">Debates</button>
          <button type="button" class="mobile-nav-link mobile-nav-link--noticias" @click="goNews">Noticias</button>
          <button v-if="isEditorOrAdmin" type="button" class="mobile-nav-link mobile-nav-link--editorial" @click="goEditorial">Editorial</button>
        </nav>
      </div>
    </Transition>
  </nav>

  <LoginModal v-model="isLoginOpen" />
  <ChangePasswordModal v-model="isChangePasswordOpen" />
</template>

<style scoped>
.navbar-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
  /* Asegura que el contenido quede encima de la capa de blur */
  position: relative;
  z-index: 1;
}

.site-header {
  position: sticky;
  top: 0;
  z-index: 40;
  /* Sin overflow:hidden para no romper position:sticky en algunos browsers */
  overflow: visible;
}

/*
  Capa de desenfoque/gradiente que se superpone DETRÁS del contenido del nav.
  - backdrop-filter siempre activo (evita que Chrome/Safari reconstruya la
    capa GPU y genere el destello horizontal al hacer scroll).
  - Solo se transiciona opacity: entrada suave, sin flash.
  - pointer-events:none para que no intercepte clicks.
*/
.nav-backdrop {
  position: absolute;
  inset: 0;
  background: transparent;
  backdrop-filter: blur(0px);
  -webkit-backdrop-filter: blur(0px);
  transition: background 300ms ease, backdrop-filter 300ms ease;
  pointer-events: none;
  transform: translateZ(0);
}

/* Al scrollear: tinte perceptible sobre contenido oscuro + blur */
.nav-backdrop.is-scrolled {
  background: linear-gradient(
    to bottom,
    rgba(20, 24, 28, 0.82) 0%,
    rgba(20, 24, 28, 0.45) 70%,
    rgba(20, 24, 28, 0.00) 100%
  );
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
}


/* Links — coherentes con la tipografía del logo: sans, no italic, bold */
.nav-link {
  font-size: 0.75rem;       /* 12px — compact como el logo */
  font-weight: 700;
  text-transform: uppercase;
  font-style: normal;        /* no italic — logo es recto */
  letter-spacing: 0.06em;    /* ligero tracking como el logo */
  color: #64748b;            /* slate-500 */
  padding: 0.25rem 0;
  transition: color 150ms;
  white-space: nowrap;
}

.nav-link:hover {
  color: #e2e8f0; /* slate-200 */
}

/* Búsqueda sin marco, con línea inferior sutil solo al hacer foco */
.search-input {
  border: none;
  border-bottom: 1px solid transparent;
  transition: border-color 150ms;
}

.search-input:focus {
  border-bottom-color: #475569; /* slate-600 */
}

/* Noticias — brand rojo, contenido curado/editorial */
.nav-link--noticias {
  color: #BE2B0C; /* brand */
}
.nav-link--noticias:hover {
  color: #e8471f; /* brand aclarado */
}

/* Editorial — indigo, señal visual de zona de admin/editor */
.nav-link--editorial {
  color: #6366f1; /* indigo-500 */
}
.nav-link--editorial:hover {
  color: #a5b4fc; /* indigo-300 */
}

/* Quitar el X nativo del input[type=search] */
.search-input::-webkit-search-cancel-button { display: none; }
.search-input::-webkit-search-decoration    { display: none; }

/* Links móvil */
.mobile-nav-link {
  font-size: 0.875rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #94a3b8; /* slate-400 */
  padding: 0.75rem 0;
  text-align: left;
  border-bottom: 1px solid rgba(255,255,255,0.05);
  transition: color 150ms;
  width: 100%;
}
.mobile-nav-link:last-child { border-bottom: none; }
.mobile-nav-link:hover { color: #e2e8f0; }
.mobile-nav-link--noticias { color: #BE2B0C; }
.mobile-nav-link--noticias:hover { color: #e8471f; }
.mobile-nav-link--editorial { color: #6366f1; }
.mobile-nav-link--editorial:hover { color: #a5b4fc; }
</style>
