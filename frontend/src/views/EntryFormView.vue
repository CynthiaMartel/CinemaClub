<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import api from '@/services/api';
import MovieGrid from '@/components/MovieGrid.vue';
import FilmSearch from '@/components/FilmSearch.vue';
import { Ckeditor } from '@ckeditor/ckeditor5-vue';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';

const router = useRouter();
const route = useRoute();
const isSubmitting = ref(false);
const isLoading = ref(false);

const editId = computed(() => route.params.id || null);
const isEditMode = computed(() => !!editId.value);

const validTypes = ['user_list', 'user_debate', 'user_review'];
const initialType = validTypes.includes(route.query.type) ? route.query.type : 'user_list';

const types = [
  { label: 'Crear Lista', value: 'user_list', activeClass: 'bg-yellow-600 shadow-yellow-900/20', hoverClass: 'hover:text-yellow-500' },
  { label: 'Crear Debate', value: 'user_debate', activeClass: 'bg-orange-500 shadow-orange-900/20', hoverClass: 'hover:text-orange-400' },
  { label: 'Crear Reseña', value: 'user_review', activeClass: 'bg-[#BE2B0C] shadow-red-900/20', hoverClass: 'hover:text-red-500' }
];

const editor = ClassicEditor;
const editorConfig = ref({
  toolbar: ['bold', 'italic', 'underline', '|', 'bulletedList', 'numberedList', 'blockQuote', '|', 'link', '|', 'undo', 'redo'],
  placeholder: 'Escribe aquí los detalles de tu lista, discusión de debate o tu reseña...',
});

const form = ref({
  title: '',
  content: '',
  type: initialType,
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

onMounted(async () => {
  if (!isEditMode.value) return;
  isLoading.value = true;
  try {
    const { data } = await api.get(`/user_entries/${editId.value}/show`);
    const entry = data.data;
    form.value = {
      title: entry.title || '',
      content: entry.content || '',
      type: entry.type || initialType,
      visibility: entry.visibility || 'public',
      films: (entry.films || []).map(f => ({ ...f, poster_url: f.frame }))
    };
  } catch (e) {
    console.error("Error cargando entrada para editar:", e);
    alert("No se pudo cargar la entrada.");
  } finally {
    isLoading.value = false;
  }
});

const submitEntry = async () => {
  if (isSubmitting.value) return;
  isSubmitting.value = true;

  try {
    const payload = {
      title: form.value.title,
      content: form.value.content,
      type: form.value.type,
      visibility: form.value.visibility,
      film_ids: form.value.films.map(f => f.idFilm)
    };

    let response;
    if (isEditMode.value) {
      response = await api.put(`/user_entries/${editId.value}`, payload);
    } else {
      response = await api.post('/user_entries/create', payload);
    }

    const entry = response.data?.data;
    const entryId = entry?.id || editId.value;
    const entryType = entry?.type || form.value.type;

    if (entryId) {
      router.push({
        name: 'entry-detail',
        params: { type: entryType, id: entryId }
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
  <div class="min-h-screen bg-[#14181c] text-[#9ab] font-sans pb-20">

    <div v-if="isLoading" class="flex items-center justify-center h-screen">
      <div class="w-10 h-10 border-4 border-slate-800 border-t-brand rounded-full animate-spin"></div>
    </div>

    <template v-else>
      <div class="relative w-full h-auto pt-12 md:pt-16 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-black/20 via-[#14181c]/90 to-[#14181c]"></div>

        <div class="content-wrap relative mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 h-full flex flex-col justify-end pb-6">

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
            class="bg-transparent text-3xl md:text-5xl font-bold text-white outline-none border-b border-slate-700 focus:border-brand transition-colors w-full pb-2"
          >
        </div>
      </div>

      <main class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-8">

        <section class="mb-10">
          <label class="block text-xs font-bold uppercase tracking-widest text-slate-400 mb-4">
            Contenido / Descripción
          </label>
          <div class="border border-slate-700 rounded-md overflow-hidden editor-entry-wrap">
            <Ckeditor
              :editor="editor"
              v-model="form.content"
              :config="editorConfig"
            />
          </div>
        </section>

        <section v-if="form.type" class="mb-12 animate-fade-in">
          <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4 border-b border-slate-800 pb-4">
            <h2 class="text-xs font-bold uppercase tracking-widest text-slate-400">
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

          <div v-if="form.films.length === 0" class="py-12 border-2 border-dashed border-slate-800 rounded-lg text-center text-slate-500 italic text-sm">
            Usa el buscador superior para añadir películas a tu {{ form.type === 'user_list' ? 'lista' : 'reseña' }}.
          </div>
        </section>

        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-t border-slate-800 pt-8 mt-12">
          <div class="flex flex-col gap-1">
            <label class="text-[10px] font-bold uppercase text-slate-400 ml-1">Visibilidad</label>
            <select v-model="form.visibility" class="bg-slate-800 border-none rounded text-xs text-white px-4 py-2 focus:ring-1 focus:ring-brand/40">
              <option value="public">Público</option>
              <option value="private">Privado</option>
            </select>
          </div>

          <button
            @click="submitEntry"
            :disabled="isSubmitting || !form.title"
            class="w-full sm:w-auto bg-brand hover:bg-slate-800 text-white font-bold py-3 px-8 sm:px-12 rounded shadow-lg disabled:opacity-50 transition-all uppercase tracking-widest text-sm"
          >
            {{ isSubmitting ? 'Guardando...' : (isEditMode ? 'Guardar Cambios' : 'Publicar Entrada') }}
          </button>
        </div>
      </main>
    </template>
  </div>
</template>

<style>
/* CKEditor en entry form: fondo oscuro adaptado al theme de la app */
.editor-entry-wrap .ck-editor__editable {
  background-color: #2c3440 !important;
  color: #e2e8f0 !important;
  font-size: 1rem;
  padding: 1.5rem !important;
  min-height: 250px;
  max-height: 60vh;
  overflow-y: auto !important;
}

.editor-entry-wrap .ck-toolbar {
  background-color: #1e293b !important;
  border: 0 !important;
  border-bottom: 1px solid #334155 !important;
}

.editor-entry-wrap .ck-button {
  color: #cbd5e0 !important;
}

.editor-entry-wrap .ck-button:hover {
  background-color: #334155 !important;
}

.editor-entry-wrap .ck-button.ck-on {
  background-color: #13c090 !important;
  color: white !important;
}

.editor-entry-wrap .ck-powered-by {
  display: none;
}

/* Recuperar estilos reseteados por Tailwind dentro del editor */
.editor-entry-wrap .ck-editor__editable strong,
.editor-entry-wrap .ck-editor__editable b {
  font-weight: 700 !important;
}

.editor-entry-wrap .ck-editor__editable em,
.editor-entry-wrap .ck-editor__editable i {
  font-style: italic !important;
}

.editor-entry-wrap .ck-editor__editable u {
  text-decoration: underline !important;
}

.editor-entry-wrap .ck-editor__editable ul {
  list-style-type: disc !important;
  padding-left: 1.5rem !important;
  margin-bottom: 1rem !important;
}

.editor-entry-wrap .ck-editor__editable ol {
  list-style-type: decimal !important;
  padding-left: 1.5rem !important;
  margin-bottom: 1rem !important;
}

.editor-entry-wrap .ck-editor__editable blockquote {
  border-left: 4px solid #334155;
  padding-left: 1rem;
  color: #94a3b8;
  font-style: italic;
  margin: 1rem 0;
}
</style>

<style scoped>
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

.animate-fade-in {
  animation: fadeIn 0.4s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>