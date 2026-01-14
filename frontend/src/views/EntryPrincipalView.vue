<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';

// Componentes
import EntryHeader from '@/components/EntryHeader.vue';
import MovieGrid from '@/components/MovieGrid.vue';
import CommentSection from '@/components/CommentSection.vue';

const route = useRoute();
const auth = useAuthStore();
const entry = ref(null);

const typeLabel = computed(() => {
  if (entry.value?.type === 'user_list') return 'Lista';
  if (entry.value?.type === 'user_debate') return 'Debate';
  return 'Reseña';
});

const loadData = async () => {
  try {
    const { data } = await api.get(`/user_entries/${route.params.id}`);
    
    //Obtener los datos brutos (raw)
    const rawEntry = data.data || data;

    // Si la entrada tiene películas, les añadimos la propiedad 'poster_url' para conseguir el frame de la bd
    if (rawEntry.films && rawEntry.films.length > 0) {
      rawEntry.films = rawEntry.films.map(film => {
        return {
          ...film,            // Mantenemos todo lo original (idFilm, title, etc.)
          poster_url: film.frame // Así está en MovieGrid componente !
        };
      });
    }

    entry.value = rawEntry;

  } catch (e) {
    console.error("Error cargando la entrada:", e);
  }
};

onMounted(loadData);
</script>

<template>
  <div v-if="entry" class="min-h-screen bg-[#14181c] text-[#9ab] font-sans pb-20">
    
    <EntryHeader 
      :cover-image="entry.cover_image || entry.films?.[0]?.backdrop_url"
      :user="entry.user"
      :title="entry.title"
      :type-label="typeLabel"
    />

    <main class="max-w-5xl mx-auto px-4 py-8">
      <section class="mb-10 max-w-3xl">
        <div class="text-gray-200 leading-relaxed text-lg whitespace-pre-line">
          {{ entry.content }}
        </div>
      </section>

      <MovieGrid 
        v-if="entry.films?.length"
        :films="entry.films"
        :show-numbers="entry.type === 'user_list'"
        :title="entry.type === 'user_list' ? 'Películas en esta lista' : 'Película relacionada'"
      />

      <hr class="border-gray-800">

      <CommentSection 
        :entry-id="entry.id"
        :is-authenticated="auth.isAuthenticated"
        :current-user-id="auth.user?.id"
      />
    </main>
  </div>
</template>
