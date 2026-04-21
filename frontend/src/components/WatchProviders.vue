<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

const props = defineProps({
  filmId:  { type: [Number, String], required: true },
  compact: { type: Boolean, default: false },
})

const COUNTRIES = [
  { code: 'MX', label: 'México' },
  { code: 'AR', label: 'Argentina' },
  { code: 'ES', label: 'España' },
  { code: 'CO', label: 'Colombia' },
  { code: 'CL', label: 'Chile' },
  { code: 'PE', label: 'Perú' },
  { code: 'EC', label: 'Ecuador' },
  { code: 'UY', label: 'Uruguay' },
  { code: 'VE', label: 'Venezuela' },
]

const providers     = ref({})
const isLoading     = ref(true)
const activeCountry = ref(null)

const availableCountries = computed(() =>
  COUNTRIES.filter(c => providers.value[c.code])
)

const current = computed(() =>
  activeCountry.value ? providers.value[activeCountry.value] ?? null : null
)

const hasAny = computed(() => availableCountries.value.length > 0)

// Modo compacto: solo flatrate del primer país disponible
const compactProviders = computed(() => {
  if (!activeCountry.value || !providers.value[activeCountry.value]) return []
  return providers.value[activeCountry.value]?.flatrate?.slice(0, 5) ?? []
})

const compactLink = computed(() =>
  activeCountry.value ? providers.value[activeCountry.value]?.link ?? null : null
)

onMounted(async () => {
  try {
    const { data } = await api.get(`/films/${props.filmId}/watch-providers`)
    if (data.success) {
      providers.value = data.data
      const first = COUNTRIES.find(c => data.data[c.code])
      if (first) activeCountry.value = first.code
    }
  } catch (e) {
    console.error('Error fetching watch providers:', e)
  } finally {
    isLoading.value = false
  }
})
</script>

<template>
  <!-- ── MODO COMPACTO (badge para cards del recomendador) ── -->
  <template v-if="compact">
    <div v-if="isLoading" class="flex gap-1.5 mt-2">
      <div v-for="i in 3" :key="i" class="w-6 h-6 rounded bg-white/5 animate-pulse"></div>
    </div>
    <div v-else-if="hasAny && compactProviders.length" class="flex items-center gap-1.5 mt-2 flex-wrap">
      <a
        v-for="p in compactProviders"
        :key="p.id"
        :href="compactLink"
        target="_blank"
        rel="noopener noreferrer"
        :title="p.name"
        @click.stop
        class="w-6 h-6 rounded-md overflow-hidden border border-white/10 hover:border-white/40 transition-all hover:scale-110 flex-shrink-0 shadow-sm"
      >
        <img :src="p.logo" :alt="p.name" class="w-full h-full object-cover" loading="lazy" />
      </a>
      <span class="text-[8px] text-slate-600 font-bold uppercase tracking-wider ml-0.5">
        {{ activeCountry }}
      </span>
    </div>
  </template>

  <!-- ── MODO COMPLETO (FilmDetailView sidebar) ── -->
  <div v-else class="watch-providers bg-[#1b2228] border border-white/5 rounded-lg overflow-hidden shadow-xl mb-10">

    <header class="flex items-center gap-3 px-6 py-4 border-b border-white/5 bg-white/[0.02]">
      <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-[#BE2B0C] flex-shrink-0" fill="currentColor" viewBox="0 0 24 24">
        <path d="M8 5v14l11-7z"/>
      </svg>
      <h3 class="text-xs font-black text-white uppercase tracking-[0.3em]">Dónde ver</h3>
      <span class="text-[9px] text-[#445] font-bold uppercase tracking-widest ml-auto">via JustWatch · TMDB</span>
    </header>

    <!-- Loading -->
    <div v-if="isLoading" class="p-6 flex gap-3">
      <div v-for="i in 5" :key="i" class="w-10 h-10 rounded-lg bg-white/5 animate-pulse"></div>
    </div>

    <!-- Sin datos -->
    <div v-else-if="!hasAny" class="px-6 py-8 text-center">
      <p class="text-[10px] text-[#445] font-black uppercase tracking-widest">
        Sin información de plataformas disponible
      </p>
    </div>

    <template v-else>
      <!-- Tabs de países -->
      <div class="flex flex-wrap gap-2 px-5 pt-4 pb-2">
        <button
          v-for="c in availableCountries"
          :key="c.code"
          @click="activeCountry = c.code"
          class="px-3 py-1.5 rounded text-[9px] font-black uppercase tracking-widest border transition-all duration-150"
          :class="activeCountry === c.code
            ? 'bg-[#BE2B0C]/15 text-white border-[#BE2B0C]/50'
            : 'bg-transparent text-[#678] border-[#2a3240] hover:border-[#445] hover:text-[#9ab]'"
        >
          {{ c.label }}
        </button>
      </div>

      <!-- Contenido del país activo -->
      <div v-if="current" class="px-5 pb-5 pt-3 space-y-5">

        <div v-if="current.flatrate?.length">
          <p class="text-[9px] font-black text-[#678] uppercase tracking-widest mb-3">Streaming</p>
          <div class="flex flex-wrap gap-2">
            <a
              v-for="p in current.flatrate"
              :key="p.id"
              :href="current.link"
              target="_blank"
              rel="noopener noreferrer"
              :title="p.name"
              class="group relative w-11 h-11 rounded-xl overflow-hidden border border-white/10 hover:border-[#BE2B0C]/60 transition-all duration-200 hover:scale-110 shadow-md"
            >
              <img :src="p.logo" :alt="p.name" class="w-full h-full object-cover" loading="lazy" />
              <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all"></div>
            </a>
          </div>
        </div>

        <div v-if="current.rent?.length">
          <p class="text-[9px] font-black text-[#678] uppercase tracking-widest mb-3">Alquiler</p>
          <div class="flex flex-wrap gap-2">
            <a
              v-for="p in current.rent"
              :key="p.id"
              :href="current.link"
              target="_blank"
              rel="noopener noreferrer"
              :title="p.name"
              class="group relative w-11 h-11 rounded-xl overflow-hidden border border-white/10 hover:border-amber-500/60 transition-all duration-200 hover:scale-110 shadow-md"
            >
              <img :src="p.logo" :alt="p.name" class="w-full h-full object-cover" loading="lazy" />
              <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all"></div>
            </a>
          </div>
        </div>

        <div v-if="current.buy?.length">
          <p class="text-[9px] font-black text-[#678] uppercase tracking-widest mb-3">Compra</p>
          <div class="flex flex-wrap gap-2">
            <a
              v-for="p in current.buy"
              :key="p.id"
              :href="current.link"
              target="_blank"
              rel="noopener noreferrer"
              :title="p.name"
              class="group relative w-11 h-11 rounded-xl overflow-hidden border border-white/10 hover:border-emerald-500/60 transition-all duration-200 hover:scale-110 shadow-md"
            >
              <img :src="p.logo" :alt="p.name" class="w-full h-full object-cover" loading="lazy" />
              <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition-all"></div>
            </a>
          </div>
        </div>

        <div v-if="!current.flatrate?.length && !current.rent?.length && !current.buy?.length">
          <p class="text-[10px] text-[#445] font-black uppercase tracking-widest py-2">Sin plataformas para este país</p>
        </div>

        <a
          v-if="current.link"
          :href="current.link"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-[#678] hover:text-white border border-[#2a3240] hover:border-[#445] px-3 py-1.5 rounded transition-all"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
          Ver todas las opciones en JustWatch
        </a>
      </div>
    </template>

  </div>
</template>
