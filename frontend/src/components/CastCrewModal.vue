<script setup>
import { ref, watch, computed } from 'vue'
import api from '@/services/api'

const props = defineProps({
  modelValue: Boolean,
  personId: Number
})

const emit = defineEmits(['update:modelValue'])

const person = ref(null)
const isLoading = ref(false)

const fetchPersonDetails = async () => {
  if (!props.personId) return
  
  // Limpiamos los datos anteriores para que no se vea el actor anterior mientras carga
  person.value = null 
  isLoading.value = true
  
  try {
    const response = await api.get(`/${props.personId}/cast-crew`)
    // Verificamos si la respuesta viene en .data o .data.data según tu backend
    person.value = response.data.data || response.data
  } catch (error) {
    console.error("Error fetching person:", error)
  } finally {
    isLoading.value = false
  }
}

// immediate: true hace que cargue nada más abrirse el modal
watch(() => props.personId, (newId) => {
  if (newId) fetchPersonDetails()
}, { immediate: true })

const close = () => {
  emit('update:modelValue', false)
  person.value = null // Limpiar al cerrar
}

const formatDate = (dateStr) => {
  if (!dateStr) return null
  return new Date(dateStr).toLocaleDateString('es-ES', {
    day: 'numeric', month: 'long', year: 'numeric'
  })
}

// Función para procesar la imagen (TMDB path vs URL completa)
const getImageUrl = (path) => {
  if (!path) return null
  if (path.startsWith('http')) return path
  return `https://image.tmdb.org/t/p/w500${path}`
}

//Función para agrupar roles (por ejemplo si el director aparece como director y actor para no mostrar dos veces el mismo film)
const groupedFilms = computed(() => {
  if (!person.value?.films) return [];

  const map = new Map();

  person.value.films.forEach(film => {
    const id = film.idFilm;
    
    if (!map.has(id)) {
      // Si es la primera vez que vemos la película, la añadimos al mapa
      // Creamos una copia y convertimos el rol en un array
      map.set(id, {
        ...film,
        allRoles: [film.pivot?.role]
      });
    } else {
      // Si la película ya existe, extraemos el objeto y añadimos el nuevo rol al array
      const existingFilm = map.get(id);
      if (film.pivot?.role && !existingFilm.allRoles.includes(film.pivot?.role)) {
        existingFilm.allRoles.push(film.pivot?.role);
      }
    }
  });

  // Devolvemos el mapa convertido de nuevo a un array para el v-for
  return Array.from(map.values());
});

</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-4 backdrop-blur-md">
    <div class="absolute inset-0  backdrop-blur-xl transition-all duration-500" @click="close"></div>

    <div class="relative w-full max-w-5xl bg-[#1b2228]/95 border border-white/10 rounded-3xl overflow-hidden shadow-[0_32px_64px_-12px_rgba(0,0,0,0.6)] max-h-[90vh] flex flex-col">
      
      <button @click="close" class="absolute top-6 right-6 z-20 text-slate-400 hover:text-white transition-all bg-slate-900/50 hover:bg-slate-800 p-2 rounded-full backdrop-blur-md border border-white/5">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <div v-if="isLoading" class="p-20 flex flex-col items-center justify-center gap-4">
        <div class="w-12 h-12 border-4 border-red-600/20 border-t-red-600 rounded-full animate-spin"></div>
        <p class="text-slate-500 text-xs font-bold uppercase tracking-widest">Cargando perfil...</p>
      </div>

      <div v-else-if="person" class="overflow-y-auto custom-scrollbar">
        <div class="p-8 sm:p-12 md:px-16 md:py-14">
          <div class="grid md:grid-cols-12 gap-12 lg:gap-16">
            
            <div class="md:col-span-4 space-y-6 md:sticky md:top-0 self-start">
              <div v-if="person.profile_path" class="relative group overflow-hidden rounded-2xl border border-white/10 shadow-2xl transition-all duration-500 hover:scale-[1.03] hover:border-red-600/30">
                <img 
                  :src="getImageUrl(person.profile_path)" 
                  class="w-full object-cover aspect-[2/3] block"
                  :alt="person.name"
                />
                <div class="absolute inset-0 ring-1 ring-inset ring-white/10 group-hover:ring-red-600/20 transition-all"></div>
              </div>

              <div v-if="person.birthday || person.place_of_birth" class="bg-slate-950/40 p-6 rounded-2xl border border-white/5 space-y-5">
                <div v-if="person.birthday" class="flex flex-col">
                  <span class="text-[9px] text-red-600 font-black uppercase tracking-[0.2em] mb-1">Nacimiento</span>
                  <span class="text-sm text-slate-200 font-medium">{{ formatDate(person.birthday) }}</span>
                </div>
                <div v-if="person.place_of_birth" class="flex flex-col">
                  <span class="text-[9px] text-red-600 font-black uppercase tracking-[0.2em] mb-1">Procedencia</span>
                  <span class="text-sm text-slate-300 leading-snug">{{ person.place_of_birth }}</span>
                </div>
              </div>
            </div>

            <div class="md:col-span-8 space-y-10">
              <header>
                <h2 class="text-4xl sm:text-6xl font-black text-white leading-none tracking-tighter mb-4">{{ person.name }}</h2>
                <div class="h-1.5 w-20 bg-red-600 rounded-full"></div>
              </header>

              <div v-if="person.bio && person.bio.trim().length > 0">
                <h3 class="text-[10px] text-slate-500 font-black uppercase tracking-[0.3em] mb-4">Biografía</h3>
                <p class="text-slate-300 leading-relaxed text-base italic font-serif opacity-90">
                  "{{ person.bio }}"
                </p>
              </div>

              <div v-if="groupedFilms.length > 0">
                <h3 class="text-[10px] text-slate-500 font-black uppercase tracking-[0.3em] mb-6">Más filmografía</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
                  <router-link 
                    v-for="film in groupedFilms" 
                    :key="film.idFilm" 
                    :to="`/films/${film.idFilm}`"
                    @click="close"
                    class="group block"
                  >
                    <div class="aspect-[2/3] rounded-xl overflow-hidden border border-white/5 mb-3 relative bg-slate-900 shadow-lg">
                      <img :src="film.frame" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                      <div class="absolute inset-0 bg-red-600/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                    </div>
                    <p class="text-[11px] font-black text-slate-200 group-hover:text-red-500 transition-colors truncate uppercase tracking-tight">{{ film.title }}</p>
                    <p class="text-[9px] text-slate-500 uppercase font-bold tracking-wider mt-0.5">{{ film.pivot?.role }}</p>
                  </router-link>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.font-serif {
  font-family: 'Tiempos Headline', Georgia, serif;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #334155;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #ef4444;
}
</style>
