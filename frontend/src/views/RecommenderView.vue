<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import HomeBackdropModal from '@/components/HomeBackdropModal.vue'
import WatchProviders from '@/components/WatchProviders.vue'

const auth  = useAuthStore()
const isAdmin = computed(() => {
  if (!auth.isAuthenticated || !auth.user) return false
  return parseInt(auth.user.idRol) === 1 || String(auth.user.role || '').toLowerCase() === 'admin'
})

// Fondo de pantalla (poster/backdrop)
const heroFilmId   = ref(localStorage.getItem('recommender_hero_id') || 5190)
const heroFilmData = ref(null)
const isBackdropModalOpen = ref(false)

const handleBackdropChange = async (film) => {
  if (!film) return
  try {
    heroFilmId.value = film.idFilm
    localStorage.setItem('recommender_hero_id', film.idFilm)
    const res = await api.get(`/films/${film.idFilm}`)
    heroFilmData.value = res.data.data || res.data
  } catch (e) {
    console.error('Error cambiando backdrop', e)
  }
}

onMounted(async () => {
  try {
    const res = await api.get(`/films/${heroFilmId.value}`)
    heroFilmData.value = res.data.data || res.data
  } catch (e) { /* sin backdrop */ }
})

const router = useRouter()

// --- ESTADO DEL WIZARD ---
const step        = ref(0)   // 0 = bienvenida, 1-4 = preguntas, 5 = cargando, 6 = resultados
const selections  = ref({ genre: null, duration: null, era: null, country: null })
const results     = ref([])
const aiPowered   = ref(false)
const error       = ref(null)
const cachedFilms = ref([])   // películas filtradas guardadas para refinar sin re-filtrar
const refinement  = ref('')   // texto libre de refinamiento
const reranking   = ref(false)

// Estado por card: traducción de la sinopsis
const cardState = ref({}) // { [filmId]: { translating, showTranslated, error } }

const getCard = (id) => {
  if (!cardState.value[id]) {
    cardState.value[id] = { translating: false, showTranslated: false, error: false }
  }
  return cardState.value[id]
}

const toggleTranslation = async (film) => {
  const c = getCard(film.id)
  // Si ya tiene overview_es, solo alternar
  if (film.overview_es) { c.showTranslated = !c.showTranslated; return }
  if (c.showTranslated) { c.showTranslated = false; return }
  // Llamar al endpoint de Azure (guarda en BD y devuelve traducción)
  c.translating = true
  c.error = false
  try {
    const { data } = await api.post(`/films/${film.id}/translate-overview`)
    film.overview_es = data.overview_es
    c.showTranslated = true
  } catch {
    c.error = true
  } finally {
    c.translating = false
  }
}

// --- OPCIONES DE CADA PASO ---
// iconPaths: array de paths SVG (heroicons outline 24px)
const genres = [
  {
    label: 'Acción', value: 'Action',
    iconPaths: ['M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'],
  },
  {
    label: 'Drama', value: 'Drama',
    iconPaths: ['M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 01.865-.501 48.172 48.172 0 003.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z'],
  },
  {
    label: 'Comedia', value: 'Comedy',
    iconPaths: ['M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z'],
  },
  {
    label: 'Terror', value: 'Horror',
    iconPaths: ['M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z'],
  },
  {
    label: 'Ciencia Ficción', value: 'Science Fiction',
    iconPaths: ['M6.75 7.5l3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z'],
  },
  {
    label: 'Thriller', value: 'Thriller',
    iconPaths: ['M21 21l-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z'],
  },
  {
    label: 'Romance', value: 'Romance',
    iconPaths: ['M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z'],
  },
  {
    label: 'Animación', value: 'Animation',
    iconPaths: ['M9.53 16.122a3 3 0 0 0-5.78 1.128 2.25 2.25 0 0 1-2.4 2.245 4.5 4.5 0 0 0 8.4-2.245c0-.399-.078-.78-.22-1.128Zm0 0a15.998 15.998 0 0 0 3.388-1.62m-5.043-.025a15.994 15.994 0 0 1 1.622-3.395m3.42 3.42a15.995 15.995 0 0 0 4.764-4.648l3.876-5.814a1.151 1.151 0 0 0-1.597-1.597L14.146 6.32a15.996 15.996 0 0 0-4.649 4.763m3.42 3.42a6.776 6.776 0 0 0-3.42-3.42'],
  },
  {
    label: 'Documental', value: 'Documentary',
    iconPaths: ['M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0M3.75 9h16.5m-16.5 6h16.5'],
  },
  {
    label: 'Fantasía', value: 'Fantasy',
    iconPaths: ['M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.563.563 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.563.563 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z'],
  },
]

const durations = [
  {
    label: 'Corta',  sublabel: 'Menos de 90 min', value: 'corta',
    iconPaths: ['M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z'],
  },
  {
    label: 'Media',  sublabel: '90 – 120 min', value: 'media',
    iconPaths: ['M7.5 3.75H6A2.25 2.25 0 0 0 3.75 6v1.5M16.5 3.75H18A2.25 2.25 0 0 1 20.25 6v1.5m0 9V18A2.25 2.25 0 0 1 18 20.25h-1.5m-9 0H6A2.25 2.25 0 0 1 3.75 18v-1.5M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0M3.75 9h16.5m-16.5 6h16.5'],
  },
  {
    label: 'Larga',  sublabel: 'Más de 2 horas', value: 'larga',
    iconPaths: ['M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
  },
]

const eras = [
  { label: 'Clásicos', sublabel: 'Antes de 1980', value: 'clasicos' },
  { label: '80s',      sublabel: '1980 – 1989',   value: '1980' },
  { label: '90s',      sublabel: '1990 – 1999',   value: '1990' },
  { label: '2000s',    sublabel: '2000 – 2009',   value: '2000' },
  { label: '2010s',    sublabel: '2010 – 2019',   value: '2010' },
  { label: 'Recientes',sublabel: 'Desde 2020',    value: 'recientes' },
]

const countries = [
  { label: 'Estados Unidos', value: 'United States of America' },
  { label: 'España',         value: 'Spain' },
  { label: 'Francia',        value: 'France' },
  { label: 'Japón',          value: 'Japan' },
  { label: 'Reino Unido',    value: 'United Kingdom' },
  { label: 'Italia',         value: 'Italy' },
  { label: 'Corea del Sur',  value: 'South Korea' },
  { label: 'Alemania',       value: 'Germany' },
  { label: 'México',         value: 'Mexico' },
  { label: 'Argentina',      value: 'Argentina' },
]

// --- PASOS DEL WIZARD ---
const steps = [
  { key: 'genre',    question: '¿Qué te pide el cuerpo?',         sub: 'El género marca el tono de toda la noche' },
  { key: 'duration', question: '¿Cuánto tiempo tienes?',           sub: 'No todas las noches son de maratón' },
  { key: 'era',      question: '¿Nostalgia o estreno?',            sub: 'Cada época tiene su propia magia' },
  { key: 'country',  question: '¿De dónde viene tu cine favorito?', sub: 'Cada cinematografía tiene su identidad' },
]

const currentStep   = computed(() => steps[step.value - 1])
const totalSteps    = steps.length
const progress      = computed(() => ((step.value - 1) / totalSteps) * 100)

// --- PREFERENCIAS LEGIBLES (para el prompt de IA) ---
const preferencesText = computed(() => {
  const parts = []
  if (selections.value.genre)    parts.push(`género ${selections.value.genre}`)
  if (selections.value.duration === 'corta')  parts.push('duración corta (menos de 90 min)')
  if (selections.value.duration === 'media')  parts.push('duración media (90-120 min)')
  if (selections.value.duration === 'larga')  parts.push('duración larga (más de 2 horas)')
  if (selections.value.era === 'clasicos')     parts.push('época clásica (antes de 1980)')
  if (selections.value.era === 'recientes')    parts.push('películas recientes (desde 2020)')
  if (selections.value.era && !['clasicos','recientes'].includes(selections.value.era))
    parts.push(`cine de los ${selections.value.era}s`)
  if (selections.value.country)  parts.push(`cine de ${selections.value.country}`)
  return parts.length ? parts.join(', ') : 'cualquier tipo de película'
})

// --- NAVEGACIÓN ---
const select = (key, value) => {
  selections.value[key] = value
}

const isSelected = (key, value) => selections.value[key] === value

const next = () => {
  if (step.value < totalSteps) {
    step.value++
  } else {
    fetchRecommendations()
  }
}

const skip = () => {
  selections.value[currentStep.value.key] = null
  next()
}

const back = () => {
  if (step.value > 1) step.value--
  else step.value = 0
}

const restart = () => {
  step.value = 0
  selections.value = { genre: null, duration: null, era: null, country: null }
  results.value = []
  cachedFilms.value = []
  refinement.value = ''
  error.value = null
}

// --- LLAMADAS API ---
const fetchRecommendations = async () => {
  step.value = 5
  error.value = null

  try {
    const { data: filterRes } = await api.post('/recommender/filter', {
      genre:    selections.value.genre,
      duration: selections.value.duration,
      era:      selections.value.era,
      country:  selections.value.country,
    })

    const filtered = filterRes.data ?? []

    if (filtered.length === 0) {
      error.value = 'No encontramos películas con esos filtros. Prueba a cambiar alguna respuesta.'
      step.value = 6
      return
    }

    cachedFilms.value = filtered
    // Top 5 por valoración — la sinopsis del film es el resumen por defecto, sin llamar a la IA
    results.value = filtered.slice(0, 5).map(film => ({
      film,
      explanation: film.overview || '',
    }))
    aiPowered.value = false
    cardState.value = {}
    step.value = 6

  } catch (e) {
    console.error(e)
    error.value = 'Algo salió mal. Inténtalo de nuevo.'
    step.value = 6
  }
}

const refineResults = async () => {
  if (!refinement.value.trim() || reranking.value) return
  reranking.value = true

  const combinedPreferences = `${preferencesText.value}. Búsqueda específica del usuario: ${refinement.value.trim()}`

  try {
    const { data: rankRes } = await api.post('/recommender/rank', {
      films:       cachedFilms.value,
      preferences: combinedPreferences,
    })
    results.value   = rankRes.data ?? []
    aiPowered.value = rankRes.ai_powered ?? false
    cardState.value = {} // reset estado de traducción al cambiar resultados
  } catch (e) {
    console.error(e)
  } finally {
    reranking.value = false
  }
}
</script>

<template>
  <div class="relative min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20">

    <!-- Backdrop de fondo (siempre visible, sutil) -->
    <div v-if="heroFilmData?.backdrop" class="fixed inset-0 z-0 pointer-events-none">
      <img :src="heroFilmData.backdrop" class="w-full h-full object-cover opacity-[0.06]" />
      <div class="absolute inset-0 bg-gradient-to-t from-[#14181c] via-[#14181c]/80 to-[#14181c]/60"></div>
    </div>

    <!-- Botón admin cambiar fondo -->
    <div v-if="isAdmin" class="absolute top-6 right-6 z-50">
      <button
        @click="isBackdropModalOpen = true"
        class="flex items-center gap-2 bg-brand/20 hover:bg-brand text-white border border-brand/50 px-3 py-1.5 rounded text-[9px] font-black uppercase tracking-widest transition-all"
      >
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3 h-3">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125" />
        </svg>
        Cambiar fondo
      </button>
    </div>

    <HomeBackdropModal v-model="isBackdropModalOpen" @change-backdrop="handleBackdropChange" />

    <div class="relative z-10 content-wrap mx-auto max-w-[720px] px-4 sm:px-6 pt-14 sm:pt-20 pb-12">

      <!-- ── BIENVENIDA ── -->
      <div v-if="step === 0" class="flex flex-col items-center justify-center text-center min-h-[calc(100vh-10rem)] gap-10 animate-fade-in">

        <!-- Icono central con halo -->
        <div class="relative flex items-center justify-center w-28 h-28">
          <div class="absolute w-28 h-28 rounded-full bg-orange-500/20 blur-2xl"></div>
          <div class="relative w-24 h-24 rounded-full bg-orange-500/10 border-2 border-orange-500/30 flex items-center justify-center shadow-[0_0_20px_rgba(249,115,22,0.1)]">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-orange-500">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" />
            </svg>
          </div>
        </div>

        <!-- Texto principal -->
        <div class="flex flex-col items-center gap-3">
          <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black uppercase italic tracking-tighter text-white leading-[0.9]">
            No sé qué ver
          </h1>
          <p class="text-slate-400 text-sm md:text-base font-light max-w-xs text-center leading-relaxed mt-2">
            Responde 4 preguntas y te recomendamos las películas perfectas para ti.
          </p>
        </div>

        <!-- Chips de previsualización -->
        <div class="flex flex-wrap justify-center gap-2 max-w-xs">
          <span class="px-3 py-1 rounded-full bg-slate-900/80 border border-slate-800 text-[9px] font-black uppercase tracking-widest text-slate-500">Género</span>
          <span class="text-slate-700 self-center">›</span>
          <span class="px-3 py-1 rounded-full bg-slate-900/80 border border-slate-800 text-[9px] font-black uppercase tracking-widest text-slate-500">Duración</span>
          <span class="text-slate-700 self-center">›</span>
          <span class="px-3 py-1 rounded-full bg-slate-900/80 border border-slate-800 text-[9px] font-black uppercase tracking-widest text-slate-500">Época</span>
          <span class="text-slate-700 self-center">›</span>
          <span class="px-3 py-1 rounded-full bg-slate-900/80 border border-slate-800 text-[9px] font-black uppercase tracking-widest text-slate-500">País</span>
        </div>

        <!-- CTA principal -->
        <button
          @click="step = 1"
          class="bg-brand hover:bg-[#d4310e] text-white px-12 py-4 rounded-lg font-black uppercase tracking-widest text-base transition-all hover:scale-105 shadow-[0_4px_24px_rgba(190,43,12,0.45)] hover:shadow-[0_6px_32px_rgba(190,43,12,0.6)]"
        >
          Empezar
        </button>

        <button @click="router.back()" class="text-[10px] text-slate-600 uppercase tracking-widest hover:text-slate-400 transition-colors">
          Volver
        </button>
      </div>

      <!-- ── PREGUNTAS (steps 1–4) ── -->
      <div v-else-if="step >= 1 && step <= 4" class="animate-fade-in">

        <!-- Cabecera + progreso -->
        <div class="mb-8 sm:mb-12">
          <div class="flex items-center justify-between mb-6 sm:mb-8">
            <button @click="back" class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-white transition-colors">
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
              </svg>
              Atrás
            </button>
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-600">{{ step }} / {{ totalSteps }}</span>
          </div>

          <!-- Barra de progreso -->
          <div class="w-full h-1 bg-slate-800 rounded-full overflow-hidden mb-10">
            <div class="h-full bg-brand rounded-full transition-all duration-500" :style="{ width: `${progress}%` }"></div>
          </div>

          <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500 mb-3">Pregunta {{ step }}</p>
          <h2 class="text-3xl md:text-4xl font-black uppercase italic tracking-tighter text-white mb-3">
            {{ currentStep.question }}
          </h2>
          <p class="text-sm text-slate-400 font-light">{{ currentStep.sub }}</p>
        </div>

        <!-- Paso 1: Géneros -->
        <div v-if="step === 1" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3">
          <button
            v-for="g in genres" :key="g.value"
            @click="select('genre', g.value)"
            :class="['flex flex-col items-center justify-center gap-3 py-6 px-3 rounded-xl border transition-all group',
              isSelected('genre', g.value)
                ? 'border-brand bg-brand/10 text-brand'
                : 'border-slate-800 hover:border-slate-600 text-slate-500 hover:text-white bg-slate-900/30']"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 flex-shrink-0">
              <path v-for="(p, i) in g.iconPaths" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="p" />
            </svg>
            <span class="text-[9px] font-black uppercase tracking-widest text-center leading-tight">{{ g.label }}</span>
          </button>
        </div>

        <!-- Paso 2: Duración -->
        <div v-else-if="step === 2" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <button
            v-for="d in durations" :key="d.value"
            @click="select('duration', d.value)"
            :class="['flex flex-col items-center gap-4 py-8 px-6 rounded-xl border transition-all',
              isSelected('duration', d.value)
                ? 'border-brand bg-brand/10 text-brand'
                : 'border-slate-800 hover:border-slate-600 text-slate-500 hover:text-white bg-slate-900/30']"
          >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-9 h-9">
              <path v-for="(p, i) in d.iconPaths" :key="i" stroke-linecap="round" stroke-linejoin="round" :d="p" />
            </svg>
            <div class="text-center">
              <p class="text-sm font-black uppercase tracking-widest">{{ d.label }}</p>
              <p class="text-[10px] text-slate-500 mt-1 font-light">{{ d.sublabel }}</p>
            </div>
          </button>
        </div>

        <!-- Paso 3: Épocas -->
        <div v-else-if="step === 3" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <button
            v-for="e in eras" :key="e.value"
            @click="select('era', e.value)"
            :class="['flex flex-col items-center justify-center gap-3 py-6 px-3 rounded-xl border transition-all',
              isSelected('era', e.value)
                ? 'border-brand bg-brand/10 text-brand'
                : 'border-slate-800 hover:border-slate-600 text-slate-500 hover:text-white bg-slate-900/30']"
          >
            <!-- Icono calendario (heroicon outline) -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-7 h-7 flex-shrink-0">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
            <div class="text-center">
              <p class="text-sm font-black uppercase tracking-wider">{{ e.label }}</p>
              <p class="text-[9px] text-slate-500 mt-0.5 font-light">{{ e.sublabel }}</p>
            </div>
          </button>
        </div>

        <!-- Paso 4: Países -->
        <div v-else-if="step === 4" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
          <button
            v-for="c in countries" :key="c.value"
            @click="select('country', c.value)"
            :class="['flex flex-col items-center justify-center gap-3 py-5 px-3 rounded-xl border transition-all',
              isSelected('country', c.value)
                ? 'border-brand bg-brand/10 text-brand'
                : 'border-slate-800 hover:border-slate-600 text-slate-500 hover:text-white bg-slate-900/30']"
          >
            <!-- Icono map-pin (heroicon outline) -->
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 flex-shrink-0">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
            </svg>
            <span class="text-[10px] font-black uppercase tracking-widest text-center leading-tight">{{ c.label }}</span>
          </button>
        </div>

        <!-- Navegación inferior -->
        <div class="flex items-center justify-between mt-8 pt-6 border-t border-slate-800">
          <button
            @click="skip"
            class="text-[10px] font-black uppercase tracking-widest text-slate-600 hover:text-slate-400 transition-colors"
          >
            Omitir
          </button>
          <button
            @click="next"
            :disabled="!selections[currentStep.key]"
            :class="['px-8 py-3 rounded font-black uppercase tracking-widest text-sm transition-all',
              selections[currentStep.key]
                ? 'bg-brand hover:bg-brand-dark text-white hover:scale-105'
                : 'bg-slate-800 text-slate-600 cursor-not-allowed']"
          >
            {{ step === totalSteps ? 'Ver recomendaciones' : 'Siguiente' }}
          </button>
        </div>
      </div>

      <!-- ── CARGANDO ── -->
      <div v-else-if="step === 5" class="flex flex-col items-center justify-center py-28 gap-6 animate-fade-in">
        <div class="relative w-16 h-16">
          <div class="absolute inset-0 border-2 border-slate-800 rounded-full"></div>
          <div class="absolute inset-0 border-2 border-t-brand rounded-full animate-spin"></div>
        </div>
        <div class="text-center">
          <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500 mb-2">Analizando tu perfil</p>
          <p class="text-white font-black uppercase italic text-xl">Buscando películas…</p>
        </div>
        <p class="text-[10px] text-slate-600 uppercase tracking-widest text-center max-w-xs">
          Filtrando el catálogo según tus preferencias
        </p>
      </div>

      <!-- ── RESULTADOS ── -->
      <div v-else-if="step === 6" class="animate-fade-in">

        <!-- Error -->
        <div v-if="error" class="py-20 text-center">
          <p class="text-slate-500 text-sm mb-6">{{ error }}</p>
          <button @click="restart" class="bg-brand text-white px-8 py-3 rounded font-black uppercase tracking-widest hover:scale-105 transition-all">
            Intentar de nuevo
          </button>
        </div>

        <template v-else>
          <!-- Cabecera resultados -->
          <div class="mb-8">
            <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500 mb-2">
              Tu selección para esta noche
            </p>
            <h2 class="text-3xl font-black uppercase italic tracking-tighter text-white mb-1">
              Tus 5 recomendaciones
            </h2>
            <div class="flex items-center gap-2 mt-3">
              <span v-if="aiPowered" class="flex items-center gap-1.5 bg-brand/10 border border-brand/30 text-brand px-2.5 py-1 rounded text-[9px] font-black uppercase tracking-widest">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-3 h-3">
                  <path d="M15.98 1.804a1 1 0 0 0-1.96 0l-.24 1.192a1 1 0 0 1-.784.785l-1.192.238a1 1 0 0 0 0 1.962l1.192.238a1 1 0 0 1 .785.785l.238 1.192a1 1 0 0 0 1.962 0l.238-1.192a1 1 0 0 1 .785-.785l1.192-.238a1 1 0 0 0 0-1.962l-1.192-.238a1 1 0 0 1-.785-.785l-.238-1.192ZM6.949 5.684a1 1 0 0 0-1.898 0l-.683 2.051a1 1 0 0 1-.633.633l-2.051.683a1 1 0 0 0 0 1.898l2.051.684a1 1 0 0 1 .633.632l.683 2.051a1 1 0 0 0 1.898 0l.683-2.051a1 1 0 0 1 .633-.633l2.051-.683a1 1 0 0 0 0-1.897l-2.051-.683a1 1 0 0 1-.633-.633L6.95 5.684Z" />
                </svg>
                Ranking IA
              </span>
              <span v-else class="text-[9px] font-bold text-slate-500 uppercase tracking-widest border border-slate-800 px-2.5 py-1 rounded">
                Por valoración comunidad
              </span>
              <span class="text-[9px] text-slate-600 uppercase tracking-widest">· {{ results.length }} películas</span>
            </div>
          </div>

          <!-- Cards de resultados -->
          <div class="flex flex-col gap-3">
            <div
              v-for="(item, idx) in results" :key="item.film.id"
              class="rounded-xl border border-slate-800 hover:border-brand/40 hover:bg-slate-900/40 transition-all group"
            >
              <!-- Fila principal: clickable -->
              <div
                class="flex gap-3 sm:gap-4 p-3 sm:p-4 cursor-pointer"
                @click="router.push(`/films/${item.film.id}`)"
              >
                <!-- Número -->
                <div class="flex-shrink-0 w-5 sm:w-7 flex items-start pt-1 justify-center">
                  <span class="text-base sm:text-lg font-black text-slate-500 group-hover:text-brand transition-colors leading-none">{{ idx + 1 }}</span>
                </div>

                <!-- Poster -->
                <div class="flex-shrink-0 w-12 h-[68px] sm:w-14 sm:h-20 rounded-lg overflow-hidden bg-slate-800 border border-slate-700/60">
                  <img v-if="item.film.frame" :src="item.film.frame" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" />
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0 flex flex-col justify-center gap-1 sm:gap-1.5">
                  <!-- Meta: género · año · puntuación -->
                  <div class="flex items-center flex-wrap gap-x-2 gap-y-1">
                    <span class="text-[11px] sm:text-xs font-black uppercase tracking-wide text-brand leading-none">{{ item.film.genre?.split(',')[0] }}</span>
                    <span v-if="item.film.year" class="text-[11px] sm:text-xs text-slate-300 font-semibold leading-none">{{ item.film.year }}</span>
                    <span v-if="item.film.globalRate > 0" class="text-[11px] sm:text-xs font-bold text-yellow-400 leading-none">★ {{ item.film.globalRate }}</span>
                  </div>
                  <!-- Título -->
                  <h3 class="text-sm sm:text-base font-black text-white uppercase tracking-tight truncate group-hover:text-brand transition-colors">
                    {{ item.film.title }}
                  </h3>
                  <!-- Sinopsis / explicación IA -->
                  <p v-if="item.explanation" class="text-xs sm:text-[13px] text-slate-200 leading-relaxed line-clamp-3">
                    {{ getCard(item.film.id).showTranslated && item.film.overview_es
                        ? item.film.overview_es
                        : item.explanation }}
                  </p>
                  <!-- Providers badge -->
                  <WatchProviders :filmId="item.film.id" compact />
                </div>

                <!-- Arrow -->
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="flex-shrink-0 w-4 h-4 text-slate-400 group-hover:text-slate-200 transition-colors self-center">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                </svg>
              </div>

              <!-- Fila traducción (solo si hay overview, no en explicaciones IA) -->
              <div v-if="item.film.overview" class="flex items-center gap-2 px-3 sm:px-4 pb-3">
                <button
                  @click.stop="toggleTranslation(item.film)"
                  :disabled="getCard(item.film.id).translating"
                  class="flex items-center gap-1.5 text-xs font-semibold text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 px-2.5 py-1 rounded transition-all disabled:opacity-40"
                >
                  <svg v-if="getCard(item.film.id).translating" class="w-3 h-3 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                  </svg>
                  <svg v-else xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path d="M5 8l6 6"/><path d="m4 14 6-6 2-3"/><path d="M2 5h12"/><path d="M7 2h1"/><path d="m22 22-5-10-5 10"/><path d="M14 18h6"/>
                  </svg>
                  {{ getCard(item.film.id).translating ? 'Traduciendo…'
                     : getCard(item.film.id).showTranslated ? 'Ver original'
                     : 'Traducir sinopsis' }}
                </button>
                <span v-if="getCard(item.film.id).error" class="text-xs text-red-400">Error al traducir.</span>
              </div>
            </div>
          </div>

          <!-- ── Refinamiento ── -->
          <div class="mt-8 pt-8 border-t border-slate-800">
            <div class="flex items-start gap-3 mb-4">
              <div class="w-8 h-8 rounded-full bg-yellow-500/10 border border-yellow-500/30 flex items-center justify-center flex-shrink-0 mt-0.5">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 text-yellow-400">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                </svg>
              </div>
              <div>
                <p class="text-sm font-black uppercase tracking-widest text-white">¿Afinar la búsqueda?</p>
                <p class="text-xs text-slate-400 mt-0.5">Prioriza dentro de las candidatas ya filtradas. Para géneros distintos, inicia una nueva búsqueda.</p>
              </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-2">
              <input
                v-model="refinement"
                @keydown.enter="refineResults"
                :disabled="reranking"
                type="text"
                maxlength="120"
                placeholder="Ej: algo más pausado, sin violencia, con buenas actuaciones…"
                class="flex-1 bg-slate-900/60 border border-slate-800 hover:border-slate-700 focus:border-brand/60 rounded-lg px-4 py-3 text-sm text-white placeholder:text-slate-600 placeholder:font-light focus:outline-none focus:ring-1 focus:ring-brand/30 disabled:opacity-40 transition-all"
              />
              <button
                @click="refineResults"
                :disabled="!refinement.trim() || reranking"
                :class="['flex items-center justify-center gap-2 px-5 py-3 rounded-lg font-black text-[10px] uppercase tracking-widest transition-all sm:flex-shrink-0',
                  refinement.trim() && !reranking
                    ? 'bg-brand hover:bg-[#d4310e] text-white shadow-[0_2px_12px_rgba(190,43,12,0.35)]'
                    : 'bg-slate-800 text-slate-600 cursor-not-allowed']"
              >
                <svg v-if="reranking" class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
                <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                </svg>
                {{ reranking ? 'Analizando…' : 'Reanalizar' }}
              </button>
            </div>
          </div>

          <!-- Acciones -->
          <div class="flex items-center justify-center gap-4 mt-8 pt-6 border-t border-slate-800">
            <button
              @click="restart"
              class="flex items-center gap-2 px-6 py-2.5 border border-slate-700 rounded text-[10px] font-black uppercase tracking-widest text-slate-400 hover:border-brand hover:text-white transition-all"
            >
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
              </svg>
              Nueva búsqueda
            </button>
            <button
              @click="router.push('/')"
              class="px-6 py-2.5 bg-brand hover:bg-brand-dark text-white rounded text-[10px] font-black uppercase tracking-widest transition-all hover:scale-105"
            >
              Ir al inicio
            </button>
          </div>
        </template>
      </div>

    </div>
  </div>
</template>

<style scoped>
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

@keyframes fade-in {
  from { opacity: 0; transform: translateY(16px); }
  to   { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
  animation: fade-in 0.35s ease-out forwards;
}

.brand-scroll::-webkit-scrollbar { width: 4px; height: 4px; }
.brand-scroll::-webkit-scrollbar-track { background: #1e293b; border-radius: 10px; }
.brand-scroll::-webkit-scrollbar-thumb { background: var(--color-brand, #BE2B0C); border-radius: 10px; }
@media (max-width: 640px) {
  .brand-scroll::-webkit-scrollbar { height: 0; width: 0; }
}
</style>
