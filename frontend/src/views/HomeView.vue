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
    const [feedResult, filmsResult, heroResult] = await Promise.allSettled([
        api.get('/user_entries/feed', { params: { page: 1 } }),
        api.get('/films/trending', { params: { per_page: 15 } }),
        api.get(`/films/${heroFilmId.value}`)
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

  } catch (error) {
    console.error("Error cargando dashboard:", error)
  } finally {
    isLoading.value = false
  }
}

const handleBackdropChange = async (film) => {
    if (!film) return;

    try {
        // Actualizamos persistencia
        heroFilmId.value = film.idFilm
        localStorage.setItem('home_hero_id', film.idFilm)
        
        // Cargamos datos full 
        const res = await api.get(`/films/${film.idFilm}`)
        
        heroFilmData.value = res.data.data || res.data 
        
    } catch (e) {
        console.error("Error cambiando backdrop", e)
    }
}

const goToEntry = (type, id) => {
    router.push(`/entry/${type}/${id}`)
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

   
      <div class="...">  
        <div v-if="isAdmin" class="absolute top-10 right-10 z-50">   
              <button @click="openBackdropModal" class="flex items-center gap-2 bg-brand/20 hover:bg-brand text-white border border-brand/50 px-4 py-2 rounded text-[10px] font-black uppercase tracking-widest transition-all"> 
                  <i class="bi bi-pencil-square"></i> Cambiar Backdrop
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
                    <i class="bi bi-eye text-2xl text-white"></i>
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
            
            <div v-if="popularDebates.length > 0" class="grid grid-cols-1 md:grid-cols-3 gap-6">
                 <div 
                    v-for="debate in popularDebates.slice(0, 3)" :key="debate.id"
                    @click="goToEntry('user_debate', debate.id)"
                    class="group cursor-pointer bg-slate-900/40 border border-slate-800 rounded-lg p-4 hover:border-orange-500/50 transition-all"
                 >
                    <div class="aspect-video rounded-md overflow-hidden mb-4 bg-black">
                        <img :src="debate.films?.[0]?.frame || '/default-debate.webp'" class="w-full h-full object-cover opacity-60 group-hover:opacity-100 transition-all" />
                    </div>
                    <h3 class="text-xs font-black uppercase text-white line-clamp-2 mb-3 group-hover:text-brand">{{ debate.title }}</h3>
                    <div class="flex items-center gap-2">
                        <img :src="debate.user?.avatar ? `/storage/${debate.user.avatar}` : '/default-avatar.webp'" class="w-5 h-5 rounded-full object-cover" />
                        <span class="text-[9px] font-bold text-slate-500 uppercase">{{ debate.user?.name }}</span>
                    </div>
                 </div>
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

/* Scrollbars */
.brand-scroll::-webkit-scrollbar { height: 4px; }
.brand-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 10px; }
.brand-scroll::-webkit-scrollbar-thumb { background: var(--brand-color, #dd6a23); border-radius: 10px; }

/* Truncado de texto */
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

@media (hover: none) {
    .brand-scroll::-webkit-scrollbar { height: 0px; }
}
</style>