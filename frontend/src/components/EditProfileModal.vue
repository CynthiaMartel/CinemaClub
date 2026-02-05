<script setup>
import { ref, reactive, watch } from 'vue';
import api from '@/services/api';
import FilmSearch from '@/components/FilmSearch.vue';

const props = defineProps({
  modelValue: Boolean, 
  initialData: Object,
  userId: [String, Number]
});

const emit = defineEmits(['update:modelValue', 'updated']);

const isSaving = ref(false);
const avatarFile = ref(null);
const avatarPreview = ref(null);

const form = reactive({
  bio: '',
  location: '',
  website: '',
  top_films: [],
});

// para sincronizar datos al abrir el modal
watch(() => props.modelValue, (isOpen) => {
  if (isOpen && props.initialData) {
    form.bio = props.initialData.bio || '';
    form.location = props.initialData.location || '';
    form.website = props.initialData.website || '';
    
    //hacemos que top_films sea un array
    form.top_films = Array.isArray(props.initialData.top_films) ? [...props.initialData.top_films] : [];
    avatarPreview.value = props.initialData.avatar ? `/storage/${props.initialData.avatar}` : null;
  }
});

const onFileChange = (e) => {
  const file = e.target.files[0];
  if (file) {
    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
  }
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
    // ****Laravel requiere _method PUT para procesar archivos vía POST
    formData.append('_method', 'PUT');
    formData.append('bio', form.bio || '');
    formData.append('location', form.location || '');
    formData.append('website', form.website || '');
    formData.append('top_films', JSON.stringify(form.top_films));
    
    if (avatarFile.value) {
      formData.append('avatar', avatarFile.value);
    }

    await api.post(`/user_profiles/update/${props.userId}`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    });

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
            
            <section class="flex items-center gap-6 pb-6 border-b border-slate-800/50">
              <div class="relative group">
                <img :src="avatarPreview || '/default-avatar.webp'" class="w-24 h-24 rounded-full object-cover border-2 border-slate-700 group-hover:border-brand transition-colors" />
                <label for="avatar-up" class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 rounded-full cursor-pointer transition-opacity text-[10px] font-bold uppercase text-white">Cambiar</label>
              </div>
              <div>
                <h4 class="text-white font-bold text-sm mb-1 uppercase tracking-wider">Tu Avatar</h4>
                <input id="avatar-up" type="file" @change="onFileChange" hidden accept="image/*" />
                <p class="text-slate-500 text-[10px] leading-relaxed">Sube una imagen cuadrada.<br>JPG o PNG, máx. 2MB.</p>
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
          </aside>
        </form>
      </div>

      <footer class="p-6 bg-[#24282e] border-t border-slate-800 flex justify-end gap-4">
        <button @click="close" class="px-6 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-white transition-colors">
          Cancelar
        </button>
        <button 
          @click="handleSave" 
          :disabled="isSaving"
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


/* Scrollbar */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #1b1e22;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #445566;
  border-radius: 10px;
}


/* Animaciones */
.fade-in { animation: fadeIn 0.2s ease-out; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>