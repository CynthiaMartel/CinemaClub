<script setup>
import { ref, onMounted, watch } from 'vue';
import api from '@/services/api';
import LoginModal from '@/components/LoginModal.vue'
import { useNavigation } from '@/composables/useNavigation';
import { avatarUrl } from '@/composables/useAvatar';


const props = defineProps({
  entryId: [String, Number],
  isAuthenticated: Boolean,
  currentUserId: [String, Number],
  type: { type: String, default: 'entry' }, 
  accentClass: String
});

const comments = ref([]);
const newComment = ref('');
const isSending = ref(false);

const isLoginOpen = ref(false)
const openLogin = () => { isLoginOpen.value = true }

const fetchComments = async () => {
  if (!props.entryId) return;
  try {
    
    const { data } = await api.get(`/comments/${props.type}/${props.entryId}`);
    comments.value = data.data || data;
  } catch (e) {
    console.error("Error cargando comentarios:", e);
  }
};

const { goProfile } = useNavigation();

watch(() => props.entryId, () => {
  fetchComments();
});

const handlePost = async () => {
  if (!newComment.value.trim()) return;
  isSending.value = true;
  try {
    const { data } = await api.post(`/comments/${props.type}/${props.entryId}/create`, { 
      comment: newComment.value 
    });
    comments.value.unshift(data.data);
    newComment.value = '';
  } finally { isSending.value = false; }
};

const handleDelete = async (id) => {
  if (!confirm('¿Borrar?')) return;
  await api.delete(`/comments/${id}/delete`);
  comments.value = comments.value.filter(c => c.id !== id);
};

const formatDate = (date) => new Date(date).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });

onMounted(fetchComments);
</script>

<template>
  <section class="max-w-3xl mt-16 border-t border-slate-800/50 pt-12">
    <div class="flex items-center gap-3 mb-10">
      <span class="w-1.5 h-6 bg-[#BE2B0C] rounded-full"></span>
      <h3 class="text-[18px] font-black uppercase tracking-[3px] text-slate-400">
        ¿Qué dice la comunidad? <span class="text-slate-600">({{ comments.length }})</span>
      </h3>
    </div>

    <div class="space-y-4">
      <div v-if="isAuthenticated" class="mb-12 group">
        <textarea 
          v-model="newComment" 
          placeholder="¿Qué te parece esta entrada?" 
          class="w-full bg-[#1b2228] border border-white/5 rounded-2xl p-5 text-slate-200 focus:ring-2 focus:ring-brand/30 focus:border-white/10 outline-none mb-4 resize-none transition-all text-sm"
          rows="3"
        ></textarea>
        <div class="flex justify-end">
          <button
            @click="handlePost"
            :disabled="isSending || !newComment.trim()"
            class="text-white font-black py-3 px-8 rounded-xl text-sm uppercase tracking-widest transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed hover:brightness-110 active:scale-[0.97]"
            :class="accentClass || 'bg-emerald-600'"
          >
            {{ isSending ? 'Enviando...' : 'Publicar' }}
          </button>
        </div>
      </div>
      <div v-else class="bg-slate-800/30 p-10 rounded-2xl border border-dashed border-slate-800 text-center">
        <p class="text-slate-500 font-black uppercase text-[10px] tracking-widest">
          ¡Haz <span @click="openLogin" role="button" tabindex="0" @keyup.enter="openLogin" class="text-yellow-500 underline cursor-pointer hover:text-yellow-400 transition-colors">login</span> para participar!
        </p>
      </div>
    </div>

    <div class="space-y-4 sm:space-y-6">
      <div v-for="comment in comments" :key="comment.id" class="flex gap-3 sm:gap-5 animate-fade-in group">
        <div
          @click="goProfile(comment.user.name)"
          class="w-9 h-9 sm:w-11 sm:h-11 bg-slate-800 rounded-full overflow-hidden flex items-center justify-center text-yellow-600 font-black shrink-0 border border-slate-700 text-base sm:text-lg shadow-md cursor-pointer"
        >
          <img
            v-if="avatarUrl(comment.user?.profile?.avatar)"
            :src="avatarUrl(comment.user?.profile?.avatar)"
            class="w-full h-full object-cover"
          />
          <span v-else>{{ comment.user?.name?.charAt(0).toUpperCase() || 'U' }}</span>
        </div>
        
        <div class="flex-1">
          <div class="bg-slate-800/30 border border-slate-800/60 p-3 sm:p-5 rounded-2xl rounded-tl-none group-hover:border-slate-700 transition-colors">
            <div class="flex items-center justify-between mb-2">
              <span @click="goProfile(comment.user.name)"
              class="text-white font-bold text-sm">@{{ comment.user?.name }}</span>
              <span class="text-[9px] text-slate-500 uppercase font-black tracking-tighter">
                {{ formatDate(comment.created_at) }}
              </span>
            </div>
            <p class="text-slate-300 text-sm leading-relaxed italic font-light">
              "{{ comment.comment }}"
            </p>
          </div>
          
          <button 
            v-if="currentUserId === comment.user_id" 
            @click="handleDelete(comment.id)" 
            class="ml-4 mt-2 text-[9px] text-slate-500 hover:text-red-400 font-black uppercase tracking-widest transition-colors cursor-pointer"
          >
            Eliminar
          </button>
        </div>
      </div>
    </div>
  </section>
  
  <LoginModal v-model="isLoginOpen" />
</template>