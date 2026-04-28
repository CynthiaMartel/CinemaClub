<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const props = defineProps({
  modelValue: { type: Boolean, default: false }
})
const emit = defineEmits(['update:modelValue'])

const close = () => emit('update:modelValue', false)

const router = useRouter()
const auth = useAuthStore()

const diary = ref([])
const stats = ref(null)
const isLoading = ref(false)
const page = ref(1)
const lastPage = ref(1)
const isLoadingMore = ref(false)

const monthNames = ['ENE','FEB','MAR','ABR','MAY','JUN','JUL','AGO','SEP','OCT','NOV','DIC']

const diaryGrouped = computed(() => {
  const grouped = {}
  diary.value.forEach(entry => {
    const d = new Date(entry.updated_at)
    const key = `${monthNames[d.getMonth()]} ${d.getFullYear()}`
    if (!grouped[key]) grouped[key] = []
    grouped[key].push(entry)
  })
  return grouped
})

const orderedKeys = computed(() => Object.keys(diaryGrouped.value))

const fetchDiary = async (p = 1, append = false) => {
  const username = auth.user?.name
  if (!username) return
  try {
    if (p === 1) isLoading.value = true
    else isLoadingMore.value = true

    const { data } = await api.get(`/my_films_diary/${username}`, {
      params: { type: 'diary', page: p, per_page: 24 }
    })

    diary.value = append ? [...diary.value, ...(data.data || [])] : (data.data || [])
    page.value = data.pagination.current_page
    lastPage.value = data.pagination.last_page
  } catch (e) {
    console.error('Error cargando filmoteca:', e)
  } finally {
    isLoading.value = false
    isLoadingMore.value = false
  }
}

const fetchStats = async () => {
  const username = auth.user?.name
  if (!username) return
  try {
    const { data } = await api.get(`/user_films/stats/${username}`)
    stats.value = data.user?.stats || null
  } catch (e) {
    console.error('Error cargando stats:', e)
  }
}

const loadMore = () => {
  if (page.value < lastPage.value) fetchDiary(page.value + 1, true)
}

const goToFilm = (filmId) => {
  close()
  router.push(`/films/${filmId}`)
}

const goToProfile = () => {
  close()
  if (auth.user?.name) router.push({ name: 'user-profile', params: { username: auth.user.name } })
}

watch(() => props.modelValue, (open) => {
  if (open) {
    diary.value = []
    page.value = 1
    lastPage.value = 1
    fetchDiary(1, false)
    fetchStats()
  }
})
</script>

<template>
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
        v-if="modelValue"
        class="fixed inset-0 z-[200] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-label="Mi Filmoteca"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="close" />

        <!-- Panel -->
        <Transition
          enter-active-class="transition duration-200 ease-out"
          enter-from-class="opacity-0 translate-y-3 scale-[0.98]"
          enter-to-class="opacity-100 translate-y-0 scale-100"
          appear
        >
          <div class="relative w-full max-w-lg bg-[#14181c] border border-white/10 rounded-2xl shadow-2xl flex flex-col overflow-hidden max-h-[88vh]">

            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-white/[0.07] flex-shrink-0">
              <div class="flex items-center gap-3">
                <div class="w-1 h-5 rounded-full bg-amber-400"></div>
                <div>
                  <h2 class="text-[11px] font-black uppercase tracking-[0.2em] text-white leading-none">Filmoteca Visionada</h2>
                  <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">{{ auth.user?.name }}</p>
                </div>
              </div>
              <button
                type="button"
                class="w-7 h-7 flex items-center justify-center rounded-lg text-slate-500 hover:text-white hover:bg-white/5 transition-colors"
                @click="close"
                aria-label="Cerrar"
              >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Stats bar -->
            <div v-if="stats" class="grid grid-cols-3 border-b border-white/[0.07] flex-shrink-0">
              <div class="text-center py-3 border-r border-white/[0.07]">
                <p class="text-lg font-black text-white leading-none">{{ stats.films_seen }}</p>
                <p class="text-[8px] uppercase tracking-widest font-bold text-slate-500 mt-0.5">Vistas</p>
              </div>
              <div class="text-center py-3 border-r border-white/[0.07]">
                <p class="text-lg font-black text-white leading-none">{{ stats.films_seen_this_year }}</p>
                <p class="text-[8px] uppercase tracking-widest font-bold text-slate-500 mt-0.5">Este año</p>
              </div>
              <div class="text-center py-3">
                <p class="text-lg font-black text-white leading-none">{{ stats.films_rated }}</p>
                <p class="text-[8px] uppercase tracking-widest font-bold text-slate-500 mt-0.5">Ratings</p>
              </div>
            </div>

            <!-- Body -->
            <div class="overflow-y-auto flex-1 filmoteca-scroll">

              <!-- Loading inicial -->
              <div v-if="isLoading" class="flex justify-center py-16">
                <div class="w-8 h-8 border-2 border-slate-800 border-t-amber-400 rounded-full animate-spin"></div>
              </div>

              <!-- Sin actividad -->
              <div v-else-if="diary.length === 0" class="flex flex-col items-center justify-center py-16 gap-3 text-center px-8">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-12 h-12 text-slate-700">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75.125V6.375m0 0A1.125 1.125 0 0 1 3.375 5.25h17.25a1.125 1.125 0 0 1 1.125 1.125M3.375 6.375v.75m17.25-1.125h-1.5c-.621 0-1.125.504-1.125 1.125M20.625 6.375v.75m0 0v12m0-12H6" />
                </svg>
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Aún no has registrado películas</p>
              </div>

              <!-- Diario agrupado por mes -->
              <div v-else class="px-4 py-3 flex flex-col gap-5">
                <div v-for="monthKey in orderedKeys" :key="monthKey">
                  <!-- Cabecera mes -->
                  <div class="flex items-center gap-2 mb-2 sticky top-0 bg-[#14181c] py-1.5 z-10">
                    <span class="text-[9px] font-black uppercase tracking-[0.25em] text-amber-400/80 border-l-2 border-amber-400 pl-2">{{ monthKey }}</span>
                    <span class="text-[8px] font-bold text-slate-600">{{ diaryGrouped[monthKey].length }} films</span>
                  </div>

                  <!-- Filas de película -->
                  <div class="flex flex-col gap-0.5">
                    <div
                      v-for="entry in diaryGrouped[monthKey]"
                      :key="entry.id"
                      class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-white/[0.04] cursor-pointer group transition-colors"
                      @click="goToFilm(entry.film_id)"
                    >
                      <!-- Día -->
                      <div class="w-8 flex-shrink-0 text-center">
                        <span class="text-[13px] font-black text-slate-400 group-hover:text-white leading-none block transition-colors">
                          {{ new Date(entry.updated_at).getDate() }}
                        </span>
                        <span class="text-[7px] uppercase text-slate-600 font-bold tracking-widest">Día</span>
                      </div>

                      <!-- Divisor -->
                      <div class="w-px h-8 bg-slate-800 flex-shrink-0"></div>

                      <!-- Mini poster -->
                      <div class="w-8 h-12 flex-shrink-0 rounded overflow-hidden border border-transparent group-hover:border-slate-600 transition-colors">
                        <img
                          :src="entry.film?.frame || '/default-poster.webp'"
                          :alt="entry.film?.title"
                          class="w-full h-full object-cover"
                          loading="lazy"
                        />
                      </div>

                      <!-- Título + badges -->
                      <div class="flex-1 min-w-0">
                        <p class="text-[12px] font-bold text-slate-200 group-hover:text-white transition-colors truncate leading-tight">
                          {{ entry.film?.title }}
                        </p>
                        <div class="flex items-center gap-1 mt-1">
                          <!-- Rating numérico + estrella -->
                          <span v-if="entry.rating" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-400/10 border border-amber-400/20">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5 text-amber-400">
                              <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" />
                            </svg>
                            <span class="text-[9px] font-black text-amber-400 leading-none tabular-nums">{{ entry.rating }}</span>
                          </span>
                          <!-- Solo vista -->
                          <span v-else class="inline-flex items-center px-1.5 py-0.5 rounded bg-slate-700/40 border border-slate-700/60">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5 text-slate-400">
                              <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/>
                              <path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd"/>
                            </svg>
                          </span>
                          <!-- Favorita -->
                          <span v-if="entry.is_favorite" class="inline-flex items-center px-1.5 py-0.5 rounded bg-brand/10 border border-brand/20">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5 text-brand">
                              <path d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z"/>
                            </svg>
                          </span>
                        </div>
                      </div>

                      <!-- Flecha -->
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3 text-slate-700 group-hover:text-slate-400 flex-shrink-0 transition-colors">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                      </svg>
                    </div>
                  </div>
                </div>

                <!-- Load more -->
                <div v-if="page < lastPage" class="flex justify-center py-3">
                  <button
                    @click="loadMore"
                    :disabled="isLoadingMore"
                    class="flex items-center gap-2 text-[9px] font-black uppercase tracking-[0.2em] text-slate-500 hover:text-amber-400 transition-colors disabled:opacity-40"
                  >
                    <svg v-if="isLoadingMore" class="w-3 h-3 animate-spin" viewBox="0 0 24 24" fill="none">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <span>{{ isLoadingMore ? 'Cargando...' : '+ Ver más historial' }}</span>
                  </button>
                </div>
              </div>

            </div>

            <!-- Footer -->
            <div class="flex-shrink-0 border-t border-white/[0.07] px-5 py-3 flex items-center justify-between">
              <span class="text-[8px] font-bold text-slate-600 uppercase tracking-widest">
                {{ diary.length }} entrada{{ diary.length !== 1 ? 's' : '' }} cargada{{ diary.length !== 1 ? 's' : '' }}
              </span>
              <button
                type="button"
                class="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-slate-400 hover:text-white transition-colors"
                @click="goToProfile"
              >
                Ver perfil completo
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3 h-3">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                </svg>
              </button>
            </div>

          </div>
        </Transition>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.filmoteca-scroll::-webkit-scrollbar { width: 3px; }
.filmoteca-scroll::-webkit-scrollbar-track { background: transparent; }
.filmoteca-scroll::-webkit-scrollbar-thumb { background: #334155; border-radius: 10px; }
.filmoteca-scroll::-webkit-scrollbar-thumb:hover { background: #475569; }
</style>
