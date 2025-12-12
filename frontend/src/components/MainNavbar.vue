<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

const isActive = (path) => computed(() => route.path === path)

const goHome = () => router.push({ name: 'home' })
const goLogin = () => router.push({ name: 'login' })
const goNews = () => router.push({ name: 'news' }) // luego creamos esta vista
</script>

<template>
  <nav class="bg-slate-900/90 border-b border-slate-800 sticky top-0 z-40 backdrop-blur">
    <div class="max-w-6xl mx-auto px-4 md:px-6">
      <div class="flex items-center justify-between h-16 gap-4">
        <!-- Logo -->
        <button
          type="button"
          class="flex items-center gap-2 focus:outline-none"
          @click="goHome"
        >
          <!-- Usa tu logo real en assets cuando lo tengas -->
          <div class="w-10 h-10 rounded-full bg-red-700 flex items-center justify-center text-xs font-bold">
            CC7
          </div>
          <span class="hidden sm:inline text-lg font-semibold">
            CinemaClub 7
          </span>
        </button>

        <!-- Menú principal -->
        <div class="hidden md:flex items-center gap-6">
          <button
            type="button"
            class="text-sm font-semibold"
            :class="isActive('/') ? 'text-red-400' : 'text-slate-200 hover:text-red-300'"
            @click="goHome"
          >
            Inicio
          </button>
          <button
            type="button"
            class="text-sm font-semibold"
            :class="isActive('/news') ? 'text-red-400' : 'text-slate-200 hover:text-red-300'"
            @click="goNews"
          >
            Noticias
          </button>
        </div>

        <!-- Búsqueda + usuario -->
        <div class="flex items-center gap-4">
          <!-- Barra de búsqueda (de momento solo maqueta) -->
          <form
            class="hidden md:flex items-center gap-2"
            @submit.prevent
          >
            <input
              type="search"
              placeholder="Buscar películas..."
              class="bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5 text-sm text-slate-100 placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-red-500"
            />
            <button
              type="submit"
              class="px-3 py-1.5 rounded-lg bg-emerald-500 text-sm font-semibold text-slate-900 hover:bg-emerald-400"
            >
              Search
            </button>
          </form>

          <!-- Zona usuario -->
          <div v-if="auth.isAuthenticated" class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
              <p class="text-xs text-slate-400">Conectada como</p>
              <p class="text-sm font-semibold">
                {{ auth.user?.name }}
              </p>
            </div>
            <div class="relative">
              <!-- Avatar placeholder -->
              <div
                class="w-10 h-10 rounded-full bg-slate-700 flex items-center justify-center text-sm font-semibold cursor-pointer"
              >
                {{ (auth.user?.name || 'U')[0]?.toUpperCase() }}
              </div>
            </div>
          </div>

          <div v-else>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg border border-slate-600 text-sm font-semibold hover:bg-slate-800"
              @click="goLogin"
            >
              Login
            </button>
          </div>
        </div>
      </div>
    </div>
  </nav>
</template>
