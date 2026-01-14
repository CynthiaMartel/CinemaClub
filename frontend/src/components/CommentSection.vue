<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';

const props = defineProps(['entryId', 'isAuthenticated', 'currentUserId']);
const comments = ref([]);
const newComment = ref('');
const isSending = ref(false);

const fetchComments = async () => {
  const { data } = await api.get(`/comments/entry/${props.entryId}`);
  comments.value = data.data;
};

const handlePost = async () => {
  if (!newComment.value.trim()) return;
  isSending.value = true;
  try {
    const { data } = await api.post(`/comments/entry/${props.entryId}/create`, { comment: newComment.value });
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
  <section class="max-w-2xl mt-12">
    <h3 class="text-xs font-bold uppercase tracking-[2px] text-gray-500 border-b border-gray-800 pb-2 mb-8">
      {{ comments.length }} Comentarios
    </h3>

    <div v-if="isAuthenticated" class="mb-10">
      <textarea v-model="newComment" placeholder="Añade un comentario..." class="w-full bg-[#2c3440] border-none rounded-md p-4 text-white focus:ring-2 focus:ring-[#00c020] mb-3 resize-none shadow-inner text-sm" rows="3"></textarea>
      <div class="flex justify-end">
        <button @click="handlePost" :disabled="isSending" class="bg-[#00c020] hover:bg-[#00e020] text-white font-bold py-2 px-6 rounded text-xs uppercase tracking-widest transition-all">
          {{ isSending ? 'Enviando...' : 'Publicar' }}
        </button>
      </div>
    </div>

    <div class="space-y-8">
      <div v-for="comment in comments" :key="comment.id" class="flex gap-4">
        <img :src="comment.user?.avatar_url || '/default-avatar.png'" class="w-9 h-9 rounded-full shadow-md">
        <div class="flex-1 text-[13px]">
          <div class="flex items-center gap-2 mb-1.5">
            <span class="text-white font-bold">{{ comment.user?.name }}</span>
            <span class="text-[10px] text-gray-500 uppercase">{{ formatDate(comment.created_at) }}</span>
          </div>
          <div class="text-[#9ab] bg-[#1b2228] p-3 rounded-md border border-gray-800/50">
            {{ comment.comment }}
          </div>
          <button v-if="currentUserId === comment.user_id" @click="handleDelete(comment.id)" class="text-[9px] text-gray-600 hover:text-red-500 mt-2 font-bold uppercase tracking-widest transition-colors">Eliminar</button>
        </div>
      </div>
    </div>
  </section>
</template>

