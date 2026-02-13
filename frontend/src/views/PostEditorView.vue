<script setup>
import { ref, onMounted, computed } from 'vue';
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

// Configuración del Editor
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
    visible: false  
});

// Lógica de carga inicial
onMounted(async () => {
    try {
        
        const userResponse = await api.get('/user').catch(() => null); 
        
        if (userResponse && userResponse.data) {
            currentUser.value = userResponse.data;
            
            // Verificamos roles numéricos (1 = Admin, 2 = Editor)
            const roleId = parseInt(currentUser.value.idRol);
            const isAdmin = roleId === 1; 
            const isEditor = roleId === 2;

            // Si NO es admin Y TAMPOCO es editor: le redirigimos 
            if (!isAdmin && !isEditor) {
                alert("Acceso denegado: No tienes permisos de editor.");
                router.push({ name: 'post-feed' }); // Redirige al feed
                return; 
            }
        } else {
            // Si no hay usuario logueado, también rdirigimos
            router.push({ name: 'post-feed' });
            return;
        }
        
        // Si estamos editando, cargar el post
        if (isEditMode.value) {
            const response = await api.get(`/post-show/${route.params.id}`);
            // Soporte por si Laravel devuelve los datos dentro de un objeto dara **+
            const data = response.data.data || response.data;
            
            form.value = {
                title: data.title || '',
                subtitle: data.subtitle || '',
                editorName: data.editorName || currentUser.value?.name || '',
                img: data.img || '',
                content: data.content || '',
                // Nos aseguramos de que el checkbox entienda si es true/false por eso ponemos tantos or
                visible: data.visible === true || data.visible === 1 || data.visible === '1'
            };
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

/* 1. Reset de colores para el editor (fondo claro, texto oscuro) */
.ck-editor__editable {
    background-color: #e2e8f0 !important; 
    color: #1a202c !important;
    font-size: 1rem;
    padding: 2rem !important;
    
    /* 2. CONTROL DE SCROLL Y TAMAÑO */
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
</style>