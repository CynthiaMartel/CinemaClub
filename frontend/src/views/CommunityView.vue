<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import StarDisplay from '@/components/StarDisplay.vue'

const auth = useAuthStore()
const router = useRouter()

const isLoading = ref(false)
const friendsActivity = ref([])
const friendsEntries = ref([])
const activeTab = ref('all')

const filteredEntries = computed(() => {
  if (activeTab.value === 'all') return friendsEntries.value
  return friendsEntries.value.filter(e => e.type === activeTab.value)
})

const fetchFeed = async () => {
  if (!auth.isAuthenticated) return
  isLoading.value = true
  try {
    const res = await api.get('/feed')
    const allFeed = res.data?.feed || []
    friendsActivity.value = allFeed.filter(item => item.type === 'film_action')
    friendsEntries.value = allFeed.filter(item =>
      ['user_debate', 'user_review', 'user_list'].includes(item.type)
    )
  } catch (err) {
    console.error('Error cargando comunidad:', err)
  } finally {
    isLoading.value = false
  }
}

const formatShortDate = (dateString) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  const month = date.toLocaleString('en-US', { month: 'short' })
  return `${month} ${date.getDate()}`
}

const getInitial = (name) => name ? name.charAt(0).toUpperCase() : '?'

const goToEntry = (type, id) => router.push(`/entry/${type}/${id}`)

onMounted(fetchFeed)
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20 pt-8">
    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0">

      <!-- Header -->
      <div class="flex items-center gap-3 mb-10 border-b border-slate-800 pb-5">
        <button @click="router.back()" class="w-7 h-7 flex items-center justify-center rounded border border-slate-700 text-slate-400 hover:border-brand hover:text-brand transition-colors">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </button>
        <h1 class="text-lg font-black uppercase tracking-[0.2em] text-white">Mi Comunidad</h1>
      </div>

      <!-- Sin auth -->
      <div v-if="!auth.isAuthenticated" class="py-24 text-center">
        <p class="text-slate-500 text-sm font-bold uppercase tracking-widest mb-4">Inicia sesión para ver la actividad de tu comunidad</p>
        <button @click="router.push('/')" class="text-[10px] font-black uppercase tracking-widest text-brand hover:text-brand-dark transition-colors">Ir al inicio</button>
      </div>

      <template v-else>
        <div v-if="isLoading" class="flex justify-center py-24">
          <div class="w-8 h-8 border-2 border-slate-800 border-t-brand rounded-full animate-spin"></div>
        </div>

        <div v-else class="flex flex-col gap-16">

          <!-- ── VISIONADOS ──────────────────────────────────────────── -->
          <section>
            <div class="flex items-center justify-between mb-5 border-b border-slate-800 pb-2">
              <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Visionados de mi comunidad</h2>
              <span class="text-[9px] font-bold text-slate-600 uppercase tracking-widest">{{ friendsActivity.length }} actividades</span>
            </div>

            <div v-if="friendsActivity.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
              <div
                v-for="(activity, index) in friendsActivity"
                :key="activity.film_id + '-' + index"
                class="group cursor-pointer"
                @click="router.push(`/films/${activity.film_id}`)"
              >
                <div class="relative w-full aspect-[2/3] rounded overflow-hidden border border-white/10 group-hover:border-white/40 transition-colors shadow-lg">
                  <img
                    :src="activity.film_frame || '/default-poster.webp'"
                    :alt="activity.film_title"
                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    loading="lazy"
                  />
                  <div class="absolute bottom-1 left-1 bg-[#14181c]/90 rounded px-1.5 py-1 flex items-center gap-1.5 backdrop-blur-sm border border-slate-700/50">
                    <div class="w-4 h-4 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center flex-shrink-0">
                      <span class="text-[7px] font-black text-white">{{ getInitial(activity.user) }}</span>
                    </div>
                    <span class="text-[9px] font-bold text-slate-200 truncate max-w-[80px]">{{ activity.user }}</span>
                  </div>
                </div>

                <div class="flex flex-col items-center gap-1 mt-2">
                  <div class="flex items-center justify-center gap-1.5">
                    <StarDisplay v-if="activity.rating" :rating="activity.rating" :lg="true" />
                    <span v-if="activity.rating" class="text-[11px] font-black text-slate-200 leading-none tabular-nums">{{ activity.rating }}</span>
                    <svg v-if="activity.is_favorite" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-brand flex-shrink-0" title="Favorita">
                      <path d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z" />
                    </svg>
                    <svg v-if="activity.watched && !activity.rating" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 text-emerald-400 flex-shrink-0" title="Vista">
                      <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /><path fill-rule="evenodd" d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z" clip-rule="evenodd" />
                    </svg>
                  </div>
                  <time class="text-[9px] font-bold text-slate-400 uppercase tracking-wide">{{ formatShortDate(activity.updated_at) }}</time>
                </div>
              </div>
            </div>

            <div v-else class="py-16 border border-dashed border-slate-800 rounded text-center opacity-40">
              <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold italic">Ningún seguido ha puntuado películas aún.</p>
            </div>
          </section>

          <!-- ── DEBATES, REVIEWS, LISTAS ───────────────────────────── -->
          <section>
            <div class="flex items-center justify-between mb-5 border-b border-slate-800 pb-2">
              <h2 class="text-xs font-black uppercase tracking-[0.2em] text-slate-200">Debates, reviews, listas de mi comunidad</h2>
            </div>

            <!-- Tabs filtro -->
            <div class="flex items-center gap-2 mb-6">
              <button
                v-for="tab in [
                  { key: 'all', label: 'Todo' },
                  { key: 'user_debate', label: 'Debates' },
                  { key: 'user_review', label: 'Reviews' },
                  { key: 'user_list', label: 'Listas' },
                ]"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="text-[9px] font-black uppercase tracking-widest px-3 py-1.5 rounded border transition-colors"
                :class="activeTab === tab.key
                  ? 'border-brand text-brand bg-brand/10'
                  : 'border-slate-700 text-slate-500 hover:border-slate-500 hover:text-slate-300'"
              >
                {{ tab.label }}
              </button>
            </div>

            <div v-if="filteredEntries.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-5">
              <div
                v-for="(entry, index) in filteredEntries"
                :key="'entry-' + entry.entry_id + '-' + index"
                class="group cursor-pointer"
                @click="goToEntry(entry.type, entry.entry_id)"
              >
                <!-- THUMBNAIL -->
                <div class="mb-2.5">
                  <!-- LISTA -->
                  <template v-if="entry.type === 'user_list'">
                    <div v-if="entry.films?.length" class="mini-poster-stack">
                      <ul class="mini-poster-list">
                        <li
                          v-for="(film, fi) in entry.films.slice(0, 5)"
                          :key="film.idFilm"
                          class="mini-poster-item"
                          :style="{ zIndex: fi * 10 }"
                          @click.stop="router.push(`/films/${film.idFilm}`)"
                        >
                          <img :src="film.frame || '/default-poster.webp'" :alt="film.title" class="mini-poster-img" loading="lazy" />
                        </li>
                      </ul>
                    </div>
                    <div v-else class="w-[70px] h-[105px] bg-slate-800/60 rounded border border-slate-700/50 flex items-center justify-center">
                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 text-slate-600"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" /></svg>
                    </div>
                  </template>

                  <!-- DEBATE -->
                  <template v-else-if="entry.type === 'user_debate'">
                    <div class="relative w-full aspect-[2/3] rounded overflow-hidden border border-white/10 group-hover:border-orange-500/40 transition-colors shadow-md"
                      @click.stop="entry.films?.[0]?.idFilm && router.push(`/films/${entry.films[0].idFilm}`)">
                      <img :src="entry.films?.[0]?.frame || '/default-poster.webp'" :alt="entry.title"
                        class="w-full h-full object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500" loading="lazy" />
                      <div class="absolute top-1 left-1 w-4 h-4 rounded-full bg-slate-900/90 border border-slate-700 flex items-center justify-center text-orange-400">
                        <i class="bi bi-chat-quote-fill text-[7px]"></i>
                      </div>
                    </div>
                  </template>

                  <!-- REVIEW -->
                  <template v-else>
                    <div class="relative w-full aspect-[2/3] rounded overflow-hidden border border-white/10 group-hover:border-slate-400/50 transition-colors shadow-md"
                      @click.stop="entry.films?.[0]?.idFilm && router.push(`/films/${entry.films[0].idFilm}`)">
                      <img :src="entry.films?.[0]?.frame || '/default-poster.webp'" :alt="entry.title"
                        class="w-full h-full object-cover opacity-75 sepia-[0.5] group-hover:sepia-0 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500" loading="lazy" />
                      <div class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white/10 backdrop-blur-sm border border-white/20 flex items-center justify-center text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5">
                          <path fill-rule="evenodd" d="M4.804 21.644A6.707 6.707 0 0 0 6 21.75a6.721 6.721 0 0 0 3.583-1.029c.774.182 1.584.279 2.417.279 5.322 0 9.75-3.97 9.75-9 0-5.03-4.428-9-9.75-9s-9.75 3.97-9.75 9c0 2.409 1.025 4.587 2.674 6.192.232.226.277.428.254.543a3.73 3.73 0 0 1-.814 1.686.75.75 0 0 0 .44 1.223 4.58 4.58 0 0 0 .744-.072z" clip-rule="evenodd" />
                        </svg>
                      </div>
                    </div>
                  </template>
                </div>

                <!-- TÍTULO -->
                <h3 class="text-[11px] font-black uppercase text-slate-200 line-clamp-2 mb-1.5 leading-tight group-hover:text-white transition-colors">{{ entry.title }}</h3>

                <!-- ATTRIBUTION -->
                <div class="flex items-center gap-1.5">
                  <div class="w-4 h-4 rounded-full bg-slate-700 border border-slate-500 flex items-center justify-center flex-shrink-0">
                    <span class="text-[6px] font-black text-white">{{ getInitial(entry.user) }}</span>
                  </div>
                  <span class="text-[10px] font-bold text-slate-300 truncate">{{ entry.user }}</span>
                </div>
                <div class="flex items-center gap-1 mt-1 flex-wrap">
                  <span class="text-[8px] font-black uppercase tracking-wide px-1.5 py-0.5 rounded"
                    :class="{
                      'text-orange-300 bg-orange-500/15': entry.type === 'user_debate',
                      'text-slate-200 bg-slate-600/40': entry.type === 'user_review',
                      'text-emerald-300 bg-emerald-500/15': entry.type === 'user_list'
                    }">
                    {{ entry.type === 'user_debate' ? 'Debate' : entry.type === 'user_review' ? 'Reseña' : 'Lista' }}
                  </span>
                  <span class="text-slate-600 text-[8px]">·</span>
                  <span class="text-[8px] text-slate-400 uppercase font-bold">{{ formatShortDate(entry.updated_at) }}</span>
                  <span class="text-slate-600 text-[8px]">·</span>
                  <span class="flex items-center gap-0.5 text-[8px] text-slate-300 font-bold">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-2.5 h-2.5 text-brand" aria-hidden="true">
                      <path d="m11.645 20.91-.007-.003-.022-.012a15.247 15.247 0 0 1-.383-.218 25.18 25.18 0 0 1-4.244-3.17C4.688 15.36 2.25 12.174 2.25 8.25 2.25 5.322 4.714 3 7.688 3A5.5 5.5 0 0 1 12 5.052 5.5 5.5 0 0 1 16.313 3c2.973 0 5.437 2.322 5.437 5.25 0 3.925-2.438 7.111-4.739 9.256a25.175 25.175 0 0 1-4.244 3.17 15.247 15.247 0 0 1-.383.219l-.022.012-.007.004-.003.001a.752.752 0 0 1-.704 0l-.003-.001Z" />
                    </svg>
                    {{ entry.likes_count || 0 }}
                  </span>
                </div>
              </div>
            </div>

            <div v-else class="py-16 border border-dashed border-slate-800 rounded text-center opacity-40">
              <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold italic">Ningún seguido ha publicado aún.</p>
            </div>
          </section>

        </div>
      </template>

    </div>
  </div>
</template>

<style scoped>
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

.mini-poster-stack { overflow: hidden; }
.mini-poster-list { display: flex; height: 135px; position: relative; list-style: none; padding: 0; margin: 0; }
.mini-poster-item { position: relative; width: 90px; height: 135px; margin-left: -63px; flex-shrink: 0; transition: transform 0.4s ease; }
.mini-poster-item:first-child { margin-left: 0; }
.mini-poster-img { width: 90px; height: 135px; object-fit: cover; border: 1.5px solid #14181c; border-radius: 5px; box-shadow: 8px 0 18px rgba(0,0,0,0.6); }
.group:hover .mini-poster-item { transform: translateY(-6px) rotate(-1deg); }

.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
</style>
