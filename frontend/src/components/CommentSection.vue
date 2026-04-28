<script setup>
import { ref, onMounted, watch } from 'vue';
import api from '@/services/api';
import LoginModal from '@/components/LoginModal.vue'
import RegisterModal from '@/components/RegisterModal.vue'
import { useNavigation } from '@/composables/useNavigation';
import { avatarUrl } from '@/composables/useAvatar';


const props = defineProps({
  entryId: [String, Number],
  isAuthenticated: Boolean,
  currentUserId: [String, Number],
  type: { type: String, default: 'entry' },
  accentClass: String,
  variant: { type: String, default: 'default' },
});

const comments = ref([]);
const newComment = ref('');
const isSending = ref(false);

const isLoginOpen = ref(false)
const openLogin = () => { isLoginOpen.value = true }

const isRegisterOpen = ref(false)
const openRegister = () => { isRegisterOpen.value = true }

const fetchComments = async () => {
  if (!props.entryId) return;
  try {
    const { data } = await api.get(`/comments/${props.type}/${props.entryId}`);
    comments.value = data.data || data;
    emit('count', comments.value.length);
  } catch (e) {
    console.error("Error cargando comentarios:", e);
  }
};

const emit = defineEmits(['count']);

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
    emit('count', comments.value.length);
  } finally { isSending.value = false; }
};

const handleDelete = async (id) => {
  if (!confirm('¿Borrar?')) return;
  await api.delete(`/comments/${id}/delete`);
  comments.value = comments.value.filter(c => c.id !== id);
  emit('count', comments.value.length);
};

const formatDate = (date) => new Date(date).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });

onMounted(fetchComments);
</script>

<template>

  <!-- DEFAULT mode -->
  <section v-if="variant !== 'thread'" class="max-w-3xl mt-16 border-t border-slate-800/50 pt-12">
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
          <span @click="openRegister" role="button" tabindex="0" @keyup.enter="openRegister" class="text-yellow-500 underline cursor-pointer hover:text-yellow-400 transition-colors">Regístrate</span> para participar o haz <span @click="openLogin" role="button" tabindex="0" @keyup.enter="openLogin" class="text-yellow-500 underline cursor-pointer hover:text-yellow-400 transition-colors">login</span> si ya tienes cuenta
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

  <!-- THREAD mode (debate) -->
  <section v-else class="space-y-2">

    <!-- New comment box -->
    <div v-if="isAuthenticated" class="rounded-xl border border-slate-800/80 bg-[#1a1d21] overflow-hidden mb-4">
      <div class="px-4 pt-3 pb-1 border-b border-slate-800/50 text-[9px] font-black text-slate-600 uppercase tracking-widest">
        Escribe tu comentario
      </div>
      <textarea
        v-model="newComment"
        placeholder="¿Qué opinas sobre este debate?"
        class="w-full bg-transparent px-4 py-3 text-slate-200 outline-none resize-none text-sm placeholder-slate-600"
        rows="3"
      ></textarea>
      <div class="flex justify-end px-4 py-2 bg-slate-900/30 border-t border-slate-800/40">
        <button
          @click="handlePost"
          :disabled="isSending || !newComment.trim()"
          class="text-white font-black py-2 px-6 rounded-lg text-[10px] uppercase tracking-widest transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed hover:brightness-110 active:scale-[0.97]"
          :class="accentClass || 'bg-orange-600'"
        >
          {{ isSending ? 'Enviando...' : 'Comentar' }}
        </button>
      </div>
    </div>
    <div v-else class="rounded-xl border border-dashed border-slate-800/80 bg-[#1a1d21]/50 px-5 py-4 mb-4 text-center">
      <p class="text-slate-500 text-[10px] font-black uppercase tracking-widest">
        <span @click="openRegister" role="button" class="text-orange-400 cursor-pointer hover:underline">Regístrate</span>
        <span class="text-slate-700"> o </span>
        <span @click="openLogin" role="button" class="text-orange-400 cursor-pointer hover:underline">inicia sesión</span>
        <span> para unirte al debate</span>
      </p>
    </div>

    <!-- Thread comments list -->
    <div v-if="comments.length === 0" class="py-8 text-center">
      <p class="text-[10px] text-slate-600 uppercase tracking-widest">Sé el primero en comentar.</p>
    </div>

    <div
      v-for="comment in comments"
      :key="comment.id"
      class="flex gap-0 animate-fade-in pt-4 border-t border-slate-800/40 first:border-t-0 first:pt-0"
    >
      <!-- Left thread line + avatar -->
      <div class="flex flex-col items-center mr-3">
        <div
          @click="goProfile(comment.user.name)"
          class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 overflow-hidden flex items-center justify-center text-orange-400 font-black text-sm shrink-0 cursor-pointer hover:border-orange-500 transition-colors"
        >
          <img
            v-if="avatarUrl(comment.user?.profile?.avatar)"
            :src="avatarUrl(comment.user?.profile?.avatar)"
            class="w-full h-full object-cover"
          />
          <span v-else>{{ comment.user?.name?.charAt(0).toUpperCase() || 'U' }}</span>
        </div>
        <div class="w-px flex-1 mt-2 bg-slate-800/80 min-h-[16px]"></div>
      </div>

      <!-- Comment body -->
      <div class="flex-1 pb-3 min-w-0">
        <!-- Meta row -->
        <div class="flex items-center gap-2 mb-1.5 flex-wrap">
          <span
            @click="goProfile(comment.user.name)"
            class="text-[11px] font-black text-slate-300 cursor-pointer hover:text-orange-400 transition-colors"
          >@{{ comment.user?.name }}</span>
          <span class="text-slate-700 text-[10px]">·</span>
          <span class="text-[10px] text-slate-600 font-bold">{{ formatDate(comment.created_at) }}</span>
        </div>

        <!-- Text -->
        <p class="text-slate-300 text-sm leading-relaxed mb-2">
          {{ comment.comment }}
        </p>

        <div v-if="currentUserId === comment.user_id" class="-ml-1 mt-1">
          <button
            @click="handleDelete(comment.id)"
            class="px-2 py-1 rounded text-[9px] font-black text-slate-700 hover:text-red-400 hover:bg-red-500/10 uppercase tracking-wide transition-colors"
          >
            Eliminar
          </button>
        </div>
      </div>
    </div>

  </section>

  <LoginModal v-model="isLoginOpen" />
  <RegisterModal v-model="isRegisterOpen" />
</template>