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
  <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-md" @click="close"></div>

    <div class="relative w-full max-w-4xl bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl max-h-[85vh] flex flex-col">
      
      <button @click="close" class="absolute top-4 right-4 z-10 text-slate-400 hover:text-white transition-colors">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>

      <div v-if="isLoading" class="p-20 flex flex-col items-center justify-center gap-4">
        <div class="w-12 h-12 border-4 border-red-600/20 border-t-red-600 rounded-full animate-spin"></div>
        <p class="text-slate-500 text-xs font-bold uppercase tracking-widest">Cargando perfil...</p>
      </div>

      <div v-else-if="person" class="overflow-y-auto p-6 md:p-10 custom-scrollbar">
        <div class="grid md:grid-cols-12 gap-10">
          
          <div class="md:col-span-4 space-y-6">
            <div v-if="person.profile_path" class="relative group">
              <img 
                :src="getImageUrl(person.profile_path)" 
                class="w-full rounded-2xl shadow-2xl border border-slate-800 object-cover aspect-[2/3]"
                :alt="person.name"
              />
            </div>

            <div v-if="person.birthday || person.place_of_birth" class="bg-slate-950/50 p-5 rounded-2xl border border-slate-800 space-y-4">
              <div v-if="person.birthday" class="flex flex-col">
                <span class="text-[10px] text-red-600 font-bold uppercase tracking-widest">Fecha de Nacimiento</span>
                <span class="text-sm text-slate-200">{{ formatDate(person.birthday) }}</span>
              </div>
              <div v-if="person.place_of_birth" class="flex flex-col">
                <span class="text-[10px] text-red-600 font-bold uppercase tracking-widest">Lugar de procedencia</span>
                <span class="text-sm text-slate-200">{{ person.place_of_birth }}</span>
              </div>
            </div>
          </div>

          <div class="md:col-span-8 space-y-8">
            <div>
              <h2 class="text-5xl font-black text-white leading-tight tracking-tighter">{{ person.name }}</h2>
              <div class="h-1.5 w-16 bg-red-600 mt-2 rounded-full"></div>
            </div>

            <div v-if="person.bio && person.bio.trim().length > 0">
              <h3 class="text-xs font-bold text-slate-500 uppercase tracking-[0.2em] mb-4">Semblanza</h3>
              <p class="text-slate-300 leading-relaxed text-sm whitespace-pre-line italic font-serif">
                "{{ person.bio }}"
              </p>
            </div>

            <div v-if="groupedFilms.length > 0">
              <h3 class="text-xs font-bold text-slate-500 uppercase tracking-[0.2em] mb-4">Más filmografía</h3>
              <div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                <router-link 
                  v-for="film in groupedFilms" 
                  :key="film.idFilm" 
                  :to="`/films/${film.idFilm}`"
                  @click="close"
                  class="group block"
                >
                  <div class="aspect-[16/9] rounded-xl overflow-hidden border border-slate-800 mb-2 relative">
                    <img :src="film.frame" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110" />
                    <div class="absolute inset-0 bg-red-600/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
                  </div>
                  <p class="text-[11px] font-bold text-slate-200 group-hover:text-red-500 transition-colors truncate">{{ film.title }}</p>
                  <p class="text-[9px] text-slate-500 uppercase font-medium">{{ film.pivot?.role }}</p>
                </router-link>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #1e293b;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #ef4444;
}
</style>

