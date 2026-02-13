<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api' // Instancia de Axios

// ---STORES Y ROUTER 
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

// --- ESTADO 
const posts = ref([])
const isLoading = ref(true)
const searchQuery = ref(route.query.search || '')

//--
// Verificamos si es Admin (rol 1) o Editor (rol 2)
const isAdminOrEditor = computed(() => {
    if (!auth.isAuthenticated || !auth.user) return false;
    const roleId = parseInt(auth.user.idRol);
    const roleName = String(auth.user.role || '').toLowerCase();
    return roleId === 1 || roleId === 2 || roleName === 'admin' || roleName === 'editor';
});

// Filtramos los posts 
const visiblePosts = computed(() => {
    return posts.value.filter(post => {
        const esVisible = parseInt(post.visible) === 1;
        return esVisible || isAdminOrEditor.value;
    });
});

// --- MÉTODOS ---

//  Obtener Posts
const fetchPosts = async () => {
    isLoading.value = true
    try {
        const response = await api.get('/post-index', { 
            params: { search: searchQuery.value } 
        })
        posts.value = response.data.data || response.data
    } catch (error) {
        console.error("Error cargando los posts:", error)
    } finally {
        isLoading.value = false
    }
}

// Ejecutar búsqueda
const performSearch = () => {
    router.replace({ query: { ...route.query, search: searchQuery.value } })
    fetchPosts()
}

// Ir a la vista detalle del post
const goToPost = (id) => {
    router.push(`/post/${id}`)
}

// Ir a la vista del Editor para crear/editarr
const goToEditor = (id = null) => {
    if (id) {
        // Modo Edición: pasamos el ID como parámetro 
        router.push({ name: 'post-editor', params: { id } })
    } else {
        // Modo Creación: sin ID
        router.push({ name: 'post-editor' })
    }
}

// Eliminar post
const confirmDeletePost = async (id) => {
    if (!confirm('¿Estás seguro de que deseas eliminar este post? Esta acción no se puede deshacer.')) return;
    
    try {
        await api.delete(`/post-destroy/${id}`)
        // Recargamos el feed tras borrar exitosamente
        fetchPosts()
    } catch (error) {
        console.error("Error al eliminar post:", error)
        alert('Hubo un error al intentar eliminar el post.');
    }
}

//---
onMounted(() => {
    fetchPosts()
})
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20">
    
    <main class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10 relative z-10">
        
        <header class="flex flex-col md:flex-row items-center justify-between gap-6 mb-12 border-b border-slate-800 pb-6">
            <h1 class="text-3xl md:text-5xl font-black text-white uppercase italic leading-none tracking-tighter w-full md:w-auto text-center md:text-left">
                Últimas Noticias
            </h1>
            
            <div class="flex items-center gap-4 w-full md:w-auto">
                <div class="relative w-full md:w-64">
                    <input 
                        v-model="searchQuery" 
                        @keyup.enter="performSearch"
                        type="text" 
                        placeholder="Buscar..."
                        class="w-full bg-slate-900 border border-slate-800 rounded px-3 py-1.5 text-xs text-white focus:border-brand outline-none transition-colors placeholder-slate-600"
                    >
                </div>

                <button 
                    v-if="isAdminOrEditor"
                    @click="goToEditor()" 
                    class="bg-brand hover:bg-brand/80 text-white font-bold py-2 px-6 rounded shadow-lg transition-all uppercase tracking-widest text-[10px] whitespace-nowrap"
                >
                    + Añadir Post
                </button>
            </div>
        </header>

        <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 gap-4">
            <div class="w-12 h-12 border-4 border-slate-800 border-t-brand rounded-full animate-spin"></div>
            <p class="text-slate-500 text-[10px] uppercase tracking-widest font-bold">Cargando feed...</p>
        </div>

        <div v-else-if="visiblePosts.length > 0" class="masonry-grid">
            <article 
                v-for="post in visiblePosts" 
                :key="post.id"
                class="masonry-item bg-slate-900/40 border border-slate-800 rounded-lg overflow-hidden hover:border-brand/50 transition-all shadow-xl group relative cursor-pointer"
            >
                <div @click="goToPost(post.id)" class="block w-full overflow-hidden relative bg-black">
                    <img 
                        :src="post.img || '/default-poster.webp'" 
                        :alt="post.title"
                        class="w-full h-auto object-cover opacity-60 group-hover:opacity-100 group-hover:scale-105 transition-all duration-700"
                        loading="lazy"
                    >
                    <div v-if="parseInt(post.visible) === 0" class="absolute top-2 left-2 bg-yellow-500/90 px-2 py-1 rounded text-[8px] font-black uppercase text-black tracking-widest shadow-lg">
                        Borrador
                    </div>
                </div>

                <div class="p-4 flex flex-col gap-1.5">
                    <h3 @click="goToPost(post.id)" class="text-[14px] font-black text-white uppercase leading-tight group-hover:text-brand transition-colors">
                        {{ post.title }}
                    </h3>
                    
                    <p v-if="post.subtitle" class="text-[11px] text-slate-500 italic line-clamp-2 mt-1">
                        {{ post.subtitle }}
                    </p>

                    <div v-if="isAdminOrEditor" class="flex justify-between items-center gap-2 mt-4 pt-4 border-t border-slate-800/50" @click.stop>
                        <button 
                            @click="goToEditor(post.id)"
                            class="flex-1 bg-slate-800 text-slate-400 border border-slate-700 hover:border-brand/50 hover:text-white py-1.5 rounded transition-all uppercase tracking-[0.15em] text-[9px] font-black"
                        >
                            Editar
                        </button>
                        <button 
                            @click="confirmDeletePost(post.id)"
                            class="flex-1 bg-[#BE2B0C]/10 text-[#BE2B0C] border border-transparent hover:bg-[#BE2B0C] hover:text-white py-1.5 rounded transition-all uppercase tracking-[0.15em] text-[9px] font-black"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <div v-else class="py-20 border border-dashed border-slate-800 rounded text-center opacity-40 mt-10">
            <p class="text-slate-500 text-[10px] uppercase tracking-[0.2em] italic font-bold">
                No se encontraron posts.
            </p>
        </div>
        
    </main>
  </div>
</template>

<style scoped>
.content-wrap {
    width: 100%;
    margin-left: auto;
    margin-right: auto;
}

/* --- SISTEMA MASONRY para que se vean con contenido irregular tipo pinterest --- */
.masonry-grid {
    column-count: 1;
    column-gap: 1.5rem; 
}

@media (min-width: 640px) { .masonry-grid { column-count: 2; } }
@media (min-width: 768px) { .masonry-grid { column-count: 3; } }
@media (min-width: 1024px) { .masonry-grid { column-count: 4; } }

.masonry-item {
    break-inside: avoid; 
    margin-bottom: 1.5rem; 
    display: inline-block;
    width: 100%;
}

.line-clamp-2 { 
    display: -webkit-box; 
    -webkit-line-clamp: 2; 
    -webkit-box-orient: vertical; 
    overflow: hidden; 
}
</style>