<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import api from '@/services/api';
import MovieGrid from '@/components/MovieGrid.vue';
import FilmSearch from '@/components/FilmSearch.vue';

const router = useRouter();
const isSubmitting = ref(false);

const types = [
  { label: 'Crear Lista', value: 'user_list', activeClass: 'bg-yellow-600 shadow-yellow-900/20', hoverClass: 'hover:text-yellow-500' },
  { label: 'Crear Debate', value: 'user_debate', activeClass: 'bg-orange-500 shadow-orange-900/20', hoverClass: 'hover:text-orange-400' },
  { label: 'Crear Reseña', value: 'user_review', activeClass: 'bg-[#BE2B0C] shadow-red-900/20', hoverClass: 'hover:text-red-500' }
];

const form = ref({
  title: '',
  content: '',
  type: 'user_list',
  visibility: 'public',
  films: []
});

const addFilm = (film) => {
  const exists = form.value.films.some(f => f.idFilm === film.idFilm);
  if (!exists) {
    form.value.films.push({
      ...film,
      poster_url: film.frame 
    });
  }
};

const removeMovie = (index) => {
  form.value.films.splice(index, 1);
};

const submitEntry = async () => {
  if (isSubmitting.value) return;
  isSubmitting.value = true;
  
  try {
    const payload = {
      ...form.value,
      film_ids: form.value.films.map(f => f.idFilm)
    };

    const response = await api.post('/user_entries/create', payload);
    const newId = response.data?.data?.id || response.data?.id;

    if (newId) {
      router.push({ 
        name: 'entry-detail', 
        params: { 
          type: form.value.type, 
          id: newId 
        } 
      });
    }
  } catch (e) {
    console.error("ERROR DETALLADO:", e.response?.data || e);
    alert("Error al procesar la respuesta del servidor.");
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div v-if="form" class="min-h-screen bg-[#14181c] text-[#9ab] font-sans pb-20">
    
    <div class="relative w-full h-auto pt-12 md:pt-16 overflow-hidden">
      <div class="absolute inset-0 bg-gradient-to-b from-black/20 via-[#14181c]/90 to-[#14181c]"></div>
      
      <div class="relative max-w-5xl mx-auto px-4 h-full flex flex-col justify-end pb-6">
  

        <div class="flex flex-wrap gap-5 mb-10">
          <button 
            v-for="t in types" :key="t.value"
            @click="form.type = t.value"
            :class="[
              'px-5 py-2 rounded-full text-[10px] font-black uppercase tracking-widest transition-all duration-300 border shadow-lg',
              form.type === t.value 
                ? `${t.activeClass} text-white border-transparent scale-105` 
                : `bg-slate-900/80 text-slate-500 border-slate-800 ${t.hoverClass} hover:border-slate-700`
            ]"
          >
            {{ t.label }}
          </button>
        </div>

        <input 
          v-model="form.title" 
          type="text" 
          placeholder="Título de la entrada..."
          class="bg-transparent text-3xl md:text-5xl font-bold text-white outline-none border-b border-gray-700 focus:border-[#13c090] transition-colors w-full pb-2"
        >
      </div>
    </div>

    <main class="max-w-5xl mx-auto px-4 py-8">
      
      <section class="mb-10">
        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-4">
          Contenido / Descripción
        </label>
        <textarea 
          v-model="form.content"
          placeholder="Escribe aquí los detalles de tu lista, discusión de debate o tu reseña..."
          class="w-full bg-[#2c3440] border-none rounded-md p-6 text-white text-lg focus:ring-2 focus:ring-[#00c020] min-h-[250px] resize-none shadow-inner"
        ></textarea>
      </section>

      <section v-if="form.type" class="mb-12 animate-fade-in">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b border-gray-800 pb-4">
          <h2 class="text-xs font-bold uppercase tracking-widest text-gray-500">
            Películas seleccionadas ({{ form.films.length }})
          </h2>
          
          <FilmSearch @select-film="addFilm" />
        </div>

        <MovieGrid 
          :films="form.films" 
          :show-numbers="form.type === 'user_list'" 
          is-editable
          @remove="removeMovie"
        />
        
        <div v-if="form.films.length === 0" class="py-12 border-2 border-dashed border-gray-800 rounded-lg text-center text-gray-600 italic text-sm">
          Usa el buscador superior para añadir películas a tu {{ form.type === 'user_list' ? 'lista' : 'reseña' }}.
        </div>
      </section>

      <div class="flex items-center justify-between border-t border-gray-800 pt-8 mt-12">
        <div class="flex flex-col gap-1">
          <label class="text-[10px] font-bold uppercase text-gray-600 ml-1">Visibilidad</label>
          <select v-model="form.visibility" class="bg-gray-800 border-none rounded text-xs text-white px-4 py-2 focus:ring-1 focus:ring-[#00c020]">
            <option value="public">Público</option>
            <option value="private">Privado</option>
          </select>
        </div>

        <button 
          @click="submitEntry"
          :disabled="isSubmitting || !form.title"
          class="bg-brand hover:bg-slate-800 text-white font-bold py-3 px-12 rounded shadow-lg disabled:opacity-50 transition-all uppercase tracking-widest text-sm"
        >
          {{ isSubmitting ? 'Publicando...' : 'Publicar Entrada' }}
        </button>
      </div>
    </main>
  </div>
</template>

<style scoped>
.animate-fade-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

