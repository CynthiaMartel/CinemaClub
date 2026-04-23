<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const isEdit = computed(() => !!route.params.id)
const filmId = computed(() => route.params.id || null)

// --- Estado formulario ---
const form = ref({
  title: '',
  original_title: '',
  genre: '',
  origin_country: '',
  original_language: '',
  overview: '',
  duration: '',
  release_date: '',
  frame: '',
  backdrop: '',
  tmdb_id: '',
  wikidata_id: '',
  vote_average: '',
  globalRate: '',
  total_awards: '',
  total_nominations: '',
  total_festivals: '',
  director_id: null,
})

const directorQuery = ref('')
const directorResults = ref([])
const selectedDirector = ref(null)
let directorTimeout = null

const isSaving = ref(false)
const isLoadingFilm = ref(false)
const errors = ref({})

// --- Toast ---
const toast = ref(null)
function showToast(msg, type = 'ok') {
  toast.value = { msg, type }
  setTimeout(() => (toast.value = null), 3500)
}

// --- Cargar datos del film para edición ---
async function loadFilm() {
  isLoadingFilm.value = true
  try {
    const { data } = await api.get(`/films/${filmId.value}`)
    const f = data
    form.value = {
      title: f.title ?? '',
      original_title: f.original_title ?? '',
      genre: f.genre ?? '',
      origin_country: f.origin_country ?? '',
      original_language: f.original_language ?? '',
      overview: f.overview ?? '',
      duration: f.duration ?? '',
      release_date: f.release_date ? String(f.release_date).slice(0, 10) : '',
      frame: f.frame ?? '',
      backdrop: f.backdrop ?? '',
      tmdb_id: f.tmdb_id ?? '',
      wikidata_id: f.wikidata_id ?? '',
      vote_average: f.vote_average ?? '',
      globalRate: f.globalRate ?? '',
      total_awards: f.total_awards ?? '',
      total_nominations: f.total_nominations ?? '',
      total_festivals: f.total_festivals ?? '',
      director_id: null,
    }
    // Si hay director en cast, pre-cargarlo
    const director = (f.cast || []).find(p => p.pivot?.role === 'Director')
    if (director) {
      selectedDirector.value = { idPerson: director.idPerson, name: director.name, photo: director.photo }
      directorQuery.value = director.name
      form.value.director_id = director.idPerson
    }
  } catch {
    showToast('Error al cargar la película', 'err')
  } finally {
    isLoadingFilm.value = false
  }
}

// --- Autocompletado director ---
function onDirectorInput() {
  clearTimeout(directorTimeout)
  if (directorQuery.value.length < 2) {
    directorResults.value = []
    return
  }
  directorTimeout = setTimeout(searchDirector, 350)
}

async function searchDirector() {
  try {
    const { data } = await api.get('/admin/cast-search', { params: { q: directorQuery.value } })
    directorResults.value = data.data || []
  } catch {
    directorResults.value = []
  }
}

function selectDirector(person) {
  selectedDirector.value = person
  form.value.director_id = person.idPerson
  directorQuery.value = person.name
  directorResults.value = []
}

function clearDirector() {
  selectedDirector.value = null
  form.value.director_id = null
  directorQuery.value = ''
  directorResults.value = []
}

// --- Enviar formulario ---
async function submit() {
  isSaving.value = true
  errors.value = {}

  // Limpiar campos vacíos opcionales (para no enviar strings vacíos como null)
  const payload = {}
  for (const [k, v] of Object.entries(form.value)) {
    if (v === '' || v === null || v === undefined) {
      payload[k] = null
    } else {
      payload[k] = v
    }
  }
  // Convertir numéricos
  if (payload.duration) payload.duration = parseInt(payload.duration)
  if (payload.tmdb_id) payload.tmdb_id = parseInt(payload.tmdb_id)
  if (payload.wikidata_id) payload.wikidata_id = parseInt(payload.wikidata_id)
  if (payload.total_awards) payload.total_awards = parseInt(payload.total_awards)
  if (payload.total_nominations) payload.total_nominations = parseInt(payload.total_nominations)
  if (payload.total_festivals) payload.total_festivals = parseInt(payload.total_festivals)
  if (payload.vote_average) payload.vote_average = parseFloat(payload.vote_average)
  if (payload.globalRate) payload.globalRate = parseFloat(payload.globalRate)

  try {
    if (isEdit.value) {
      await api.put(`/films/${filmId.value}/update`, payload)
      showToast('Película actualizada correctamente')
    } else {
      await api.post('/films/store', payload)
      showToast('Película creada correctamente')
    }
    setTimeout(() => router.push({ name: 'admin-dashboard' }), 1200)
  } catch (err) {
    const data = err?.response?.data
    if (err?.response?.status === 422 && data?.errors) {
      errors.value = data.errors
      showToast('Revisa los errores del formulario', 'err')
    } else {
      showToast(data?.message || 'Error inesperado', 'err')
    }
  } finally {
    isSaving.value = false
  }
}

function goBack() {
  router.push({ name: 'admin-dashboard' })
}

onMounted(() => {
  if (!auth.user || auth.user.idRol != 1) {
    router.replace({ name: 'home' })
    return
  }
  if (isEdit.value) loadFilm()
})
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-24">

    <!-- Toast -->
    <Transition name="fade">
      <div
        v-if="toast"
        :class="[
          'fixed top-5 right-5 z-50 px-5 py-3 rounded-lg text-sm font-medium shadow-xl',
          toast.type === 'err' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white'
        ]"
      >{{ toast.msg }}</div>
    </Transition>

    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10">

      <!-- Cabecera -->
      <div class="flex items-center gap-3 mb-8">
        <button @click="goBack" class="p-2 rounded-lg text-slate-500 hover:text-white hover:bg-white/5 transition">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500 block">Panel de administración</span>
          <h1 class="text-xl font-bold text-white">{{ isEdit ? 'Editar película' : 'Añadir película' }}</h1>
        </div>
      </div>

      <!-- Loading para edición -->
      <div v-if="isLoadingFilm" class="space-y-4 animate-pulse">
        <div v-for="i in 6" :key="i" class="h-10 bg-slate-800 rounded-lg"></div>
      </div>

      <form v-else @submit.prevent="submit" class="space-y-8">

        <!-- SECCIÓN: Datos básicos -->
        <section class="bg-[#1c2128] border border-slate-800 rounded-xl p-4 sm:p-6 space-y-5">
          <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 border-b border-slate-800 pb-3">Datos básicos</h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- Título -->
            <div>
              <label class="field-label">Título <span class="text-red-400">*</span></label>
              <input v-model="form.title" type="text" class="field-input" :class="{ 'border-red-500/70': errors.title }" placeholder="Título en castellano o principal" />
              <p v-if="errors.title" class="field-error">{{ errors.title[0] }}</p>
            </div>
            <!-- Título original -->
            <div>
              <label class="field-label">Título original <span class="text-red-400">*</span></label>
              <input v-model="form.original_title" type="text" class="field-input" :class="{ 'border-red-500/70': errors.original_title }" placeholder="Título en idioma original" />
              <p v-if="errors.original_title" class="field-error">{{ errors.original_title[0] }}</p>
            </div>
          </div>

          <!-- Sinopsis -->
          <div>
            <label class="field-label">Sinopsis</label>
            <textarea v-model="form.overview" rows="4" class="field-input resize-none" placeholder="Descripción de la película..."></textarea>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <!-- Año / fecha -->
            <div>
              <label class="field-label">Fecha de estreno</label>
              <input v-model="form.release_date" type="date" class="field-input" :class="{ 'border-red-500/70': errors.release_date }" />
              <p v-if="errors.release_date" class="field-error">{{ errors.release_date[0] }}</p>
            </div>
            <!-- Duración -->
            <div>
              <label class="field-label">Duración (min)</label>
              <input v-model="form.duration" type="number" min="1" max="65535" class="field-input" placeholder="Ej: 120" />
            </div>
            <!-- Idioma -->
            <div>
              <label class="field-label">Idioma original</label>
              <input v-model="form.original_language" type="text" class="field-input" placeholder="Ej: en, es, fr" />
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <!-- Género -->
            <div>
              <label class="field-label">Género(s)</label>
              <input v-model="form.genre" type="text" class="field-input" placeholder="Ej: Drama, Thriller" />
            </div>
            <!-- País -->
            <div>
              <label class="field-label">País de origen</label>
              <input v-model="form.origin_country" type="text" class="field-input" placeholder="Ej: US, FR, ES" />
            </div>
          </div>
        </section>

        <!-- SECCIÓN: Director -->
        <section class="bg-[#1c2128] border border-slate-800 rounded-xl p-4 sm:p-6 space-y-4">
          <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 border-b border-slate-800 pb-3">Director</h2>
          <p class="text-xs text-slate-500">Busca el director en la base de datos de personas. Si no existe aún, puedes dejarlo vacío y asignarlo después.</p>

          <!-- Director seleccionado -->
          <div v-if="selectedDirector" class="flex items-center gap-3 p-3 bg-[#00e054]/10 border border-[#00e054]/30 rounded-lg">
            <img v-if="selectedDirector.photo" :src="selectedDirector.photo" class="w-9 h-9 rounded-full object-cover" />
            <div v-else class="w-9 h-9 rounded-full bg-slate-700 flex items-center justify-center text-slate-400 text-xs font-bold">{{ selectedDirector.name?.charAt(0) }}</div>
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-white">{{ selectedDirector.name }}</p>
              <p class="text-xs text-slate-400">ID: {{ selectedDirector.idPerson }}</p>
            </div>
            <button type="button" @click="clearDirector" class="text-slate-500 hover:text-red-400 transition p-1">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Autocompletado -->
          <div v-else class="relative">
            <input
              v-model="directorQuery"
              @input="onDirectorInput"
              type="text"
              class="field-input"
              placeholder="Buscar por nombre... (mín. 2 caracteres)"
            />
            <ul v-if="directorResults.length" class="absolute z-20 w-full mt-1 bg-[#1c2128] border border-slate-700 rounded-lg overflow-hidden shadow-xl">
              <li
                v-for="person in directorResults"
                :key="person.idPerson"
                @click="selectDirector(person)"
                class="flex items-center gap-3 px-3 py-2.5 hover:bg-white/5 cursor-pointer transition"
              >
                <img v-if="person.photo" :src="person.photo" class="w-8 h-8 rounded-full object-cover flex-shrink-0" />
                <div v-else class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-slate-400 text-xs font-bold flex-shrink-0">{{ person.name?.charAt(0) }}</div>
                <div>
                  <p class="text-sm text-slate-100">{{ person.name }}</p>
                  <p class="text-xs text-slate-500">ID {{ person.idPerson }}</p>
                </div>
              </li>
            </ul>
          </div>
          <p v-if="errors.director_id" class="field-error">{{ errors.director_id[0] }}</p>
        </section>

        <!-- SECCIÓN: Imágenes -->
        <section class="bg-[#1c2128] border border-slate-800 rounded-xl p-4 sm:p-6 space-y-5">
          <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 border-b border-slate-800 pb-3">Imágenes</h2>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <!-- Póster -->
            <div>
              <label class="field-label">URL del póster (frame)</label>
              <input v-model="form.frame" type="url" maxlength="225" class="field-input" :class="{ 'border-red-500/70': errors.frame }" placeholder="https://..." />
              <p v-if="errors.frame" class="field-error">{{ errors.frame[0] }}</p>
              <div v-if="form.frame" class="mt-2">
                <img :src="form.frame" alt="Póster" class="h-32 rounded-lg object-cover border border-slate-700" @error="e => e.target.style.display='none'" />
              </div>
            </div>
            <!-- Backdrop -->
            <div>
              <label class="field-label">URL del backdrop</label>
              <input v-model="form.backdrop" type="url" maxlength="255" class="field-input" :class="{ 'border-red-500/70': errors.backdrop }" placeholder="https://..." />
              <p v-if="errors.backdrop" class="field-error">{{ errors.backdrop[0] }}</p>
              <div v-if="form.backdrop" class="mt-2">
                <img :src="form.backdrop" alt="Backdrop" class="h-32 w-full rounded-lg object-cover border border-slate-700" @error="e => e.target.style.display='none'" />
              </div>
            </div>
          </div>
        </section>

        <!-- SECCIÓN: Puntuaciones -->
        <section class="bg-[#1c2128] border border-slate-800 rounded-xl p-4 sm:p-6 space-y-5">
          <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 border-b border-slate-800 pb-3">Puntuaciones</h2>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="field-label">Nota media usuarios (0–10)</label>
              <input v-model="form.vote_average" type="number" step="0.1" min="0" max="10" class="field-input" placeholder="Ej: 7.5" />
              <p v-if="errors.vote_average" class="field-error">{{ errors.vote_average[0] }}</p>
            </div>
            <div>
              <label class="field-label">Nota global TMDB (0–10)</label>
              <input v-model="form.globalRate" type="number" step="0.1" min="0" max="10" class="field-input" placeholder="Ej: 8.1" />
              <p v-if="errors.globalRate" class="field-error">{{ errors.globalRate[0] }}</p>
            </div>
          </div>
        </section>

        <!-- SECCIÓN: IDs externos -->
        <section class="bg-[#1c2128] border border-slate-800 rounded-xl p-4 sm:p-6 space-y-5">
          <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 border-b border-slate-800 pb-3">IDs externos</h2>
          <p class="text-xs text-slate-500">Opcionales. Si no encuentras el ID puedes dejarlo vacío y asignarlo después. Evitan duplicados y habilitan funciones como streaming o datos enriquecidos.</p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <div>
              <label class="field-label">TMDB ID</label>
              <input v-model="form.tmdb_id" type="number" class="field-input" :class="{ 'border-red-500/70': errors.tmdb_id }" placeholder="Ej: 157336" />
              <p class="text-[11px] text-slate-600 mt-1">El número al final de themoviedb.org/movie/<strong class="text-slate-500">157336</strong></p>
              <p v-if="errors.tmdb_id" class="field-error">{{ errors.tmdb_id[0] }}</p>
            </div>
            <div>
              <label class="field-label">Wikidata ID</label>
              <input v-model="form.wikidata_id" type="number" class="field-input" :class="{ 'border-red-500/70': errors.wikidata_id }" placeholder="Ej: 11252" />
              <p class="text-[11px] text-slate-600 mt-1">Solo el número de wikidata.org/wiki/Q<strong class="text-slate-500">11252</strong> (sin la Q)</p>
              <p v-if="errors.wikidata_id" class="field-error">{{ errors.wikidata_id[0] }}</p>
            </div>
          </div>
        </section>

        <!-- SECCIÓN: Premios y festivales -->
        <section class="bg-[#1c2128] border border-slate-800 rounded-xl p-4 sm:p-6 space-y-5">
          <h2 class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 border-b border-slate-800 pb-3">Premios y festivales</h2>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
            <div>
              <label class="field-label">Total premios</label>
              <input v-model="form.total_awards" type="number" min="0" max="65535" class="field-input" placeholder="0" />
            </div>
            <div>
              <label class="field-label">Total nominaciones</label>
              <input v-model="form.total_nominations" type="number" min="0" max="65535" class="field-input" placeholder="0" />
            </div>
            <div>
              <label class="field-label">Total festivales</label>
              <input v-model="form.total_festivals" type="number" min="0" max="65535" class="field-input" placeholder="0" />
            </div>
          </div>
        </section>

        <!-- Botones -->
        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3 pt-2">
          <button type="button" @click="goBack" class="w-full sm:w-auto px-5 py-2.5 rounded-lg text-sm text-slate-400 hover:text-white transition text-center">
            Cancelar
          </button>
          <button
            type="submit"
            :disabled="isSaving"
            class="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-[#00e054] text-[#14181c] text-sm font-bold hover:bg-[#00c94a] disabled:opacity-60 disabled:cursor-not-allowed transition-colors"
          >
            <svg v-if="isSaving" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
            {{ isSaving ? 'Guardando…' : (isEdit ? 'Guardar cambios' : 'Crear película') }}
          </button>
        </div>

      </form>
    </div>
  </div>
</template>

<style scoped>
@reference "@/assets/main.css";

.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

.field-label {
  @apply block text-xs font-medium text-slate-400 mb-1.5;
}
.field-input {
  @apply w-full bg-[#14181c] border border-slate-700 rounded-lg px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-600 focus:outline-none focus:border-[#00e054]/60 transition;
}
.field-error {
  @apply mt-1 text-xs text-red-400;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
