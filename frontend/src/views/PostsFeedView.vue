<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api' // Instancia de Axios

// --- STORES Y ROUTER 
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
        // Mostramos si es visible O si el usuario es admin/editor
        return esVisible || isAdminOrEditor.value;
    });
});

// --- MÉTODOS ---

// Obtener Posts
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
    router.push(`/post-reed/${id}`)
}

// Ir a la vista del Editor para crear/editar
const goToEditor = (id = null) => {
    if (id) {
        router.push({ name: 'post-editor', params: { id } })
    } else {
        router.push({ name: 'post-editor' })
    }
}

// Eliminar post
const confirmDeletePost = async (id) => {
    if (!confirm('¿Estás seguro de que deseas eliminar este post? Esta acción no se puede deshacer.')) return;
    
    try {
        await api.delete(`/post-destroy/${id}`)
        fetchPosts()
    } catch (error) {
        console.error("Error al eliminar post:", error)
        alert('Hubo un error al intentar eliminar el post.');
    }
}

// Función auxiliar para iniciales
const getInitials = (name) => {
    return name ? name.substring(0, 1).toUpperCase() : 'C';
}

//---
onMounted(() => {
    fetchPosts()
})
</script>
<template>
  <div class="min-h-screen w-full bg-[#14181c] text-[#9ab] font-sans overflow-x-hidden pb-20 selection:bg-[#BE2B0C]/40">
    
    <main class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10 relative z-10">
        
        <header class="flex flex-col md:flex-row items-center justify-between gap-6 mb-12 border-b border-white/10 pb-6">
            <h1 class="text-3xl md:text-5xl font-serif font-black text-white tracking-tight w-full md:w-auto text-center md:text-left">
                Últimas Noticias
            </h1>
            
            <div class="flex items-center gap-4 w-full md:w-auto">
                <div class="relative w-full md:w-64">
                    <input 
                        v-model="searchQuery" 
                        @keyup.enter="performSearch"
                        type="text" 
                        placeholder="Buscar..."
                        class="w-full bg-[#1b2228] border border-white/10 rounded px-3 py-1.5 text-xs text-white focus:border-[#BE2B0C] outline-none transition-colors placeholder-slate-500"
                    >
                </div>

                <button 
                    v-if="isAdminOrEditor"
                    @click="goToEditor()" 
                    class="bg-[#BE2B0C] hover:bg-[#a02208] text-white font-bold py-2 px-6 rounded shadow-lg transition-all uppercase tracking-widest text-[10px] whitespace-nowrap"
                >
                    + Nuevo
                </button>
            </div>
        </header>

        <div v-if="isLoading" class="flex flex-col items-center justify-center py-20 gap-4">
            <div class="w-12 h-12 border-4 border-slate-800 border-t-[#BE2B0C] rounded-full animate-spin"></div>
            <p class="text-slate-500 text-[10px] uppercase tracking-widest font-bold">Cargando...</p>
        </div>

        <div v-else-if="visiblePosts.length > 0" class="masonry-grid">
            
            <article 
                v-for="post in visiblePosts" 
                :key="post.id"
                class="masonry-item mb-8 break-inside-avoid group"
            >
                <div class="relative w-full rounded-lg overflow-hidden border border-white/10 bg-[#1b2228] shadow-md hover:border-white/40 transition-all duration-300">
                    
                    <figure class="relative w-full m-0 cursor-pointer overflow-hidden">
                        <div @click="goToPost(post.id)" class="w-full relative block">
                            <img 
                                :src="post.img || '/default-poster.webp'" 
                                :alt="post.title"
                                class="w-full h-auto object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-700 block"
                                loading="lazy"
                            >
                            <div v-if="parseInt(post.visible) === 0" class="absolute top-2 right-2 bg-yellow-500 text-black px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-widest shadow-md z-10">
                                Borrador
                            </div>
                        </div>
                    </figure>

                    <div class="p-4 flex flex-col gap-3">
                        
                        <div class="flex items-center gap-2">
                            <div class="w-5 h-5 rounded-full bg-slate-700 flex items-center justify-center text-[8px] font-bold text-white overflow-hidden border border-white/20 shrink-0">
                                 {{ getInitials(post.editorName) }}
                            </div>
                            <span class="text-[10px] font-bold text-[#899] uppercase hover:text-white transition-colors cursor-default tracking-wide">
                                {{ post.editorName || 'CinemaClub' }}
                            </span>
                        </div>

                        <h3 class="leading-tight">
                            <a @click.prevent="goToPost(post.id)" href="#" class="text-[18px] md:text-[20px] font-serif font-bold text-white hover:text-[#BE2B0C] transition-colors leading-tight block">
                                {{ post.title }}
                            </a>
                        </h3>
                        
                        <div class="text-[13px] text-[#9ab] leading-relaxed line-clamp-3 font-normal">
                            <p>{{ post.subtitle }}</p>
                        </div>

                        <div class="mt-1">
                            <a @click.prevent="goToPost(post.id)" href="#" class="text-[10px] font-black uppercase tracking-[0.1em] text-[#678] hover:text-white transition-colors border-b border-transparent hover:border-white/50 pb-0.5">
                                Leer Noticia
                            </a>
                        </div>

                        <div v-if="isAdminOrEditor" class="flex gap-3 pt-3 border-t border-white/5 opacity-0 group-hover:opacity-100 transition-opacity duration-300 mt-1">
                            <button 
                                @click="goToEditor(post.id)" 
                                class="text-[9px] text-blue-400 hover:text-blue-300 uppercase font-bold tracking-wider"
                            >
                                Editar
                            </button>
                            <button 
                                @click="confirmDeletePost(post.id)" 
                                class="text-[9px] text-red-500 hover:text-red-400 uppercase font-bold tracking-wider"
                            >
                                Borrar
                            </button>
                        </div>
                    </div>

                </div>
            </article>

        </div>

        <div v-else class="py-20 border border-dashed border-white/10 rounded text-center mt-10 opacity-60">
            <p class="text-slate-500 text-[10px] uppercase tracking-[0.2em] font-bold">
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

.font-serif {
    font-family: 'Tiempos Headline', Georgia, serif;
}

/* --- SISTEMA MASONRY (Estilo Pinterest) --- */

.masonry-grid {
    column-count: 1;
    column-gap: 2rem;
}

@media (min-width: 640px) { .masonry-grid { column-count: 2; } }
@media (min-width: 768px) { .masonry-grid { column-count: 3; } }
@media (min-width: 1024px) { .masonry-grid { column-count: 4; } }

.masonry-item {
    break-inside: avoid; 
    display: inline-block;
    width: 100%;
}

.line-clamp-3 { 
    display: -webkit-box; 
    -webkit-line-clamp: 3; 
    -webkit-box-orient: vertical; 
    overflow: hidden; 
}
</style>