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

const { showInstallButton, install, browserType } = useInstallPrompt()

const installPanel = ref(null) // null | 'safari-mac' | 'safari-ios' | 'firefox'

const closeInstallPanel = () => { installPanel.value = null }

const handleInstall = async () => {
  isUserMenuOpen.value  = false
  isMobileMenuOpen.value = false
  if (browserType === 'safari-mac' || browserType === 'safari-ios' || browserType === 'firefox') {
    installPanel.value = browserType
  } else {
    await install()
  }
}

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
const goAgenda    = () => router.push({ name: 'agenda' })
const goEvents    = () => { isUserMenuOpen.value = false; router.push({ name: 'event-manager' }) }
const goAdmin     = () => { isUserMenuOpen.value = false; router.push({ name: 'admin-dashboard' }) }

const isEditorOrAdmin = computed(() => {
  if (!auth.isAuthenticated || !auth.user) return false
  return auth.user.idRol === 1 || auth.user.idRol === 2
})

const isAdmin = computed(() => {
  if (!auth.isAuthenticated || !auth.user) return false
  return auth.user.idRol === 1
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
const searchQuery   = ref('')
const isSearchOpen  = ref(false)
const searchInputRef = ref(null)

const openSearch = async () => {
  isSearchOpen.value = true
  await nextTick()
  searchInputRef.value?.focus()
}

const closeSearch = () => {
  isSearchOpen.value = false
  searchQuery.value  = ''
}

const goToSearch = () => {
  const q = searchQuery.value.trim()
  if (!q) return
  router.push({ name: 'search', query: { q } })
  searchQuery.value = ''
  isSearchOpen.value = false
}

const handleSearchKey = (e) => {
  if (e.key === 'Enter') goToSearch()
  if (e.key === 'Escape') closeSearch()
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
    isUserMenuOpen.value  = false
    isMobileMenuOpen.value = false
    installPanel.value    = null
  }
}

watch(() => route.fullPath, () => {
  isUserMenuOpen.value  = false
  isMobileMenuOpen.value = false
  installPanel.value    = null
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
        <button type="button" class="logo-btn flex-shrink-0 flex items-center gap-2.5" @click="goHome">
          <img :src="logoUrl" alt="FilmoClub" class="h-6 md:h-7 w-auto object-contain" />
          <span class="logo-wordmark" aria-label="FilmoClub">
            <span class="logo-filmo">Filmo</span><span class="logo-club">Club</span>
          </span>
        </button>

        <!-- Nav links + Search — agrupados a la derecha, pegados entre sí -->
        <div class="flex-1 flex items-center justify-end gap-5 md:gap-7">

          <!-- Nav links — solo desktop -->
          <div class="hidden md:flex items-center gap-6">
            <button type="button" class="nav-link" @click="goCommunity">Comunidad</button>
            <button type="button" class="nav-link" @click="goFilmsFeed">Films</button>
            <button type="button" class="nav-link" @click="goDebates">Debates</button>
            <button type="button" class="nav-link nav-link--noticias" @click="goNews">Noticias</button>
            <button type="button" class="nav-link nav-link--agenda" @click="goAgenda">Agenda</button>
          </div>

          <!-- Search expandible -->
          <div class="search-wrap">
            <!-- Lupa -->
            <button
              type="button"
              class="search-icon-btn"
              :class="{ 'is-open': isSearchOpen }"
              :aria-label="isSearchOpen ? 'Buscar' : 'Abrir búsqueda'"
              @click="isSearchOpen ? goToSearch() : openSearch()"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
              </svg>
            </button>

            <!-- Input con transición de anchura -->
            <Transition name="search-expand">
              <input
                v-if="isSearchOpen"
                ref="searchInputRef"
                v-model="searchQuery"
                @keydown="handleSearchKey"
                @blur="!searchQuery && closeSearch()"
                type="search"
                placeholder="Buscar…"
                class="search-input"
              />
            </Transition>
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
                class="absolute right-0 mt-2 w-56 origin-top-right rounded-xl border border-white/10 bg-[#1b2228] shadow-xl overflow-hidden"
              >
                <!-- Sección admin — solo para admins -->
                <template v-if="isAdmin">
                  <div class="px-4 pt-3 pb-1">
                    <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-[#00e054]/70">Administración</span>
                  </div>
                  <button type="button" class="w-full text-left px-4 py-2.5 text-sm text-[#00e054] hover:bg-[#00e054]/10 flex items-center gap-2.5 transition-colors" @click="goAdmin">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/>
                    </svg>
                    Panel de películas
                  </button>
                  <button v-if="isEditorOrAdmin" type="button" class="w-full text-left px-4 py-2.5 text-sm text-indigo-400 hover:bg-indigo-500/10 flex items-center gap-2.5 transition-colors" @click="goEditorial">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Panel editorial
                  </button>
                  <button v-if="isEditorOrAdmin" type="button" class="w-full text-left px-4 py-2.5 text-sm text-amber-400 hover:bg-amber-500/10 flex items-center gap-2.5 transition-colors" @click="goEvents">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Event Manager
                  </button>
                  <div class="border-t border-white/[0.06] mx-3 my-1"></div>
                </template>

                <!-- Sección editorial (solo editors, no admins — admins ya lo ven arriba) -->
                <template v-else-if="isEditorOrAdmin">
                  <div class="px-4 pt-3 pb-1">
                    <span class="text-[9px] font-bold uppercase tracking-[0.2em] text-indigo-400/70">Editorial</span>
                  </div>
                  <button type="button" class="w-full text-left px-4 py-2.5 text-sm text-indigo-400 hover:bg-indigo-500/10 flex items-center gap-2.5 transition-colors" @click="goEditorial">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Panel editorial
                  </button>
                  <button type="button" class="w-full text-left px-4 py-2.5 text-sm text-amber-400 hover:bg-amber-500/10 flex items-center gap-2.5 transition-colors" @click="goEvents">
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Event Manager
                  </button>
                  <div class="border-t border-white/[0.06] mx-3 my-1"></div>
                </template>

                <!-- Opciones de usuario -->
                <button type="button" class="w-full text-left px-4 py-2.5 text-sm text-slate-200 hover:bg-slate-800 transition-colors" @click="goProfile">Mi perfil</button>
                <button type="button" class="w-full text-left px-4 py-2.5 text-sm text-slate-200 hover:bg-slate-800 transition-colors" @click="goCommunity">Comunidad</button>
                <button type="button" class="w-full text-left px-4 py-2.5 text-sm text-slate-200 hover:bg-slate-800 transition-colors" @click="goChangePassword">Cambiar contraseña</button>

                <!-- Instalar PWA — visible en todos los navegadores mientras no esté instalada -->
                <template v-if="showInstallButton">
                  <div class="border-t border-white/[0.06] mx-3 my-1"></div>
                  <button
                    type="button"
                    class="w-full text-left px-4 py-2.5 text-sm text-slate-200 hover:bg-slate-800 flex items-center gap-2.5 transition-colors"
                    @click="handleInstall"
                  >
                    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Instalar app
                  </button>
                </template>

                <div class="border-t border-white/[0.06] mx-3 my-1"></div>
                <button type="button" class="w-full text-left px-4 py-2.5 text-sm text-red-400 hover:bg-red-500/10 transition-colors" @click="logout">Cerrar sesión</button>
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

          <!-- Botón instalar PWA — solo para usuarios NO logueados (los logueados lo ven en el dropdown) -->
          <!-- Botón instalar — desktop (sm+), oculto en móvil (ahí va en el menú hamburguesa) -->
          <button
            v-if="showInstallButton && !auth.isAuthenticated"
            type="button"
            class="hidden sm:flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-white/10
                   text-[10px] font-bold uppercase tracking-wider text-slate-400 hover:text-white
                   hover:border-white/20 transition-colors"
            @click="handleInstall"
            aria-label="Instalar FilmoClub"
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
          <button type="button" class="mobile-nav-link mobile-nav-link--agenda" @click="goAgenda">Agenda</button>
          <button v-if="isAdmin" type="button" class="mobile-nav-link mobile-nav-link--admin" @click="goAdmin">Panel de películas</button>
          <!-- Instalar app — móvil, solo usuarios no logueados -->
          <button
            v-if="showInstallButton && !auth.isAuthenticated"
            type="button"
            class="mobile-nav-link flex items-center gap-2"
            @click="handleInstall; isMobileMenuOpen = false"
          >
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            Instalar app
          </button>
        </nav>
      </div>
    </Transition>
  </nav>

  <LoginModal v-model="isLoginOpen" />
  <ChangePasswordModal v-model="isChangePasswordOpen" />

  <!-- Panel de instrucciones de instalación (Safari / Firefox) -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="installPanel"
        class="fixed inset-0 z-[999] flex items-end sm:items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
      >
        <!-- Backdrop -->
        <div
          class="absolute inset-0 bg-black/60 backdrop-blur-sm"
          @click="closeInstallPanel"
        />

        <!-- Tarjeta -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 translate-y-4 scale-95"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          appear
        >
          <div class="relative w-full max-w-sm rounded-2xl bg-[#1b2228] border border-white/10 shadow-2xl p-6">

            <!-- Botón cerrar -->
            <button
              type="button"
              class="absolute top-4 right-4 text-slate-500 hover:text-slate-300 transition-colors"
              @click="closeInstallPanel"
              aria-label="Cerrar"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>

            <!-- Safari macOS -->
            <template v-if="installPanel === 'safari-mac'">
              <div class="flex items-center gap-3 mb-4">
                <span class="text-2xl">🧭</span>
                <h2 class="text-base font-bold text-white">Instalar en Mac</h2>
              </div>
              <ol class="space-y-2 text-sm text-slate-300">
                <li class="flex gap-2"><span class="text-slate-500 font-mono">1.</span>En el menú superior, ve a <strong class="text-white">Archivo</strong></li>
                <li class="flex gap-2"><span class="text-slate-500 font-mono">2.</span>Pulsa <strong class="text-white">Añadir al Dock…</strong></li>
                <li class="flex gap-2"><span class="text-slate-500 font-mono">3.</span>Confirma con <strong class="text-white">Añadir</strong></li>
              </ol>
            </template>

            <!-- Safari iOS -->
            <template v-else-if="installPanel === 'safari-ios'">
              <div class="flex items-center gap-3 mb-4">
                <span class="text-2xl">📱</span>
                <h2 class="text-base font-bold text-white">Instalar en iPhone / iPad</h2>
              </div>
              <ol class="space-y-2 text-sm text-slate-300">
                <li class="flex gap-2"><span class="text-slate-500 font-mono">1.</span>Pulsa el botón <strong class="text-white">Compartir</strong> <span class="inline-block border border-slate-600 rounded px-1 text-xs">⬆</span> de Safari</li>
                <li class="flex gap-2"><span class="text-slate-500 font-mono">2.</span>Selecciona <strong class="text-white">Añadir a pantalla de inicio</strong></li>
                <li class="flex gap-2"><span class="text-slate-500 font-mono">3.</span>Confirma con <strong class="text-white">Añadir</strong></li>
              </ol>
            </template>

            <!-- Firefox -->
            <template v-else-if="installPanel === 'firefox'">
              <div class="flex items-center gap-3 mb-4">
                <span class="text-2xl">🦊</span>
                <h2 class="text-base font-bold text-white">Firefox no admite instalación</h2>
              </div>
              <p class="text-sm text-slate-300">
                Para instalar FilmoClub como app, ábrela en
                <strong class="text-white">Chrome</strong> o <strong class="text-white">Brave</strong>
                y pulsa de nuevo el botón Instalar.
              </p>
            </template>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
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

/* Al scrollear: fondo sólido semi-transparente con blur */
.nav-backdrop.is-scrolled {
  background: rgba(20, 24, 28, 0.65);
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
}


/* Links — coherentes con la tipografía del logo: sans, no italic, bold */
.nav-link {
  font-size: 0.875rem;      /* 14px — más legible y vistoso */
  font-weight: 700;
  text-transform: uppercase;
  font-style: normal;        /* no italic — logo es recto */
  letter-spacing: 0.07em;    /* ligero tracking como el logo */
  color: #64748b;            /* slate-500 */
  padding: 0.25rem 0;
  transition: color 150ms;
  white-space: nowrap;
}

.nav-link:hover {
  color: #e2e8f0; /* slate-200 */
}

/* ── Search expandible ─────────────────────────────────────────── */
.search-wrap {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.search-icon-btn {
  flex-shrink: 0;
  color: #64748b;
  transition: color 150ms;
  cursor: pointer;
  padding: 0.25rem;
  border-radius: 0.25rem;
}

.search-icon-btn:hover,
.search-icon-btn.is-open {
  color: #e2e8f0;
}

.search-input {
  width: 140px;
  background: transparent;
  border: none;
  border-bottom: 1px solid #475569;
  color: #e2e8f0;
  font-size: 0.875rem;
  padding: 0.15rem 0.25rem;
  outline: none;
}

.search-input::placeholder { color: #475569; }
.search-input::-webkit-search-cancel-button,
.search-input::-webkit-search-decoration { display: none; }

@media (min-width: 640px)  { .search-input { width: 180px; } }
@media (min-width: 768px)  { .search-input { width: 220px; } }

/* Transición Vue: search-expand */
.search-expand-enter-active,
.search-expand-leave-active {
  transition: width 200ms ease, opacity 150ms ease;
  overflow: hidden;
}
.search-expand-enter-from,
.search-expand-leave-to {
  width: 0 !important;
  opacity: 0;
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

/* Agenda — naranja marca (mismo que logo-club), calendario de eventos */
.nav-link--agenda {
  color: #f97316; /* orange-500, coherente con logo-club */
}
.nav-link--agenda:hover {
  color: #fdba74; /* orange-300 */
}

/* Events (menú admin) — amber */
.nav-link--events {
  color: #f59e0b; /* amber-400 */
}
.nav-link--events:hover {
  color: #fcd34d; /* amber-300 */
}

/* Quitar el X nativo del input[type=search] */
.search-input::-webkit-search-cancel-button { display: none; }
.search-input::-webkit-search-decoration    { display: none; }

/* Links móvil */
.mobile-nav-link {
  font-size: 1rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
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
.mobile-nav-link--events { color: #f59e0b; }
.mobile-nav-link--events:hover { color: #fcd34d; }
.mobile-nav-link--agenda { color: #f97316; }
.mobile-nav-link--agenda:hover { color: #fdba74; }
.mobile-nav-link--admin { color: #00e054; }
.mobile-nav-link--admin:hover { color: #6effa0; }

/* ── Logo wordmark ─────────────────────────────────────────────── */
.logo-btn {
  cursor: pointer;
}

.logo-wordmark {
  font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  font-size: 1.15rem;
  font-weight: 900;
  letter-spacing: -0.02em;
  line-height: 1;
  display: inline-block;
  transform: scaleX(1.15);
  transform-origin: left center;
  transition: opacity 150ms ease;
  user-select: none;
}

/* responsive wordmark */
@media (min-width: 640px) {
  .logo-wordmark { font-size: 1.3rem; transform: scaleX(1.15); }
}
@media (min-width: 768px) {
  .logo-wordmark { font-size: 1.5rem; transform: scaleX(1.15); }
}
@media (min-width: 1024px) {
  .logo-wordmark { font-size: 1.65rem; transform: scaleX(1.15); }
}

.logo-btn:hover .logo-wordmark {
  opacity: 0.82;
}

.logo-filmo {
  color: #BE2B0C; /* brand */
}

.logo-club {
  color: #f97316; /* orange-500 */
}
</style>
