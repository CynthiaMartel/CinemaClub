<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'; 
import { useRoute, useRouter } from 'vue-router';
import api from '@/services/api';
import { Ckeditor } from '@ckeditor/ckeditor5-vue';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';

const route = useRoute();
const router = useRouter();

// Variables de estado
const isSubmitting = ref(false);
const isLoading = ref(true); 
const isEditMode = computed(() => !!route.params.id);
const currentUser = ref(null);

// --- VARIABLES PARA BÚSQUEDA DE PELÍCULAS ---
const searchQuery = ref('');
const searchResults = ref([]);
const isSearchOpen = ref(false);
const isSearchingFilm = ref(false);
const searchWrapRef = ref(null);
const selectedFilm = ref(null);
let searchTimer = null;

// Configuración del Editor de Noticias
const editor = ClassicEditor;
const editorConfig = ref({
    toolbar: [ 'heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo' ],
    placeholder: 'Escribe el cuerpo de la noticia aquí...',
});

// Modelo del Formulario
const form = ref({
    title: '',
    subtitle: '',
    editorName: '', 
    img: '',        
    content: '',
    visible: false,
    film_id: null //** hay que añadirlo a la BD */** */
});

// --- LÓGICA DE BÚSQUEDA para buscar películas asociadas --- **mirar si ponemos más de una película en algún grid!!*
const fetchSearch = () => {
    clearTimeout(searchTimer);
    const q = searchQuery.value.trim();

    if (q.length < 2) {
        searchResults.value = [];
        isSearchOpen.value = false;
        return;
    }

    isSearchingFilm.value = true;

    searchTimer = setTimeout(async () => {
        try {
            const { data } = await api.get('/films/search', { params: { q } });
            searchResults.value = data.data || data;
            isSearchOpen.value = true;
        } catch (e) {
            searchResults.value = [];
        } finally {
            isSearchingFilm.value = false;
        }
    }, 400); 
};

const selectFilm = (film) => {
    selectedFilm.value = film;
    form.value.film_id = film.id; 
    
    // **: Si no hay imagen puesta, ponemos la de la película seleccionada
    if (!form.value.img && (film.poster_url || film.frame)) {
        form.value.img = film.poster_url || film.frame;
    }
    
    // Limpiamos
    searchQuery.value = '';
    isSearchOpen.value = false;
    searchResults.value = [];
};

const removeSelectedFilm = () => {
    selectedFilm.value = null;
    form.value.film_id = null;
};

const handleClickOutside = (event) => {
    if (searchWrapRef.value && !searchWrapRef.value.contains(event.target)) {
        isSearchOpen.value = false;
    }
};

// Lógica de carga inicial
onMounted(async () => {
    // Listener para cerrar búsqueda
    document.addEventListener('mousedown', handleClickOutside);

    try {
        const userResponse = await api.get('/user').catch(() => null); 
        
        if (userResponse && userResponse.data) {
            currentUser.value = userResponse.data;
            
            // Verificamos roles numéricos (1 = Admin, 2 = Editor)
            const roleId = parseInt(currentUser.value.idRol);
            const isAdmin = roleId === 1; 
            const isEditor = roleId === 2;

            // Si NO es admin Y TAMPOCO es editor: le redirigimos a post feed
            if (!isAdmin && !isEditor) {
                alert("Acceso denegado: No tienes permisos de editor.");
                router.push({ name: 'post-feed' }); // Redirige al feed
                return; 
            }
        } else {
            // Si no hay usuario logueado, también redirigimos
            router.push({ name: 'post-feed' });
            return;
        }
        
        // Si estamos editando, cargar el post
        if (isEditMode.value) {
            const response = await api.get(`/post-show/${route.params.id}`);
            // Soporte por si Laravel devuelve los datos dentro de un objeto dara **+*
            const data = response.data.data || response.data;
            
            form.value = {
                title: data.title || '',
                subtitle: data.subtitle || '',
                editorName: data.editorName || currentUser.value?.name || '',
                img: data.img || '',
                content: data.content || '',
                // Nos aseguramos de que el checkbox entienda si es true/false por eso ponemos tantos or
                visible: data.visible === true || data.visible === 1 || data.visible === '1',
                film_id: data.film_id || null
            };

            // Si hay info de pelicula en el post cargado **
            if (data.film) selectedFilm.value = data.film;
        } else {
            // Si es nuevo, poner el nombre del usuario logueado por defecto
            if(currentUser.value) form.value.editorName = currentUser.value.name;
        }

    } catch (e) {
        console.error("Error inicializando:", e);
        alert("Ocurrió un error al cargar la información.");
        router.push({ name: 'post-feed' }); 
    } finally {
        isLoading.value = false;
    }
});

onUnmounted(() => {
    document.removeEventListener('mousedown', handleClickOutside);
});

// Enviar el formulario
const submitEntry = async () => {
    if (isSubmitting.value) return;
    
    if (!form.value.title || !form.value.content) {
        alert("El título y el contenido son obligatorios.");
        return;
    }

    isSubmitting.value = true;
    
    try {
        const payload = { ...form.value };
        let response;
        
        if (isEditMode.value) {
            response = await api.put(`/post-update/${route.params.id}`, payload);
        } else {
            response = await api.post('/post-store', payload);
        }

        const message = response.data?.message || 'Operación exitosa';
        alert(message);
        
        router.push({ name: 'post-feed' }); 

    } catch (e) {
        console.error("ERROR API:", e.response?.data || e);
        alert(e.response?.data?.message || "Error al procesar la solicitud.");
    } finally {
        isSubmitting.value = false;
    }
};

// Función para cancelar de forma segura
const cancelEdit = () => {
    if (confirm("¿Estás seguro de cancelar? Se perderán los cambios no guardados.")) {
        router.push({ name: 'post-feed' });
    }
};
</script>

<template>
  <div class="min-h-screen text-slate-100 font-sans bg-[#14181c] overflow-x-hidden pb-20">
    
    <div v-if="isLoading" class="flex flex-col items-center justify-center h-screen gap-4">
      <div class="w-12 h-12 border-4 border-slate-800 border-t-[#13c090] rounded-full animate-spin"></div>
      <p class="text-slate-400 text-sm uppercase tracking-widest">Verificando permisos...</p>
    </div>

    <div v-else class="content-wrap w-full mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10 relative z-10">
      
      <header class="flex flex-col gap-6 mb-12 border-b border-slate-800 pb-10">
         <div class="flex items-center justify-between">
            <span class="px-3 py-1 rounded text-[10px] font-black uppercase tracking-widest bg-brand text-white shadow-lg shadow-green-900/20 border border-transparent">
                {{ isEditMode ? 'Editando Entrada' : 'Nueva Entrada' }}
            </span>
            <button @click="cancelEdit" class="text-slate-500 hover:text-white text-xs uppercase tracking-widest font-bold transition-colors">
                &larr; Cancelar
            </button>
         </div>

         <div class="space-y-4">
            <input 
              v-model="form.title" 
              type="text" 
              placeholder="Título de la entrada..."
              class="w-full bg-transparent text-3xl md:text-5xl font-black text-white outline-none placeholder-slate-600 focus:placeholder-slate-700 transition-colors uppercase italic leading-none tracking-tighter"
            >
            <input 
              v-model="form.subtitle" 
              type="text" 
              placeholder="Subtítulo o bajada breve..."
              class="w-full bg-transparent text-xl font-light text-slate-400 outline-none placeholder-slate-700 transition-colors"
            >
         </div>
      </header>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-16">
        
        <div class="lg:col-span-8 flex flex-col gap-8 order-2 lg:order-1">
            
            <section class="border border-slate-800 rounded-lg overflow-hidden bg-[#1c222b]">
                <div class="bg-slate-900/50 px-4 py-3 border-b border-slate-800 flex justify-between items-center">
                    <label class="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                        Cuerpo de la noticia
                    </label>
                    <span class="text-[10px] text-slate-600 italic">Compatible con Markdown</span>
                </div>
                
                <div class="editor-scroll-wrapper text-black">
                    <Ckeditor 
                        :editor="editor" 
                        v-model="form.content" 
                        :config="editorConfig"
                    />
                </div>
            </section>
        </div>

        <div class="lg:col-span-4 flex flex-col gap-8 order-1 lg:order-2">
            
            <div class="bg-[#1c222b] border border-slate-800 rounded-lg p-6 sticky top-6">
                <h3 class="text-xs font-bold uppercase tracking-widest text-white mb-6 border-b border-slate-800 pb-2">
                    Configuración
                </h3>

                <div class="mb-6">
                    <label class="flex items-center justify-between cursor-pointer group">
                        <span class="text-xs font-bold text-slate-400 group-hover:text-white transition-colors uppercase tracking-wider">Visibilidad</span>
                        <div class="relative inline-flex items-center">
                            <input type="checkbox" v-model="form.visible" class="sr-only peer">
                            <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#13c090]"></div>
                        </div>
                    </label>
                    <p class="text-[10px] mt-2 text-right font-bold" :class="form.visible ? 'text-[#13c090]' : 'text-yellow-500'">
                        {{ form.visible ? 'SE PUBLICARÁ' : 'GUARDAR COMO BORRADOR' }}
                    </p>
                </div>

                <div class="mb-6">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Editor / Autor</label>
                    <input 
                        v-model="form.editorName" 
                        type="text" 
                        class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-sm text-white focus:border-[#13c090] outline-none transition-colors"
                    >
                </div>

                <div class="mb-6" ref="searchWrapRef">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">Asociar Película</label>
                    
                    <div v-if="selectedFilm" class="bg-slate-900/50 rounded-lg p-3 border border-slate-700 flex items-start gap-3 relative group">
                        <img :src="selectedFilm.frame || selectedFilm.poster_url || '/default-poster.webp'" class="w-10 h-14 object-cover rounded bg-slate-800" />
                        <div class="flex-1 overflow-hidden">
                            <p class="text-xs font-bold text-white truncate">{{ selectedFilm.title }}</p>
                            <p class="text-[9px] text-slate-500 uppercase font-black">{{ selectedFilm.year }}</p>
                        </div>
                        <button @click="removeSelectedFilm" class="text-slate-500 hover:text-red-500 transition-colors absolute top-2 right-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
                              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div v-else class="relative group">
                        <input
                            v-model="searchQuery"
                            @input="fetchSearch"
                            @focus="isSearchOpen = true"
                            type="search"
                            placeholder="Buscar film..."
                            class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-sm text-white focus:border-[#13c090] outline-none transition-colors"
                        />
                        <div v-if="isSearchingFilm" class="absolute right-3 top-2.5">
                             <div class="w-3 h-3 border-2 border-slate-500 border-t-[#13c090] rounded-full animate-spin"></div>
                        </div>

                        <div
                          v-if="isSearchOpen && searchResults.length"
                          class="absolute mt-2 w-full rounded-lg border border-slate-700 bg-[#1e2227] shadow-xl overflow-hidden z-[100] animate-fade-in max-h-60 overflow-y-auto"
                        >
                          <button
                            v-for="film in searchResults"
                            :key="film.idFilm"
                            type="button"
                            class="w-full text-left px-3 py-2 hover:bg-slate-700 flex items-center gap-3 border-b border-slate-700/50 last:border-none transition-colors"
                            @click="selectFilm(film)"
                          >
                            <img :src="film.frame || film.poster_url" class="w-8 h-10 object-cover rounded bg-slate-800" />
                            <div class="flex-1 overflow-hidden">
                              <div class="text-xs font-bold text-white truncate">{{ film.title }}</div>
                              <div class="text-[9px] text-slate-500 uppercase tracking-widest font-black">
                                {{ film.year || 'S/D' }}
                              </div>
                            </div>
                          </button>
                        </div>
                    </div>
                </div>
                <div class="mb-8">
                    <label class="block text-[10px] font-bold uppercase tracking-widest text-slate-500 mb-2">URL Imagen Portada</label>
                    <input 
                        v-model="form.img" 
                        type="text" 
                        placeholder="https://..."
                        class="w-full bg-slate-800 border border-slate-700 rounded p-2 text-sm text-white focus:border-[#13c090] outline-none transition-colors"
                    >
                    <div v-if="form.img" class="mt-2 h-32 w-full rounded border border-slate-700 overflow-hidden relative">
                        <img :src="form.img" class="w-full h-full object-cover opacity-70">
                    </div>
                </div>

                <button 
                    @click="submitEntry"
                    :disabled="isSubmitting"
                    class="w-full bg-[#13c090] hover:bg-[#0fa87c] text-white font-bold py-3 rounded shadow-lg shadow-green-900/20 disabled:opacity-50 transition-all uppercase tracking-widest text-xs flex justify-center items-center gap-2"
                >
                    <span v-if="isSubmitting" class="w-3 h-3 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                    {{ isSubmitting ? 'Procesando...' : (isEditMode ? 'Guardar Cambios' : 'Publicar Entrada') }}
                </button>

            </div>
        </div>

      </div>
    </div>
  </div>
</template>

<style>
/* --- ESTILOS CRÍTICOS PARA CKEDITOR 5 --- */

/* Reset de colores para el editor (fondo claro, texto oscuro) */
.ck-editor__editable {
    background-color: #e2e8f0 !important; 
    color: #1a202c !important;
    font-size: 1rem;
    padding: 2rem !important;
    
    /* CONTROL DE SCROLL Y TAMAÑO */
    min-height: 500px;
    max-height: 75vh;
    overflow-y: auto !important; 
}

/* Personalización de la barra de herramientas para modo oscuro */
.ck-toolbar {
    background-color: #1e293b !important; 
    border: 0 !important;
    border-bottom: 1px solid #334155 !important;
}

.ck-button {
    color: #cbd5e0 !important;
}

.ck-button:hover {
    background-color: #334155 !important;
}

.ck-button.ck-on {
    background-color: #13c090 !important;
    color: white !important;
}

/* Ocultar el aviso de powered by */
.ck-powered-by {
    display: none;
}

/* --- RECUPERAR ESTILOS RESETEADOS POR TAILWIND --- */
.ck-editor__editable strong,
.ck-editor__editable b {
    font-weight: 700 !important;
}

.ck-editor__editable em,
.ck-editor__editable i {
    font-style: italic !important;
}

.ck-editor__editable h1,
.ck-editor__editable h2,
.ck-editor__editable h3,
.ck-editor__editable h4 {
    font-weight: 700 !important;
    margin-top: 1em !important;
    margin-bottom: 0.5em !important;
}

.ck-editor__editable h2 { font-size: 1.5em !important; }
.ck-editor__editable h3 { font-size: 1.25em !important; }

.ck-editor__editable ul {
    list-style-type: disc !important;
    padding-left: 1.5rem !important;
    margin-bottom: 1rem !important;
}

.ck-editor__editable ol {
    list-style-type: decimal !important;
    padding-left: 1.5rem !important;
    margin-bottom: 1rem !important;
}
</style>

<style scoped>
/* Alineación principal */
.content-wrap {
    width: 100%;
    margin-left: auto;
    margin-right: auto;
}

/* Animación para el dropdown de búsqueda */
.animate-fade-in {
  animation: fadeIn 0.2s ease-out;
}
@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>