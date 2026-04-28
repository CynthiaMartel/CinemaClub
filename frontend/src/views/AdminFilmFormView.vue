<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const isEdit = computed(() => !!route.params.id)
const filmId = computed(() => route.params.id || null)

// --- Estado formulario ---
const form = ref({
  title: '', original_title: '', genre: '', origin_country: '',
  original_language: '', overview: '', duration: '', release_date: '',
  frame: '', backdrop: '', tmdb_id: '', wikidata_id: '',
  vote_average: '', globalRate: '',
  total_awards: '', total_nominations: '', total_festivals: '',
  director_id: null,
  awards: [], nominations: [], festivals: [],
})

const isSaving      = ref(false)
const isLoadingFilm = ref(false)
const errors        = ref({})

// --- Director ---
// mode: 'search' | 'id' | 'new'
const directorMode    = ref('search')
const directorQuery   = ref('')
const directorResults = ref([])
const selectedDirector = ref(null)
const directorIdInput  = ref('')
const newDirName       = ref('')
const newDirPhoto      = ref('')
const newDirTmdbId     = ref('')
const isCreatingDir    = ref(false)
let directorTimeout = null

// --- Reparto ---
const castList          = ref([])
const showCastForm      = ref(false)
// castInputMode: 'search' | 'id' | 'new'
const castInputMode     = ref('search')
const castQuery         = ref('')
const castResults       = ref([])
const castIdInput       = ref('')
const pendingCastPerson = ref(null)
const newCastRole       = ref('Actor')
const newCastCharacter  = ref('')
const newCastOrder      = ref('')
const newPersonName     = ref('')
const newPersonPhoto    = ref('')
const newPersonTmdbId   = ref('')
const isCreatingPerson  = ref(false)
let castTimeout = null

// --- Premios / Nominaciones / Festivales ---
const newAward       = ref('')
const newNomination  = ref('')
const newFestival    = ref('')

// --- Importación TMDB ---
const tmdbImportId = ref('')
const isFetching   = ref(false)
const tmdbPreview  = ref(null)
const tmdbError    = ref('')

// --- Toast ---
const toast = ref(null)
function showToast(msg, type = 'ok') {
  toast.value = { msg, type }
  setTimeout(() => (toast.value = null), 3500)
}

// --- Cargar datos para edición ---
async function loadFilm() {
  isLoadingFilm.value = true
  try {
    const { data } = await api.get(`/films/${filmId.value}`)
    const f = data
    form.value = {
      title:             f.title ?? '',
      original_title:    f.original_title ?? '',
      genre:             f.genre ?? '',
      origin_country:    f.origin_country ?? '',
      original_language: f.original_language ?? '',
      overview:          f.overview ?? '',
      duration:          f.duration ?? '',
      release_date:      f.release_date ? String(f.release_date).slice(0, 10) : '',
      frame:             f.frame ?? '',
      backdrop:          f.backdrop ?? '',
      tmdb_id:           f.tmdb_id ?? '',
      wikidata_id:       f.wikidata_id ?? '',
      vote_average:      f.vote_average ?? '',
      globalRate:        f.globalRate ?? '',
      total_awards:      f.total_awards ?? '',
      total_nominations: f.total_nominations ?? '',
      total_festivals:   f.total_festivals ?? '',
      director_id:       null,
      awards:       Array.isArray(f.awards)       ? [...f.awards]       : [],
      nominations:  Array.isArray(f.nominations)  ? [...f.nominations]  : [],
      festivals:    Array.isArray(f.festivals)     ? [...f.festivals]    : [],
    }
    const allCast = f.cast || []
    const director = allCast.find(p => p.pivot?.role === 'Director')
    if (director) {
      selectedDirector.value = { idPerson: director.idPerson, name: director.name, photo: director.photo }
      directorQuery.value    = director.name
      form.value.director_id = director.idPerson
    }
    castList.value = allCast
      .filter(p => p.pivot?.role !== 'Director')
      .map(p => ({
        idPerson:       p.idPerson,
        name:           p.name,
        photo:          p.photo,
        role:           p.pivot.role,
        character_name: p.pivot.character_name || '',
        credit_order:   p.pivot.credit_order ?? 0,
      }))
  } catch { showToast('Error al cargar la película', 'err') }
  finally   { isLoadingFilm.value = false }
}

// ────── Director ──────────────────────────────────────────────────────────────
function onDirectorInput() {
  clearTimeout(directorTimeout)
  if (directorQuery.value.length < 2) { directorResults.value = []; return }
  directorTimeout = setTimeout(async () => {
    try {
      const { data } = await api.get('/admin/cast-search', { params: { q: directorQuery.value } })
      directorResults.value = data.data || []
    } catch { directorResults.value = [] }
  }, 350)
}
function selectDirector(person) {
  selectedDirector.value = person
  form.value.director_id = person.idPerson
  directorQuery.value    = person.name
  directorResults.value  = []
}
function clearDirector() {
  selectedDirector.value = null
  form.value.director_id = null
  directorQuery.value    = ''
  directorIdInput.value  = ''
  directorResults.value  = []
  newDirName.value = ''; newDirPhoto.value = ''; newDirTmdbId.value = ''
}
async function lookupDirectorById() {
  if (!directorIdInput.value) return
  try {
    const { data } = await api.get('/admin/cast-search', { params: { q: String(directorIdInput.value) } })
    const person = (data.data || []).find(p => p.idPerson === Number(directorIdInput.value))
    if (person) selectDirector(person)
    else showToast('No se encontró ninguna persona con ese ID', 'err')
  } catch { showToast('Error al buscar persona', 'err') }
}
async function createDirector() {
  if (!newDirName.value.trim()) return
  isCreatingDir.value = true
  try {
    const { data } = await api.post('/admin/cast-crew/store', {
      name:    newDirName.value.trim(),
      photo:   newDirPhoto.value.trim()  || null,
      tmdb_id: newDirTmdbId.value        ? parseInt(newDirTmdbId.value) : null,
    })
    selectDirector({ idPerson: data.data.idPerson, name: data.data.name, photo: data.data.photo })
    newDirName.value = ''; newDirPhoto.value = ''; newDirTmdbId.value = ''
    showToast('Persona creada y asignada como director/a')
  } catch (err) {
    const msg = err?.response?.data?.errors?.tmdb_id?.[0]
      || err?.response?.data?.message || 'Error al crear persona'
    showToast(msg, 'err')
  } finally { isCreatingDir.value = false }
}

// ────── Reparto ────────────────────────────────────────────────────────────────
function onCastInput() {
  clearTimeout(castTimeout)
  if (castQuery.value.length < 2) { castResults.value = []; return }
  castTimeout = setTimeout(async () => {
    try {
      const { data } = await api.get('/admin/cast-search', { params: { q: castQuery.value } })
      castResults.value = data.data || []
    } catch { castResults.value = [] }
  }, 350)
}
function selectCastPerson(person) {
  pendingCastPerson.value = person
  castResults.value       = []
  castQuery.value         = ''
}
async function lookupCastById() {
  if (!castIdInput.value) return
  try {
    const { data } = await api.get('/admin/cast-search', { params: { q: String(castIdInput.value) } })
    const person = (data.data || []).find(p => p.idPerson === Number(castIdInput.value))
    if (person) selectCastPerson(person)
    else showToast('No se encontró ninguna persona con ese ID', 'err')
  } catch { showToast('Error al buscar persona', 'err') }
}
async function createCastPerson() {
  if (!newPersonName.value.trim()) return
  isCreatingPerson.value = true
  try {
    const { data } = await api.post('/admin/cast-crew/store', {
      name:    newPersonName.value.trim(),
      photo:   newPersonPhoto.value.trim()  || null,
      tmdb_id: newPersonTmdbId.value        ? parseInt(newPersonTmdbId.value) : null,
    })
    selectCastPerson({ idPerson: data.data.idPerson, name: data.data.name, photo: data.data.photo })
    newPersonName.value = ''; newPersonPhoto.value = ''; newPersonTmdbId.value = ''
    showToast('Persona creada correctamente')
  } catch (err) {
    const msg = err?.response?.data?.errors?.tmdb_id?.[0]
      || err?.response?.data?.message || 'Error al crear persona'
    showToast(msg, 'err')
  } finally { isCreatingPerson.value = false }
}
function addCastMember() {
  if (!pendingCastPerson.value) return
  castList.value.push({
    idPerson:       pendingCastPerson.value.idPerson,
    name:           pendingCastPerson.value.name,
    photo:          pendingCastPerson.value.photo,
    role:           newCastRole.value || 'Actor',
    character_name: newCastCharacter.value,
    credit_order:   newCastOrder.value !== '' ? parseInt(newCastOrder.value) : castList.value.length,
  })
  cancelAddCast()
}
function cancelAddCast() {
  pendingCastPerson.value = null
  castQuery.value = ''; castIdInput.value = ''; castResults.value = []
  newCastRole.value = 'Actor'; newCastCharacter.value = ''; newCastOrder.value = ''
  newPersonName.value = ''; newPersonPhoto.value = ''; newPersonTmdbId.value = ''
  showCastForm.value = false
}
function removeCast(idx) { castList.value.splice(idx, 1) }

// ────── Premios / Nominaciones / Festivales ────────────────────────────────────
function addAward() {
  const v = newAward.value.trim(); if (!v) return
  form.value.awards.push(v); newAward.value = ''
}
function addNomination() {
  const v = newNomination.value.trim(); if (!v) return
  form.value.nominations.push(v); newNomination.value = ''
}
function addFestival() {
  const v = newFestival.value.trim(); if (!v) return
  form.value.festivals.push(v); newFestival.value = ''
}

// ────── Importación TMDB ───────────────────────────────────────────────────────
async function fetchFromTmdb() {
  if (!tmdbImportId.value) return
  isFetching.value = true; tmdbError.value = ''; tmdbPreview.value = null
  try {
    const { data } = await api.get('/admin/tmdb-fetch', { params: { tmdb_id: tmdbImportId.value } })
    if (data.success) tmdbPreview.value = data.data
    else tmdbError.value = data.message || 'Error desconocido'
  } catch (err) {
    tmdbError.value = err?.response?.data?.message || 'Error al conectar con TMDB'
  } finally { isFetching.value = false }
}
function applyTmdbData() {
  if (!tmdbPreview.value) return
  const f = tmdbPreview.value.film
  Object.assign(form.value, {
    tmdb_id:           f.tmdb_id          ?? form.value.tmdb_id,
    title:             f.title            || form.value.title,
    original_title:    f.original_title   || form.value.original_title,
    overview:          f.overview         || form.value.overview,
    duration:          f.duration         ?? form.value.duration,
    release_date:      f.release_date     || form.value.release_date,
    vote_average:      f.vote_average     ?? form.value.vote_average,
    genre:             f.genre            || form.value.genre,
    origin_country:    f.origin_country   || form.value.origin_country,
    original_language: f.original_language || form.value.original_language,
    frame:             f.frame            || form.value.frame,
    backdrop:          f.backdrop         || form.value.backdrop,
  })
  const dir = tmdbPreview.value.director
  if (dir?.idPerson) selectDirector({ idPerson: dir.idPerson, name: dir.name, photo: dir.photo })
  for (const m of tmdbPreview.value.cast || []) {
    if (!m.idPerson) continue
    if (castList.value.some(c => c.idPerson === m.idPerson)) continue
    castList.value.push({ idPerson: m.idPerson, name: m.name, photo: m.photo,
      role: 'Actor', character_name: m.character || '', credit_order: m.order ?? castList.value.length })
  }
  showToast('Datos de TMDB aplicados al formulario')
}

// ────── Enviar formulario ──────────────────────────────────────────────────────
async function submit() {
  isSaving.value = true; errors.value = {}
  const payload = {}
  for (const [k, v] of Object.entries(form.value)) {
    payload[k] = (v === '' || v === null || v === undefined) ? null : v
  }
  if (payload.duration)          payload.duration          = parseInt(payload.duration)
  if (payload.tmdb_id)           payload.tmdb_id           = parseInt(payload.tmdb_id)
  if (payload.wikidata_id)       payload.wikidata_id       = parseInt(payload.wikidata_id)
  if (payload.total_awards)      payload.total_awards      = parseInt(payload.total_awards)
  if (payload.total_nominations) payload.total_nominations = parseInt(payload.total_nominations)
  if (payload.total_festivals)   payload.total_festivals   = parseInt(payload.total_festivals)
  if (payload.vote_average)      payload.vote_average      = parseFloat(payload.vote_average)
  if (payload.globalRate)        payload.globalRate        = parseFloat(payload.globalRate)
  payload.cast = castList.value.map(m => ({
    idPerson:       m.idPerson,
    role:           m.role,
    character_name: m.character_name || null,
    credit_order:   m.credit_order !== '' ? parseInt(m.credit_order) : 0,
  }))
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
    } else { showToast(data?.message || 'Error inesperado', 'err') }
  } finally { isSaving.value = false }
}

function goBack() { router.push({ name: 'admin-dashboard' }) }

onMounted(() => {
  if (!auth.user || auth.user.idRol != 1) { router.replace({ name: 'home' }); return }
  if (isEdit.value) loadFilm()
})
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-24">

    <Transition name="fade">
      <div v-if="toast" :class="['fixed top-5 right-5 z-50 px-5 py-3 rounded-lg text-sm font-medium shadow-xl', toast.type === 'err' ? 'bg-red-600 text-white' : 'bg-emerald-600 text-white']">
        {{ toast.msg }}
      </div>
    </Transition>

    <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10">

      <div class="flex items-center gap-3 mb-8">
        <button @click="goBack" class="p-2 rounded-lg text-slate-500 hover:text-white hover:bg-white/5 transition">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-[0.25em] text-slate-500 block">Panel de administración</span>
          <h1 class="text-xl font-bold text-white">{{ isEdit ? 'Editar película' : 'Añadir película' }}</h1>
        </div>
      </div>

      <div v-if="isLoadingFilm" class="space-y-4 animate-pulse">
        <div v-for="i in 6" :key="i" class="h-10 bg-slate-800 rounded-lg"></div>
      </div>

      <template v-else>

        <!-- ── TMDB Import ────────────────────────────────────────── -->
        <section class="bg-[#0d1b2a] border border-blue-800/40 rounded-xl p-4 sm:p-6 mb-6 space-y-5">
          <h2 class="section-title text-blue-400 border-blue-800/30">
            <svg class="w-4 h-4 inline-block mr-1.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Importar automáticamente desde TMDB
          </h2>
          <p class="text-xs text-slate-400">Encuentra el ID en la URL de themoviedb.org/movie/<strong class="text-slate-300">157336</strong></p>
          <div class="flex gap-3">
            <input v-model="tmdbImportId" type="number" class="field-input max-w-[200px]" placeholder="Ej: 157336" @keydown.enter.prevent="fetchFromTmdb" />
            <button type="button" @click="fetchFromTmdb" :disabled="isFetching || !tmdbImportId"
              class="flex items-center gap-2 px-4 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
              <svg v-if="isFetching" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
              {{ isFetching ? 'Buscando…' : 'Importar datos' }}
            </button>
          </div>
          <p v-if="tmdbError" class="text-sm text-red-400">{{ tmdbError }}</p>

          <div v-if="tmdbPreview" class="bg-[#14181c] border border-blue-800/30 rounded-xl p-4 space-y-4">
            <div class="flex gap-4">
              <img v-if="tmdbPreview.film.frame" :src="tmdbPreview.film.frame" class="h-28 w-20 rounded-lg object-cover flex-shrink-0 border border-slate-700" />
              <div class="flex-1 min-w-0 space-y-1">
                <p class="font-bold text-white text-base leading-tight">{{ tmdbPreview.film.title }}</p>
                <p class="text-xs text-slate-400 italic">{{ tmdbPreview.film.original_title }}</p>
                <p class="text-xs text-slate-500">{{ tmdbPreview.film.release_date }} · {{ tmdbPreview.film.duration }} min · {{ tmdbPreview.film.original_language?.toUpperCase() }}</p>
                <p class="text-xs text-slate-400">{{ tmdbPreview.film.genre }} · {{ tmdbPreview.film.origin_country }}</p>
              </div>
            </div>
            <div v-if="tmdbPreview.director" class="flex items-center gap-2 text-xs">
              <span class="text-slate-500 uppercase tracking-widest text-[10px]">Director:</span>
              <span class="text-slate-200">{{ tmdbPreview.director.name }}</span>
              <span v-if="tmdbPreview.director.idPerson" class="px-1.5 py-0.5 bg-emerald-900/40 text-emerald-400 rounded text-[10px] font-bold">En BD · ID {{ tmdbPreview.director.idPerson }}</span>
              <span v-else class="px-1.5 py-0.5 bg-slate-800 text-slate-500 rounded text-[10px]">No está en la BD todavía</span>
            </div>
            <div v-if="tmdbPreview.cast.length" class="space-y-1.5">
              <p class="text-[10px] text-slate-500 uppercase tracking-widest">Reparto detectado</p>
              <div class="flex flex-wrap gap-1.5">
                <span v-for="m in tmdbPreview.cast" :key="m.tmdb_id"
                  :class="['px-2 py-1 rounded text-[11px]', m.idPerson ? 'bg-emerald-900/30 text-emerald-300' : 'bg-slate-800 text-slate-500']">
                  {{ m.name }}<span v-if="m.character"> · {{ m.character }}</span>
                </span>
              </div>
              <p class="text-[10px] text-slate-600">Verde = existe en la BD y se añadirá. Gris = no está en la BD.</p>
            </div>
            <button type="button" @click="applyTmdbData"
              class="px-5 py-2.5 rounded-lg bg-blue-600 text-white text-sm font-bold hover:bg-blue-500 transition-colors">
              Aplicar al formulario
            </button>
          </div>
        </section>

        <!-- ── Formulario manual ──────────────────────────────────── -->
        <form @submit.prevent="submit" class="space-y-6">

          <!-- Datos básicos -->
          <section class="section-card space-y-5">
            <h2 class="section-title">Datos básicos</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="field-label">Título <span class="text-red-400">*</span></label>
                <input v-model="form.title" type="text" class="field-input" :class="{ 'border-red-500/70': errors.title }" placeholder="Título en castellano o principal" />
                <p v-if="errors.title" class="field-error">{{ errors.title[0] }}</p>
              </div>
              <div>
                <label class="field-label">Título original <span class="text-red-400">*</span></label>
                <input v-model="form.original_title" type="text" class="field-input" :class="{ 'border-red-500/70': errors.original_title }" placeholder="Título en idioma original" />
                <p v-if="errors.original_title" class="field-error">{{ errors.original_title[0] }}</p>
              </div>
            </div>
            <div>
              <label class="field-label">Sinopsis</label>
              <textarea v-model="form.overview" rows="4" class="field-input resize-none" placeholder="Descripción de la película..."></textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
              <div>
                <label class="field-label">Fecha de estreno</label>
                <input v-model="form.release_date" type="date" class="field-input" :class="{ 'border-red-500/70': errors.release_date }" />
                <p v-if="errors.release_date" class="field-error">{{ errors.release_date[0] }}</p>
              </div>
              <div>
                <label class="field-label">Duración (min)</label>
                <input v-model="form.duration" type="number" min="1" max="65535" class="field-input" placeholder="Ej: 120" />
              </div>
              <div>
                <label class="field-label">Idioma original</label>
                <input v-model="form.original_language" type="text" class="field-input" placeholder="Ej: en, es, fr" />
              </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
              <div>
                <label class="field-label">Género(s)</label>
                <input v-model="form.genre" type="text" class="field-input" placeholder="Ej: Drama, Thriller" />
              </div>
              <div>
                <label class="field-label">País de origen</label>
                <input v-model="form.origin_country" type="text" class="field-input" placeholder="Ej: US, FR, ES" />
              </div>
            </div>
          </section>

          <!-- Director -->
          <section class="section-card space-y-4">
            <h2 class="section-title">Director / Directora</h2>

            <div class="flex gap-2">
              <button v-for="m in [['search','Buscar'],['id','Por ID'],['new','Crear nuevo']]" :key="m[0]"
                type="button" @click="directorMode = m[0]; clearDirector()"
                :class="['mode-tab', directorMode === m[0] ? 'mode-tab--active' : '']">{{ m[1] }}</button>
            </div>

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

            <!-- Buscar por nombre -->
            <div v-else-if="directorMode === 'search'" class="relative">
              <input v-model="directorQuery" @input="onDirectorInput" type="text" class="field-input" placeholder="Buscar por nombre… (mín. 2 caracteres)" />
              <ul v-if="directorResults.length" class="dropdown">
                <li v-for="p in directorResults" :key="p.idPerson" @click="selectDirector(p)" class="dropdown-item">
                  <img v-if="p.photo" :src="p.photo" class="w-8 h-8 rounded-full object-cover flex-shrink-0" />
                  <div v-else class="avatar-placeholder">{{ p.name?.charAt(0) }}</div>
                  <div><p class="text-sm text-slate-100">{{ p.name }}</p><p class="text-xs text-slate-500">ID {{ p.idPerson }}</p></div>
                </li>
              </ul>
            </div>

            <!-- Por ID -->
            <div v-else-if="directorMode === 'id'" class="flex gap-3">
              <input v-model="directorIdInput" type="number" class="field-input max-w-[180px]" placeholder="ID de persona (idPerson)" @keydown.enter.prevent="lookupDirectorById" />
              <button type="button" @click="lookupDirectorById" class="btn-secondary">Verificar</button>
            </div>

            <!-- Crear nueva persona -->
            <div v-else-if="directorMode === 'new'" class="bg-[#14181c] border border-slate-700 rounded-xl p-4 space-y-3">
              <p class="text-xs text-slate-400">Se creará una nueva entrada en la base de datos de personas.</p>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label class="field-label">Nombre <span class="text-red-400">*</span></label>
                  <input v-model="newDirName" type="text" class="field-input" placeholder="Nombre completo" />
                </div>
                <div>
                  <label class="field-label">URL de foto</label>
                  <input v-model="newDirPhoto" type="url" class="field-input" placeholder="https://…" />
                </div>
                <div>
                  <label class="field-label">TMDB Person ID <span class="text-slate-600">(opcional)</span></label>
                  <input v-model="newDirTmdbId" type="number" class="field-input" placeholder="Ej: 138" />
                </div>
              </div>
              <button type="button" @click="createDirector" :disabled="isCreatingDir || !newDirName.trim()"
                class="flex items-center gap-2 px-4 py-2 rounded-lg bg-[#00e054] text-[#14181c] text-sm font-bold hover:bg-[#00c94a] disabled:opacity-50 transition">
                <svg v-if="isCreatingDir" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                {{ isCreatingDir ? 'Creando…' : 'Crear y asignar' }}
              </button>
            </div>

            <p v-if="errors.director_id" class="field-error">{{ errors.director_id[0] }}</p>
          </section>

          <!-- Reparto -->
          <section class="section-card space-y-4">
            <h2 class="section-title">Reparto</h2>

            <div v-if="castList.length" class="space-y-2">
              <div v-for="(member, idx) in castList" :key="idx" class="flex items-center gap-3 p-3 bg-[#14181c] border border-slate-800 rounded-lg">
                <img v-if="member.photo" :src="member.photo" class="w-8 h-8 rounded-full object-cover flex-shrink-0" />
                <div v-else class="avatar-placeholder flex-shrink-0">{{ member.name?.charAt(0) }}</div>
                <div class="flex-1 min-w-0">
                  <p class="text-sm text-white">{{ member.name }} <span class="text-slate-500 text-xs">(ID: {{ member.idPerson }})</span></p>
                  <p class="text-xs text-slate-400">{{ member.role }}<span v-if="member.character_name"> · {{ member.character_name }}</span></p>
                </div>
                <input v-model="member.role" type="text" class="field-input w-28 text-xs py-1.5 flex-shrink-0" placeholder="Rol" />
                <button type="button" @click="removeCast(idx)" class="text-slate-600 hover:text-red-400 transition p-1 flex-shrink-0">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
              </div>
            </div>
            <p v-else class="text-xs text-slate-600">No hay miembros añadidos todavía.</p>

            <!-- Form añadir miembro -->
            <div v-if="showCastForm" class="bg-[#14181c] border border-slate-700 rounded-xl p-4 space-y-4">
              <div class="flex gap-2">
                <button v-for="m in [['search','Buscar'],['id','Por ID'],['new','Crear nuevo']]" :key="m[0]"
                  type="button" @click="castInputMode = m[0]; pendingCastPerson = null; castQuery = ''; castResults = []"
                  :class="['mode-tab', castInputMode === m[0] ? 'mode-tab--active' : '']">{{ m[1] }}</button>
                <button type="button" @click="cancelAddCast" class="ml-auto text-slate-500 hover:text-white text-xs transition">Cancelar</button>
              </div>

              <!-- Sin persona seleccionada: mostrar buscador/id/crear -->
              <template v-if="!pendingCastPerson">
                <!-- Buscar -->
                <div v-if="castInputMode === 'search'" class="relative">
                  <input v-model="castQuery" @input="onCastInput" type="text" class="field-input" placeholder="Buscar por nombre… (mín. 2 caracteres)" />
                  <ul v-if="castResults.length" class="dropdown">
                    <li v-for="p in castResults" :key="p.idPerson" @click="selectCastPerson(p)" class="dropdown-item">
                      <img v-if="p.photo" :src="p.photo" class="w-7 h-7 rounded-full object-cover flex-shrink-0" />
                      <div v-else class="avatar-placeholder w-7 h-7 text-[11px] flex-shrink-0">{{ p.name?.charAt(0) }}</div>
                      <div><p class="text-sm text-slate-100">{{ p.name }}</p><p class="text-xs text-slate-500">ID {{ p.idPerson }}</p></div>
                    </li>
                  </ul>
                </div>
                <!-- Por ID -->
                <div v-else-if="castInputMode === 'id'" class="flex gap-3">
                  <input v-model="castIdInput" type="number" class="field-input max-w-[180px]" placeholder="ID de persona" @keydown.enter.prevent="lookupCastById" />
                  <button type="button" @click="lookupCastById" class="btn-secondary">Verificar</button>
                </div>
                <!-- Crear nueva persona -->
                <div v-else class="space-y-3">
                  <p class="text-xs text-slate-400">Se creará una nueva entrada en la base de datos de personas.</p>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                      <label class="field-label">Nombre <span class="text-red-400">*</span></label>
                      <input v-model="newPersonName" type="text" class="field-input" placeholder="Nombre completo" />
                    </div>
                    <div>
                      <label class="field-label">URL de foto</label>
                      <input v-model="newPersonPhoto" type="url" class="field-input" placeholder="https://…" />
                    </div>
                    <div>
                      <label class="field-label">TMDB Person ID <span class="text-slate-600">(opcional)</span></label>
                      <input v-model="newPersonTmdbId" type="number" class="field-input" placeholder="Ej: 6193" />
                    </div>
                  </div>
                  <button type="button" @click="createCastPerson" :disabled="isCreatingPerson || !newPersonName.trim()"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg bg-slate-600 text-white text-sm font-bold hover:bg-slate-500 disabled:opacity-50 transition">
                    <svg v-if="isCreatingPerson" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
                    {{ isCreatingPerson ? 'Creando…' : 'Crear persona' }}
                  </button>
                </div>
              </template>

              <!-- Persona seleccionada: rellenar rol/personaje -->
              <template v-else>
                <div class="flex items-center gap-3 p-3 bg-white/5 rounded-lg">
                  <img v-if="pendingCastPerson.photo" :src="pendingCastPerson.photo" class="w-8 h-8 rounded-full object-cover" />
                  <div v-else class="avatar-placeholder">{{ pendingCastPerson.name?.charAt(0) }}</div>
                  <div>
                    <p class="text-sm text-white font-medium">{{ pendingCastPerson.name }}</p>
                    <p class="text-xs text-slate-500">ID: {{ pendingCastPerson.idPerson }}</p>
                  </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div>
                    <label class="field-label">Rol <span class="text-red-400">*</span></label>
                    <input v-model="newCastRole" type="text" class="field-input" placeholder="Ej: Actor, Actriz, Guionista…" />
                  </div>
                  <div>
                    <label class="field-label">Nombre del personaje</label>
                    <input v-model="newCastCharacter" type="text" class="field-input" placeholder="Ej: John Doe" />
                  </div>
                  <div>
                    <label class="field-label">Orden de crédito</label>
                    <input v-model="newCastOrder" type="number" min="0" class="field-input" placeholder="0 = primero" />
                  </div>
                </div>
                <div class="flex gap-3">
                  <button type="button" @click="addCastMember" class="px-4 py-2 rounded-lg bg-[#00e054] text-[#14181c] text-sm font-bold hover:bg-[#00c94a] transition">Añadir al reparto</button>
                  <button type="button" @click="pendingCastPerson = null" class="px-4 py-2 rounded-lg text-slate-400 hover:text-white text-sm transition">← Atrás</button>
                </div>
              </template>
            </div>

            <button v-if="!showCastForm" type="button" @click="showCastForm = true; castInputMode = 'search'"
              class="flex items-center gap-2 px-4 py-2 rounded-lg border border-slate-700 text-slate-400 text-sm hover:border-slate-500 hover:text-white transition">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
              Añadir miembro al reparto
            </button>
          </section>

          <!-- Imágenes -->
          <section class="section-card space-y-5">
            <h2 class="section-title">Imágenes</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
              <div>
                <label class="field-label">URL del póster (frame)</label>
                <input v-model="form.frame" type="url" maxlength="225" class="field-input" :class="{ 'border-red-500/70': errors.frame }" placeholder="https://…" />
                <p v-if="errors.frame" class="field-error">{{ errors.frame[0] }}</p>
                <img v-if="form.frame" :src="form.frame" alt="Póster" class="mt-2 h-32 rounded-lg object-cover border border-slate-700" @error="e => e.target.style.display='none'" />
              </div>
              <div>
                <label class="field-label">URL del backdrop</label>
                <input v-model="form.backdrop" type="url" maxlength="255" class="field-input" :class="{ 'border-red-500/70': errors.backdrop }" placeholder="https://…" />
                <p v-if="errors.backdrop" class="field-error">{{ errors.backdrop[0] }}</p>
                <img v-if="form.backdrop" :src="form.backdrop" alt="Backdrop" class="mt-2 h-32 w-full rounded-lg object-cover border border-slate-700" @error="e => e.target.style.display='none'" />
              </div>
            </div>
          </section>

          <!-- Puntuaciones -->
          <section class="section-card space-y-5">
            <h2 class="section-title">Puntuaciones</h2>
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

          <!-- IDs externos -->
          <section class="section-card space-y-5">
            <h2 class="section-title">IDs externos</h2>
            <p class="text-xs text-slate-500">Opcionales. Evitan duplicados y habilitan funciones como streaming o datos enriquecidos.</p>
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

          <!-- Premios, nominaciones y festivales -->
          <section class="section-card space-y-6">
            <h2 class="section-title">Premios, nominaciones y festivales</h2>

            <!-- Contadores -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
              <div>
                <label class="field-label">Total premios ganados</label>
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

            <div class="border-t border-slate-800 pt-5 grid grid-cols-1 md:grid-cols-3 gap-6">

              <!-- Premios ganados -->
              <div class="space-y-3">
                <p class="text-xs font-bold uppercase tracking-widest text-yellow-500">Premios ganados</p>
                <div v-if="form.awards.length" class="space-y-1.5">
                  <div v-for="(item, i) in form.awards" :key="i" class="flex items-start gap-2 text-xs text-slate-300 bg-[#14181c] border border-slate-800 rounded-lg px-3 py-2">
                    <span class="flex-1">{{ item }}</span>
                    <button type="button" @click="form.awards.splice(i, 1)" class="text-slate-600 hover:text-red-400 transition flex-shrink-0 mt-0.5">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </div>
                <p v-else class="text-xs text-slate-700">Sin premios añadidos.</p>
                <div class="flex gap-2">
                  <input v-model="newAward" type="text" class="field-input text-xs py-2" placeholder="Ej: Oscar - Mejor película 2020" @keydown.enter.prevent="addAward" />
                  <button type="button" @click="addAward" class="px-3 py-2 rounded-lg bg-yellow-600/20 text-yellow-400 text-xs font-bold hover:bg-yellow-600/30 transition flex-shrink-0">+</button>
                </div>
              </div>

              <!-- Nominaciones -->
              <div class="space-y-3">
                <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Nominaciones</p>
                <div v-if="form.nominations.length" class="space-y-1.5">
                  <div v-for="(item, i) in form.nominations" :key="i" class="flex items-start gap-2 text-xs text-slate-300 bg-[#14181c] border border-slate-800 rounded-lg px-3 py-2">
                    <span class="flex-1">{{ item }}</span>
                    <button type="button" @click="form.nominations.splice(i, 1)" class="text-slate-600 hover:text-red-400 transition flex-shrink-0 mt-0.5">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </div>
                <p v-else class="text-xs text-slate-700">Sin nominaciones añadidas.</p>
                <div class="flex gap-2">
                  <input v-model="newNomination" type="text" class="field-input text-xs py-2" placeholder="Ej: BAFTA - Mejor guión 2020" @keydown.enter.prevent="addNomination" />
                  <button type="button" @click="addNomination" class="px-3 py-2 rounded-lg bg-slate-700 text-slate-300 text-xs font-bold hover:bg-slate-600 transition flex-shrink-0">+</button>
                </div>
              </div>

              <!-- Festivales -->
              <div class="space-y-3">
                <p class="text-xs font-bold uppercase tracking-widest text-orange-400">Festivales</p>
                <div v-if="form.festivals.length" class="space-y-1.5">
                  <div v-for="(item, i) in form.festivals" :key="i" class="flex items-start gap-2 text-xs text-slate-300 bg-[#14181c] border border-slate-800 rounded-lg px-3 py-2">
                    <span class="flex-1">{{ item }}</span>
                    <button type="button" @click="form.festivals.splice(i, 1)" class="text-slate-600 hover:text-red-400 transition flex-shrink-0 mt-0.5">
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                  </div>
                </div>
                <p v-else class="text-xs text-slate-700">Sin festivales añadidos.</p>
                <div class="flex gap-2">
                  <input v-model="newFestival" type="text" class="field-input text-xs py-2" placeholder="Ej: Cannes - Palme d'Or 2019" @keydown.enter.prevent="addFestival" />
                  <button type="button" @click="addFestival" class="px-3 py-2 rounded-lg bg-orange-600/20 text-orange-400 text-xs font-bold hover:bg-orange-600/30 transition flex-shrink-0">+</button>
                </div>
              </div>

            </div>
          </section>

          <!-- Botones -->
          <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3 pt-2">
            <button type="button" @click="goBack" class="w-full sm:w-auto px-5 py-2.5 rounded-lg text-sm text-slate-400 hover:text-white transition text-center">Cancelar</button>
            <button type="submit" :disabled="isSaving"
              class="w-full sm:w-auto flex items-center justify-center gap-2 px-6 py-2.5 rounded-lg bg-[#00e054] text-[#14181c] text-sm font-bold hover:bg-[#00c94a] disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
              <svg v-if="isSaving" class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/></svg>
              {{ isSaving ? 'Guardando…' : (isEdit ? 'Guardar cambios' : 'Crear película') }}
            </button>
          </div>

        </form>
      </template>
    </div>
  </div>
</template>

<style scoped>
@reference "@/assets/main.css";

.content-wrap { width: 100%; margin-left: auto; margin-right: auto; }

.section-card {
  @apply bg-[#1c2128] border border-slate-800 rounded-xl p-4 sm:p-6;
}
.section-title {
  @apply text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400 border-b border-slate-800 pb-3;
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
.mode-tab {
  @apply px-3 py-1.5 rounded-lg text-xs font-bold uppercase tracking-widest transition text-slate-500 hover:text-slate-300;
}
.mode-tab--active {
  @apply bg-white/10 text-white;
}
.btn-secondary {
  @apply px-4 py-2 rounded-lg bg-slate-700 text-white text-sm hover:bg-slate-600 transition;
}
.dropdown {
  @apply absolute z-20 w-full mt-1 bg-[#1c2128] border border-slate-700 rounded-lg overflow-hidden shadow-xl;
}
.dropdown-item {
  @apply flex items-center gap-3 px-3 py-2.5 hover:bg-white/5 cursor-pointer transition;
}
.avatar-placeholder {
  @apply w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-slate-400 text-xs font-bold;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
