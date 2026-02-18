<script setup>
import { useAuthStore } from '@/stores/auth'
import { computed, ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import RegisterModal from '@/components/RegisterModal.vue'
import HomeBackdropModal from '@/components/HomeBackdropModal.vue'

const auth = useAuthStore()
const router = useRouter()

// --- ESTADO ---
const isRegisterOpen = ref(false)
const isLoading = ref(false)
const isBackdropModalOpen = ref(false)

// Persistencia del fondo del Hero
const heroFilmId = ref(localStorage.getItem('home_hero_id') || 5190)
const heroFilmData = ref(null)

// Datos de secciones
const popularFilms = ref([])
const popularDebates = ref([])
const popularLists = ref([])
const popularReviews = ref([])

// ESTADO para Noticias Posts
const journalPosts = ref([])

// Saludo personalizado con nombre de usuario logueado
const welcomeMessage = computed(() => {
  if (!auth.user?.name) return "La red social para amantes del cine."
  const name = auth.user.name.charAt(0).toUpperCase() + auth.user.name.slice(1).toLowerCase()
  return `¡Qué alegría tenerte de vuelta, ${name}!`
})

const isAdmin = computed(() => {
  if (!auth.isAuthenticated || !auth.user) return false;
  const roleId = parseInt(auth.user.idRol);
  const roleName = String(auth.user.role || '').toLowerCase();
  return roleId === 1 || roleName === 'admin';
});

const openRegister = () => { isRegisterOpen.value = true }

const openBackdropModal = () => {
    isBackdropModalOpen.value = true
}

const sortContentByRecent = (contentArray) => {
    return contentArray.sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))
}

const fetchDashboardData = async () => {
  isLoading.value = true
  
  try {
    const [feedResult, filmsResult, heroResult, postsResult] = await Promise.allSettled([
        api.get('/user_entries/feed', { params: { page: 1 } }),
        api.get('/films/trending', { params: { per_page: 15 } }),
        api.get(`/films/${heroFilmId.value}`),
        api.get('/post-index') 
    ]);

    if (feedResult.status === 'fulfilled') {
        const allEntries = feedResult.value.data.data.data;
        popularDebates.value = sortContentByRecent(allEntries.filter(e => e.type === 'user_debate'));
        popularLists.value = sortContentByRecent(allEntries.filter(e => e.type === 'user_list'));
        popularReviews.value = sortContentByRecent(allEntries.filter(e => e.type === 'user_review'));
    }

    if (filmsResult.status === 'fulfilled') {
        popularFilms.value = filmsResult.value.data.data || [];
    }

    if (heroResult.status === 'fulfilled') {
        heroFilmData.value = heroResult.value.data;
    }

    if (postsResult.status === 'fulfilled') {
        const posts = postsResult.value.data.data || postsResult.value.data || [];
    
        journalPosts.value = posts;
    }

  } catch (error) {
    console.error("Error cargando dashboard:", error)
  } finally {
    isLoading.value = false
  }
}

const handleBackdropChange = async (film) => {
    if (!film) return;

    try {
        heroFilmId.value = film.idFilm
        localStorage.setItem('home_hero_id', film.idFilm)
        const res = await api.get(`/films/${film.idFilm}`)
        heroFilmData.value = res.data.data || res.data 
    } catch (e) {
        console.error("Error cambiando backdrop", e)
    }
}

const goToEntry = (type, id) => {
    router.push(`/entry/${type}/${id}`)
}

const formatDate = (dateString) => {
    if (!dateString) return ''
    return new Date(dateString).toLocaleDateString('es-ES', { 
        day: '2-digit', month: 'short', year: 'numeric' 
    })
}

onMounted(() => {
    fetchDashboardData()
})
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20">
    
    <div class="relative h-[65vh] md:h-[80vh] w-full flex items-center justify-center">
      <div class="absolute inset-0 z-0 overflow-hidden">
        <img 
          v-if="heroFilmData?.backdrop"
          :src="heroFilmData.backdrop" 
          class="w-full h-full object-cover opacity-30 animate-ken-burns"
          alt="Hero Backdrop"
        />
        <div class="absolute inset-0 bg-gradient-to-t from-[#14181c] via-[#14181c]/20 to-[#14181c]/60"></div>
      </div>

      <div>  
        <div v-if="isAdmin" class="absolute top-10 right-10 z-50">   
              <button @click="openBackdropModal" class="flex items-center gap-2 bg-brand/20 hover:bg-brand text-white border border-brand/50 px-4 py-2 rounded text-[10px] font-black uppercase tracking-widest transition-all"> 
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" /></svg>
                  Cambiar Backdrop
              </button>
          </div>

        <HomeBackdropModal 
            v-model="isBackdropModalOpen" 
            @change-backdrop="handleBackdropChange" 
        />
      </div>

      <div class="relative z-10 text-center px-6 max-w-4xl animate-fade-in-up">
        <h1 class="text-4xl md:text-6xl lg:text-7xl font-black text-white uppercase italic tracking-tighter leading-[0.9] mb-4">
            Watch. Rate. Debate.
        </h1>
        <p class="text-slate-400 font-bold uppercase tracking-[0.4em] text-[10px] md:text-xs mb-8">
            {{ welcomeMessage }}
        </p>

        <div class="flex flex-col items-center gap-8">
            <p class="text-slate-300 text-sm md:text-lg max-w-2xl font-light leading-relaxed">
                Descubre películas, puntúalas, crea listas y debate con otras personas. La comunidad cinéfila empieza aquí.
            </p>
            
            <button 
                v-if="!auth.isAuthenticated"
                @click="openRegister"
                class="bg-brand hover:bg-brand-dark text-white px-8 py-3 rounded font-black uppercase tracking-widest transition-all hover:scale-105 shadow-xl"
            >
                ¡Únete a la Comunidad!
            </button>
        </div>
      </div>
    </div>

    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0">
      
      <div v-if="isLoading" class="flex justify-center py-20">
          <div class="w-10 h-10 border-2 border-slate-800 border-t-brand rounded-full animate-spin"></div>
      </div>

      <div v-else class="flex flex-col gap-20">

        <section>
          <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
            <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Populares esta semana</h2>
            <span class="text-[9px] font-bold text-slate-600 uppercase tracking-widest">Actividad Global</span>
          </div>

          <div v-if="popularFilms.length > 0" class="brand-scroll flex gap-4 overflow-x-auto pb-6">
            <div 
              v-for="film in popularFilms" :key="film.idFilm"
              class="flex-shrink-0 w-[140px] md:w-[155px] group cursor-pointer"
              @click="router.push(`/films/${film.idFilm}`)"
            >
              <div class="aspect-[2/3] rounded overflow-hidden border border-slate-800 group-hover:border-brand transition-all shadow-lg relative">
                <img :src="film.frame || '/default-poster.webp'" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-8 h-8 text-white"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                </div>
              </div>
              <h3 class="mt-2 text-[11px] font-bold text-slate-300 truncate group-hover:text-brand transition-colors">{{ film.title }}</h3>
            </div>
          </div>
        </section>

        <section>
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
              <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Debates en llamas</h2>
            </div>
            
            <div v-if="popularDebates.length > 0" class="brand-scroll flex gap-6 overflow-x-auto pb-6 items-stretch">
                 <div 
                    v-for="debate in popularDebates.slice(0, 15)" :key="debate.id"
                    @click="goToEntry('user_debate', debate.id)"
                    class="flex-shrink-0 w-[260px] md:w-[320px] group cursor-pointer bg-slate-900/40 border border-slate-800 rounded-lg p-4 hover:border-orange-500/50 transition-all flex flex-col"
                 >
                    <div class="aspect-video rounded-md overflow-hidden mb-4 bg-black">
                        <img :src="debate.films?.[0]?.frame || '/default-debate.webp'" class="w-full h-full object-cover opacity-60 group-hover:opacity-100 transition-all" />
                    </div>
                    <h3 class="text-xs font-black uppercase text-white line-clamp-2 mb-3 group-hover:text-brand flex-grow">{{ debate.title }}</h3>
                    <div class="flex items-center gap-2 mt-auto">
                        <img :src="debate.user?.avatar ? `/storage/${debate.user.avatar}` : '/default-avatar.webp'" class="w-5 h-5 rounded-full object-cover" />
                        <span class="text-[9px] font-bold text-slate-500 uppercase">{{ debate.user?.name }}</span>
                    </div>
                 </div>

                 <div v-if="popularDebates.length > 15" class="flex-shrink-0 w-[150px] flex items-center justify-center">
                    <button @click="router.push('/feed')" class="group flex flex-col items-center gap-3 text-slate-500 hover:text-brand transition-colors">
                        <div class="w-12 h-12 rounded-full border border-slate-700 flex items-center justify-center group-hover:border-brand transition-colors bg-slate-900/40">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" /></svg>
                        </div>
                        <span class="text-[10px] font-black uppercase tracking-widest">Ver Todos</span>
                    </button>
                 </div>
            </div>
        </section>

        <section>
            <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
              <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Journal</h2>
            </div>
            
            <div v-if="journalPosts.length > 0" class="max-h-[600px] overflow-y-auto brand-scroll pr-4 pb-4">
                 <div class="masonry-grid-journal">
                     <article 
                        v-for="post in journalPosts.slice(0, 15)" :key="post.id"
                        @click="router.push(`/post-reed/${post.id}`)"
                        class="masonry-item-journal mb-6 group cursor-pointer bg-slate-900/40 border border-slate-800 rounded-lg p-4 hover:border-brand/50 transition-all flex flex-col"
                     >
                        <div class="rounded-md overflow-hidden mb-4 bg-[#1b2228] relative w-full h-auto">
                            <img :src="post.img || '/default-poster.webp'" class="w-full h-auto object-cover opacity-80 group-hover:opacity-100 group-hover:scale-105 transition-all duration-500" loading="lazy" />
                            <div v-if="parseInt(post.visible) === 0" class="absolute top-2 right-2 bg-yellow-500 text-black px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest shadow-md z-10">
                                Borrador
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-[8px] text-slate-500 uppercase tracking-widest font-bold">{{ formatDate(post.created_at) }}</span>
                            <span class="w-1 h-1 rounded-full bg-slate-700"></span>
                            <span class="text-[8px] text-slate-500 uppercase tracking-widest font-bold">{{ post.editorName || 'CC' }}</span>
                        </div>

                        <h3 class="text-sm md:text-base font-serif font-black text-white mb-2 group-hover:text-brand transition-colors leading-tight">{{ post.title }}</h3>
                        <p class="text-[10px] md:text-xs text-slate-400 font-light line-clamp-3">{{ post.subtitle }}</p>
                     </article>
                 </div>

                 <div v-if="journalPosts.length > 15" class="w-full flex justify-center mt-6 pt-4 border-t border-slate-800/50">
                    <button @click="router.push({ name: 'post-feed' })" class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 hover:text-white transition-colors py-2.5 px-8 border border-slate-700 hover:border-brand hover:bg-brand/10 rounded-full">
                        Ver todo el Journal
                    </button>
                 </div>
            </div>
            <div v-else class="py-10 border border-dashed border-slate-800 rounded text-center opacity-40">
                <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold italic">Aún no hay publicaciones.</p>
            </div>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-10">
            <div class="md:col-span-4">
                <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
                  <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Listas Populares</h2>
                </div>
                <div class="flex flex-col gap-4">
                    <div 
                        v-for="list in popularLists.slice(0, 4)" :key="list.id"
                        @click="goToEntry('user_list', list.id)"
                        class="flex items-center gap-4 group cursor-pointer"
                    >
                        <div class="relative w-14 h-20 flex-shrink-0">
                            <div v-if="list.films?.[1]" class="absolute top-1 left-2 w-full h-full bg-slate-700 rounded border border-slate-600 transform rotate-6 opacity-60 z-0"></div>
                            <img :src="list.films?.[0]?.frame || '/default-poster.webp'" class="absolute top-0 left-0 w-full h-full object-cover rounded border border-slate-600 z-10" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-[11px] font-black uppercase text-white truncate group-hover:text-brand transition-colors">{{ list.title }}</h3>
                            <p class="text-[9px] text-slate-500 uppercase mt-1">{{ list.films?.length }} Films</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="md:col-span-8">
                <div class="flex items-center justify-between mb-4 border-b border-slate-800 pb-2">
                  <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Reviews Populares</h2>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div 
                        v-for="review in popularReviews.slice(0, 4)" :key="review.id"
                        @click="goToEntry('user_review', review.id)"
                        class="flex gap-4 p-3 bg-slate-900/20 rounded-lg hover:bg-slate-800/40 transition-all group cursor-pointer"
                    >
                        <img :src="review.films?.[0]?.frame" class="w-16 h-24 object-cover rounded border border-slate-800 flex-shrink-0" />
                        <div class="min-w-0">
                            <h3 class="text-[10px] font-black uppercase text-white truncate group-hover:text-brand">{{ review.title }}</h3>
                            <p class="text-[9px] text-slate-400 font-bold uppercase mb-2">Por {{ review.user.name }}</p>
                            <p class="text-[10px] text-slate-500 italic line-clamp-3">"{{ review.content }}"</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

      </div>
    </div>

    <RegisterModal v-model="isRegisterOpen" />
  </div>
</template>

<style scoped>
/* Contenedor principal */
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

/* Tipografía Serif */
.font-serif {
  font-family: 'Tiempos Headline', Georgia, serif;
}

/* --- MASONRY GRID (ESTILO PINTEREST) PARA JOURNAL --- */
.masonry-grid-journal {
    column-count: 1;
    column-gap: 1.5rem;
}
@media (min-width: 640px) { .masonry-grid-journal { column-count: 2; } }
@media (min-width: 1024px) { .masonry-grid-journal { column-count: 3; } }

.masonry-item-journal {
    break-inside: avoid;
    display: inline-block;
    width: 100%;
}

/* Animación de fondo Ken Burns */
@keyframes ken-burns {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}
.animate-ken-burns {
  animation: ken-burns 30s ease-in-out infinite;
}

@keyframes fade-in-up {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up {
  animation: fade-in-up 1.2s ease-out forwards;
}

/* Scrollbars personalizadas */
.brand-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.brand-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 10px; }
.brand-scroll::-webkit-scrollbar-thumb { background: var(--brand-color, #dd6a23); border-radius: 10px; }

/* Truncado de texto */
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

@media (hover: none) {
  .brand-scroll::-webkit-scrollbar { height: 0px; width: 0px; }
}
</style>