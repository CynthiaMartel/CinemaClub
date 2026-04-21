<script setup>
import { ref, reactive, watch, nextTick } from 'vue';
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import api from '@/services/api';
import FilmSearch from '@/components/FilmSearch.vue';
import { avatarUrl } from '@/composables/useAvatar';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({
  modelValue: Boolean,
  initialData: Object,
  userId: [String, Number]
});

const emit = defineEmits(['update:modelValue', 'updated']);

const auth = useAuthStore();

const isSaving = ref(false);
const avatarFile = ref(null);
const avatarPreview = ref(null);
const currentInitial = ref('');

// Fondo de perfil
const backgroundPreview = ref(null);   // URL del backdrop del film seleccionado
const backgroundFilmTitle = ref('');   // Título del film para mostrar en UI

// Cropper
const showCropper = ref(false);
const rawImageSrc = ref(null);
const cropperImageEl = ref(null);
let cropperInstance = null;

const form = reactive({
  bio: '',
  location: '',
  website: '',
  top_films: [],
});

watch(() => props.modelValue, (isOpen) => {
  if (isOpen && props.initialData) {
    form.bio = props.initialData.bio || '';
    form.location = props.initialData.location || '';
    form.website = props.initialData.website || '';
    form.top_films = Array.isArray(props.initialData.top_films) ? [...props.initialData.top_films] : [];
    avatarPreview.value = avatarUrl(props.initialData.avatar);
    currentInitial.value = props.initialData.user?.name?.charAt(0).toUpperCase() || '?';
    avatarFile.value = null;
    showCropper.value = false;
    backgroundPreview.value = props.initialData.background || null;
    backgroundFilmTitle.value = '';
  }
  if (!isOpen) {
    destroyCropper();
  }
});

const onFileChange = (e) => {
  const file = e.target.files[0];
  if (!file) return;
  // Resetear input para permitir volver a seleccionar el mismo archivo
  e.target.value = '';

  const reader = new FileReader();
  reader.onload = (ev) => {
    rawImageSrc.value = ev.target.result;
    showCropper.value = true;
    nextTick(() => initCropper());
  };
  reader.readAsDataURL(file);
};

const initCropper = () => {
  destroyCropper();
  if (!cropperImageEl.value) return;
  cropperInstance = new Cropper(cropperImageEl.value, {
    aspectRatio: 1,
    viewMode: 1,
    dragMode: 'move',
    cropBoxResizable: false,
    cropBoxMovable: false,
    autoCropArea: 1,
    background: false,
    guides: false,
    center: false,
    highlight: false,
  });
};

const destroyCropper = () => {
  if (cropperInstance) {
    cropperInstance.destroy();
    cropperInstance = null;
  }
};

const applyCrop = () => {
  if (!cropperInstance) return;
  cropperInstance.getCroppedCanvas({ width: 400, height: 400 }).toBlob((blob) => {
    avatarFile.value = new File([blob], 'avatar.jpg', { type: 'image/jpeg' });
    avatarPreview.value = URL.createObjectURL(blob);
    showCropper.value = false;
    destroyCropper();
  }, 'image/jpeg', 0.92);
};

const cancelCrop = () => {
  showCropper.value = false;
  destroyCropper();
};

// Seleccionar film como fondo de perfil
const selectBackgroundFilm = async (film) => {
  const rawFilm = film.target || film;
  try {
    const { data } = await api.get(`/films/${rawFilm.idFilm || rawFilm.id}`);
    const filmData = data.data || data;
    backgroundPreview.value = filmData.backdrop || filmData.frame || null;
    backgroundFilmTitle.value = filmData.title || rawFilm.title || '';
  } catch {
    backgroundPreview.value = rawFilm.frame || null;
    backgroundFilmTitle.value = rawFilm.title || '';
  }
};

const clearBackground = () => {
  backgroundPreview.value = null;
  backgroundFilmTitle.value = '';
};

const addTopFilm = (film) => {
  if (form.top_films.length < 6) {
    const rawFilm = film.target || film;
    if (!form.top_films.find(f => (f.idFilm || f.id) === (rawFilm.idFilm || rawFilm.id))) {
      form.top_films.push({
        idFilm: rawFilm.idFilm || rawFilm.id,
        title: rawFilm.title,
        frame: rawFilm.frame || rawFilm.poster_url
      });
    }
  }
};

const removeTopFilm = (index) => {
  form.top_films.splice(index, 1);
};

const close = () => {
  emit('update:modelValue', false);
};

const handleSave = async () => {
  isSaving.value = true;
  try {
    const formData = new FormData();
    formData.append('_method', 'PUT');
    formData.append('bio', form.bio || '');
    formData.append('location', form.location || '');
    formData.append('website', form.website || '');
    formData.append('top_films', JSON.stringify(form.top_films));
    if (avatarFile.value) {
      formData.append('avatar', avatarFile.value);
    }
    // background es una URL string (backdrop del film seleccionado)
    formData.append('background', backgroundPreview.value || '');

    const { data } = await api.post(`/user_profiles/update/${props.userId}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

    // Sincronizar avatar en el store global para que el navbar lo muestre inmediatamente
    if (data?.data?.avatar !== undefined) {
      auth.setAvatar(data.data.avatar);
    }

    emit('updated');
    close();
  } catch (err) {
    console.error("Error saving profile:", err);
  } finally {
    isSaving.value = false;
  }
};
</script>

<template>
  <div v-if="modelValue" class="fixed inset-0 z-[2000] flex items-center justify-center p-4 sm:p-6">
    <div class="absolute inset-0 bg-[#17191c]/90 backdrop-blur-sm" @click="close"></div>

    <div class="relative w-full max-w-5xl bg-[#1b1e22] border border-slate-800 rounded-xl overflow-hidden flex flex-col max-h-[95vh] shadow-[0_0_50px_rgba(0,0,0,0.5)] animate-in fade-in zoom-in duration-200">

      <header class="px-6 py-4 bg-[#24282e] border-b border-slate-800 flex justify-between items-center">
        <h2 class="font-black text-white uppercase tracking-[0.2em] text-xs md:text-sm">Ajustes de Perfil</h2>
        <button @click="close" class="text-slate-500 hover:text-white transition-colors text-3xl leading-none">&times;</button>
      </header>

      <div class="p-6 md:p-8 overflow-y-auto custom-scrollbar bg-[#1b1e22]">
        <form @submit.prevent="handleSave" class="grid grid-cols-1 lg:grid-cols-12 gap-10">

          <div class="lg:col-span-7 space-y-8">

            <!-- Sección avatar: estado normal -->
            <section v-if="!showCropper" class="flex items-center gap-6 pb-6 border-b border-slate-800/50">
              <div class="relative group cursor-pointer">
                <img
                  v-if="avatarPreview"
                  :src="avatarPreview"
                  class="w-24 h-24 rounded-full object-cover border-2 border-slate-700 group-hover:border-orange-500 transition-colors"
                />
                <div
                  v-else
                  class="w-24 h-24 rounded-full border-2 border-slate-700 group-hover:border-orange-500 transition-colors bg-slate-800 flex items-center justify-center select-none"
                >
                  <span class="text-4xl font-black text-slate-400">{{ currentInitial }}</span>
                </div>
                <label for="avatar-up" class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 rounded-full transition-opacity text-[10px] font-bold uppercase text-white cursor-pointer">
                  {{ avatarPreview ? 'Cambiar' : 'Subir foto' }}
                </label>
              </div>
              <div>
                <h4 class="text-white font-bold text-sm mb-1 uppercase tracking-wider">Tu Avatar</h4>
                <input id="avatar-up" type="file" @change="onFileChange" hidden accept="image/*" />
                <p class="text-slate-500 text-[10px] leading-relaxed">Selecciona una foto y<br>ajusta el encuadre a tu gusto.</p>
              </div>
            </section>

            <!-- Sección cropper: aparece tras seleccionar foto -->
            <section v-else class="pb-6 border-b border-slate-800/50">
              <div class="flex items-center justify-between mb-3">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Ajusta el encuadre</p>
                <div class="flex items-center gap-2 text-[9px] text-slate-500 uppercase tracking-widest">
                  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M15.042 21.672 13.684 16.6m0 0-2.51 2.225.569-9.47 5.227 7.917-3.286-.672Zm-7.518-.267A8.25 8.25 0 1 1 20.25 10.5M8.288 14.212A5.25 5.25 0 1 1 17.25 10.5" /></svg>
                  Arrastra para mover · Rueda para zoom
                </div>
              </div>

              <!-- Contenedor del cropper con máscara circular -->
              <div class="cropper-wrap">
                <img ref="cropperImageEl" :src="rawImageSrc" class="cropper-source-img" />
              </div>

              <div class="flex gap-3 mt-4">
                <button
                  type="button"
                  @click="applyCrop"
                  class="px-5 py-2 bg-orange-500 hover:bg-orange-400 text-white text-[10px] font-black uppercase tracking-widest rounded transition-colors"
                >
                  Aplicar recorte
                </button>
                <button
                  type="button"
                  @click="cancelCrop"
                  class="px-5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-400 text-[10px] font-black uppercase tracking-widest rounded transition-colors"
                >
                  Cancelar
                </button>
              </div>
            </section>

            <div class="space-y-5">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2">
                  <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Ubicación</label>
                  <input v-model="form.location" type="text" class="input-style" placeholder="Ej: Madrid, España" />
                </div>
                <div class="flex flex-col gap-2">
                  <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Sitio Web</label>
                  <input v-model="form.website" type="url" class="input-style" placeholder="https://tuweb.com" />
                </div>
              </div>

              <div class="flex flex-col gap-2">
                <label class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Biografía</label>
                <textarea v-model="form.bio" class="input-style min-h-[120px] resize-none" placeholder="Escribe algo sobre ti..."></textarea>
              </div>
            </div>
          </div>

          <aside class="lg:col-span-5 flex flex-col">
            <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Películas Imprescindibles</h3>

            <div class="mb-6">
              <FilmSearch @select-film="addTopFilm" :disabled="form.top_films.length >= 6" />
            </div>

            <div class="grid grid-cols-3 gap-4">
              <div v-for="n in 6" :key="n" class="relative aspect-[2/3] rounded-lg overflow-hidden border-2 border-slate-800 bg-[#17191c] group transition-all">

                <template v-if="form.top_films[n-1]">
                  <img :src="form.top_films[n-1].frame || form.top_films[n-1].poster_url" class="w-full h-full object-cover" />
                  <div class="absolute inset-0 bg-black/70 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                    <button type="button" @click="removeTopFilm(n-1)" class="bg-red-600 p-2 rounded-full hover:bg-red-500 transform hover:scale-110 transition-all shadow-lg">
                      <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12" />
                      </svg>
                    </button>
                  </div>
                </template>

                <template v-else>
                  <div class="w-full h-full flex flex-col items-center justify-center gap-2 text-slate-600 border-2 border-dashed border-slate-800/50 hover:bg-slate-800/20 transition-colors">
                    <div class="text-3xl font-light">+</div>
                    <span class="text-[8px] font-black uppercase tracking-widest">Añadir</span>
                  </div>
                </template>

              </div>
            </div>
            <p class="text-[9px] text-slate-500 mt-4 italic text-center uppercase tracking-widest opacity-60">Selecciona hasta 6 películas</p>

            <!-- Imagen de fondo del perfil -->
            <div class="mt-8 pt-6 border-t border-slate-800/50">
              <h3 class="text-[10px] font-black text-slate-500 uppercase tracking-widest mb-4">Imagen de fondo del perfil</h3>

              <!-- Preview del fondo actual -->
              <div class="relative w-full h-24 rounded-lg overflow-hidden border border-slate-700 mb-4 bg-slate-900">
                <img
                  v-if="backgroundPreview"
                  :src="backgroundPreview"
                  class="w-full h-full object-cover opacity-60"
                />
                <div v-else class="w-full h-full flex items-center justify-center">
                  <span class="text-[9px] text-slate-600 uppercase tracking-widest font-bold italic">Sin fondo</span>
                </div>
                <div v-if="backgroundFilmTitle" class="absolute bottom-1 left-2 text-[8px] font-black text-white/70 uppercase tracking-widest truncate max-w-[80%]">
                  {{ backgroundFilmTitle }}
                </div>
                <button
                  v-if="backgroundPreview"
                  type="button"
                  @click="clearBackground"
                  class="absolute top-1 right-1 bg-black/60 hover:bg-black/80 text-white rounded-full w-5 h-5 flex items-center justify-center text-[10px] transition-colors"
                  title="Quitar fondo"
                >✕</button>
              </div>

              <FilmSearch @select-film="selectBackgroundFilm" placeholder="Busca un film para el fondo…" />
              <p class="text-[9px] text-slate-600 mt-2 italic text-center uppercase tracking-widest">Usa el backdrop del film como portada</p>
            </div>
          </aside>
        </form>
      </div>

      <footer class="p-6 bg-[#24282e] border-t border-slate-800 flex justify-end gap-4">
        <button @click="close" class="px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-white transition-colors">
          Cancelar
        </button>
        <button
          @click="handleSave"
          :disabled="isSaving || showCropper"
          class="px-8 py-2.5 bg-brand hover:bg-[#00c048] disabled:opacity-50 disabled:cursor-not-allowed text-white text-[10px] font-black uppercase tracking-widest rounded-md transition-all shadow-[0_4px_14px_0_rgba(0,224,84,0.39)]"
        >
          {{ isSaving ? 'Guardando...' : 'Guardar Cambios' }}
        </button>
      </footer>
    </div>
  </div>
</template>

<style scoped>
@reference "@/assets/main.css";

/* Contenedor del cropper: tamaño fijo y recorte circular visible */
.cropper-wrap {
  width: 100%;
  max-height: 320px;
  background: #0d0f11;
  border-radius: 0.5rem;
  overflow: hidden;
  border: 1px solid #1e293b;
}

.cropper-source-img {
  display: block;
  max-width: 100%;
}

/* Hacer la crop box circular */
:deep(.cropper-view-box),
:deep(.cropper-face) {
  border-radius: 50%;
}

:deep(.cropper-view-box) {
  outline: 2px solid #f97316;
  outline-offset: 0;
}

:deep(.cropper-line),
:deep(.cropper-point) {
  display: none;
}

:deep(.cropper-modal) {
  background: rgba(0, 0, 0, 0.6);
}

/* Scrollbar */
.custom-scrollbar::-webkit-scrollbar { width: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #1b1e22; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #445566; border-radius: 10px; }

.fade-in { animation: fadeIn 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>
