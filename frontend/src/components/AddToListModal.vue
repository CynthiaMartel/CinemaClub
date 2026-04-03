<script setup>
import { ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const props = defineProps({
  modelValue: { type: Boolean, required: true },
  filmId:     { type: [Number, String], required: true },
})
const emit = defineEmits(['update:modelValue'])
const close = () => emit('update:modelValue', false)

const router = useRouter()
const auth   = useAuthStore()

const lists      = ref([])
const isLoading  = ref(false)
const addingId   = ref(null)   // id de la lista que está procesando
const doneIds    = ref([])     // listas donde ya se añadió esta película en esta sesión
const errorMsg   = ref(null)

const fetchUserLists = async () => {
  if (!auth.user?.id) return
  isLoading.value = true
  errorMsg.value  = null
  try {
    const { data } = await api.get(`/user_profiles/${auth.user.id}/lists`)
    lists.value = data.data ?? data ?? []
  } catch {
    errorMsg.value = 'No se pudieron cargar tus listas.'
  } finally {
    isLoading.value = false
  }
}

const addFilmToList = async (list) => {
  if (addingId.value || doneIds.value.includes(list.id)) return
  addingId.value = list.id
  errorMsg.value = null
  try {
    await api.post('/user_entry_films/create', {
      user_entry_id: list.id,
      film_id: props.filmId,
    })
    doneIds.value.push(list.id)
  } catch (e) {
    errorMsg.value = e.response?.data?.message ?? 'Error al añadir la película.'
  } finally {
    addingId.value = null
  }
}

const goCreateList = () => {
  close()
  router.push({ name: 'create-entry', params: { id: auth.user.id } })
}

// Carga las listas cada vez que se abre el modal
watch(() => props.modelValue, (open) => {
  if (open) {
    doneIds.value  = []
    errorMsg.value = null
    fetchUserLists()
  }
})
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-[100] flex items-center justify-center p-4">

      <!-- Backdrop -->
      <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-md" @click="close" />

      <!-- Panel -->
      <div class="relative bg-[#1b2228] border border-white/10 w-full max-w-sm rounded-xl shadow-2xl overflow-hidden">

        <!-- Cabecera -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-white/5">
          <h3 class="text-[11px] font-black text-white uppercase tracking-[0.2em]">Añadir a lista</h3>
          <button @click="close" class="text-slate-500 hover:text-white transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Cuerpo -->
        <div class="p-4 flex flex-col gap-2 max-h-[60vh] overflow-y-auto brand-scroll">

          <!-- Crear nueva lista -->
          <button
            @click="goCreateList"
            class="w-full flex items-center gap-3 px-4 py-3 border border-dashed border-white/10 rounded-lg text-slate-500 text-[10px] hover:border-brand hover:text-brand transition-all font-black uppercase tracking-widest"
          >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4 flex-shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            Crear nueva lista
          </button>

          <!-- Loading -->
          <div v-if="isLoading" class="flex justify-center py-8">
            <div class="w-6 h-6 border-2 border-slate-800 border-t-brand rounded-full animate-spin"></div>
          </div>

          <!-- Sin listas -->
          <div v-else-if="!lists.length" class="py-6 text-center">
            <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">Aún no tienes listas creadas.</p>
          </div>

          <!-- Listas del usuario -->
          <template v-else>
            <button
              v-for="list in lists"
              :key="list.id"
              @click="addFilmToList(list)"
              :disabled="!!addingId || doneIds.includes(list.id)"
              class="w-full flex items-center gap-3 px-4 py-3 rounded-lg border transition-all text-left"
              :class="doneIds.includes(list.id)
                ? 'border-brand/40 bg-brand/5 cursor-default'
                : 'border-white/5 bg-white/[0.02] hover:border-white/20 hover:bg-white/5 cursor-pointer'"
            >
              <!-- Miniatura de la lista -->
              <div class="w-8 h-12 flex-shrink-0 rounded overflow-hidden bg-slate-800 border border-white/5">
                <img
                  v-if="list.films?.[0]?.frame"
                  :src="list.films[0].frame"
                  class="w-full h-full object-cover"
                />
              </div>

              <!-- Nombre -->
              <span class="flex-1 text-[11px] font-bold text-slate-200 truncate">{{ list.title }}</span>

              <!-- Estado -->
              <span v-if="addingId === list.id" class="flex-shrink-0">
                <svg class="w-4 h-4 text-slate-400 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
              </span>
              <span v-else-if="doneIds.includes(list.id)" class="flex-shrink-0 text-brand">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
              </span>
            </button>
          </template>

          <!-- Error -->
          <p v-if="errorMsg" class="text-[10px] text-red-400 text-center font-bold mt-1">{{ errorMsg }}</p>
        </div>

      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.brand-scroll::-webkit-scrollbar { width: 4px; }
.brand-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 10px; }
.brand-scroll::-webkit-scrollbar-thumb { background: #BE2B0C; border-radius: 10px; }
</style>
