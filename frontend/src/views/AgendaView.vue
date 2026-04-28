<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '@/services/api'

// ─── Estado ───────────────────────────────────────────────────────────────
const events     = ref([])
const pagination = ref(null)
const isLoading  = ref(false)
const error      = ref(null)

const filters = ref({
  period:     'ongoing', // ongoing | upcoming | week | month | all
  island:     '',
  event_type: '',
  page:       1,
})

// ─── Constantes ───────────────────────────────────────────────────────────
const ISLANDS = [
  { code: 'GC',  label: 'Gran Canaria' },
  { code: 'TF',  label: 'Tenerife' },
  { code: 'LZ',  label: 'Lanzarote' },
  { code: 'FV',  label: 'Fuerteventura' },
  { code: 'LP',  label: 'La Palma' },
  { code: 'EH',  label: 'El Hierro' },
  { code: 'GO',  label: 'La Gomera' },
  { code: 'ALL', label: 'Todas las islas' },
]

const EVENT_TYPES = [
  { value: 'festival',   label: 'Festival',   color: 'type--festival' },
  { value: 'projection', label: 'Proyección', color: 'type--projection' },
  { value: 'cycle',      label: 'Ciclo',      color: 'type--cycle' },
  { value: 'workshop',   label: 'Taller',     color: 'type--workshop' },
  { value: 'other',      label: 'Otro',       color: 'type--other' },
]

// ─── Helpers ──────────────────────────────────────────────────────────────
const typeInfo = (val) => EVENT_TYPES.find(t => t.value === val) ?? { label: val ?? '—', color: 'type--other' }
const islandLabel = (code) => ISLANDS.find(i => i.code === code)?.label ?? code ?? '—'

// Normaliza la fecha a YYYY-MM-DD (maneja tanto '2026-04-22' como '2026-04-22T00:00:00.000000Z')
const dateOnly = (d) => d ? String(d).split('T')[0] : null

const fmtDay   = (d) => { const c = dateOnly(d); return c ? new Date(c + 'T12:00:00').toLocaleDateString('es-ES', { day: '2-digit' }) : '' }
const fmtMonth = (d) => { const c = dateOnly(d); return c ? new Date(c + 'T12:00:00').toLocaleDateString('es-ES', { month: 'short' }).toUpperCase() : '' }
const fmtYear  = (d) => { const c = dateOnly(d); return c ? new Date(c + 'T12:00:00').getFullYear() : '' }

const fmtDateRange = (event) => {
  const sd = dateOnly(event.start_date)
  const ed = dateOnly(event.end_date)
  if (!sd) return '—'
  const s    = new Date(sd + 'T12:00:00')
  const opts = { day: 'numeric', month: 'long' }
  if (!ed || sd === ed) {
    return s.toLocaleDateString('es-ES', { ...opts, year: 'numeric' })
  }
  const e = new Date(ed + 'T12:00:00')
  if (s.getMonth() === e.getMonth()) {
    return `${s.getDate()} – ${e.toLocaleDateString('es-ES', opts)}, ${s.getFullYear()}`
  }
  return `${s.toLocaleDateString('es-ES', opts)} – ${e.toLocaleDateString('es-ES', { ...opts, year: 'numeric' })}`
}

// Calcular parámetros de fecha según el periodo seleccionado
const dateParams = computed(() => {
  const today = new Date()
  const pad   = (n) => String(n).padStart(2, '0')
  const fmt   = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`

  if (filters.value.period === 'ongoing') {
    return { ongoing: 1 }
  }
  if (filters.value.period === 'week') {
    const end = new Date(today); end.setDate(today.getDate() + 7)
    return { date_from: fmt(today), date_to: fmt(end) }
  }
  if (filters.value.period === 'month') {
    const end = new Date(today); end.setDate(today.getDate() + 30)
    return { date_from: fmt(today), date_to: fmt(end) }
  }
  if (filters.value.period === 'all') {
    return {}
  }
  return { upcoming: 1 }
})

const activeIslandLabel = computed(() =>
  filters.value.island ? islandLabel(filters.value.island) : null
)
const activeTypeLabel = computed(() =>
  filters.value.event_type ? typeInfo(filters.value.event_type).label : null
)

// ─── Carga ────────────────────────────────────────────────────────────────
const fetchEvents = async () => {
  isLoading.value = true
  error.value     = null
  try {
    const params = {
      ...dateParams.value,
      ...(filters.value.island     ? { island:      filters.value.island }     : {}),
      ...(filters.value.event_type ? { event_type:  filters.value.event_type } : {}),
      page: filters.value.page,
    }
    const { data } = await api.get('/events/public', { params })
    events.value     = data.data?.data ?? []
    pagination.value = data.data
  } catch {
    error.value = 'No se pudo cargar la agenda.'
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchEvents)

watch(
  [() => filters.value.period, () => filters.value.island, () => filters.value.event_type],
  () => { filters.value.page = 1; fetchEvents() }
)

const goToPage = (page) => { filters.value.page = page; fetchEvents() }

const setPeriod = (p) => { filters.value.period = p }
const clearIsland = () => { filters.value.island = '' }
const clearType   = () => { filters.value.event_type = '' }
</script>

<template>
  <div class="min-h-screen w-full bg-[#14181c] text-slate-100 font-sans overflow-x-hidden pb-20">
  <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 pt-10">

    <!-- ── Hero header ────────────────────────────────────────────────── -->
    <div class="mb-8">
      <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-amber-500/70 mb-1">
        Cine en Canarias
      </p>
      <h1 class="text-2xl sm:text-3xl font-black uppercase tracking-tight text-white leading-tight">
        Agenda cinematográfica
      </h1>
      <p class="text-sm text-slate-500 mt-2 max-w-lg">
        Festivales, proyecciones y ciclos de cine en las Islas Canarias.
      </p>
    </div>

    <!-- ── Filtros ────────────────────────────────────────────────────── -->
    <div class="filters-block mb-8">

      <!-- Periodo — pills -->
      <div class="flex gap-1.5 flex-wrap mb-3">
        <button
          v-for="p in [
            { key: 'ongoing',  label: 'En curso' },
            { key: 'upcoming', label: 'Próximos' },
            { key: 'week',     label: 'Esta semana' },
            { key: 'month',    label: 'Este mes' },
            { key: 'all',      label: 'Todos' },
          ]"
          :key="p.key"
          class="period-pill"
          :class="{ active: filters.period === p.key }"
          @click="setPeriod(p.key)"
        >{{ p.label }}</button>
      </div>

      <!-- Isla + tipo — selects -->
      <div class="flex gap-2 flex-wrap items-center">
        <select v-model="filters.island" class="filter-select">
          <option value="">Todas las islas</option>
          <option v-for="i in ISLANDS" :key="i.code" :value="i.code">{{ i.label }}</option>
        </select>

        <select v-model="filters.event_type" class="filter-select">
          <option value="">Todos los tipos</option>
          <option v-for="t in EVENT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>

        <!-- Tags de filtros activos -->
        <div class="flex gap-1.5 flex-wrap">
          <span v-if="activeIslandLabel" class="active-filter-tag" @click="clearIsland">
            {{ activeIslandLabel }} ✕
          </span>
          <span v-if="activeTypeLabel" class="active-filter-tag" @click="clearType">
            {{ activeTypeLabel }} ✕
          </span>
        </div>
      </div>
    </div>

    <!-- ── Loading ───────────────────────────────────────────────────── -->
    <div v-if="isLoading" class="flex justify-center py-20">
      <svg class="w-7 h-7 text-slate-600 animate-spin" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
      </svg>
    </div>

    <!-- ── Error ─────────────────────────────────────────────────────── -->
    <div v-else-if="error" class="text-center py-16 text-red-400 text-sm">{{ error }}</div>

    <!-- ── Vacío ─────────────────────────────────────────────────────── -->
    <div v-else-if="events.length === 0" class="py-20 text-center">
      <div class="text-4xl mb-4">🎬</div>
      <p class="text-slate-500 text-sm max-w-xs mx-auto">
        No hay eventos programados
        {{ filters.island || filters.event_type ? 'con los filtros seleccionados' : 'próximamente' }}.
      </p>
    </div>

    <!-- ── Lista de eventos ───────────────────────────────────────────── -->
    <div v-else class="flex flex-col gap-4">
      <article
        v-for="event in events"
        :key="event.id"
        class="event-card"
      >

        <!-- Bloque de fecha -->
        <div class="date-block" :class="{ 'date-block--range': dateOnly(event.end_date) && dateOnly(event.end_date) !== dateOnly(event.start_date) }">
          <template v-if="dateOnly(event.end_date) && dateOnly(event.end_date) !== dateOnly(event.start_date)">
            <span class="date-day">{{ fmtDay(event.start_date) }}–{{ fmtDay(event.end_date) }}</span>
            <span class="date-month">{{ fmtMonth(event.start_date) }}</span>
            <span class="date-year">{{ fmtYear(event.start_date) }}</span>
          </template>
          <template v-else>
            <span class="date-day">{{ fmtDay(event.start_date) }}</span>
            <span class="date-month">{{ fmtMonth(event.start_date) }}</span>
            <span class="date-year">{{ fmtYear(event.start_date) }}</span>
          </template>
        </div>

        <!-- Miniatura opcional -->
        <div v-if="event.image_url" class="event-thumb">
          <img :src="event.image_url" :alt="event.title" class="event-thumb-img" loading="lazy" />
        </div>

        <!-- Contenido -->
        <div class="event-body">

          <!-- Badges superiores -->
          <div class="flex items-center gap-2 flex-wrap mb-1.5">
            <span class="event-type-badge" :class="typeInfo(event.event_type).color">
              {{ typeInfo(event.event_type).label }}
            </span>
            <span v-if="event.island" class="island-badge">
              {{ islandLabel(event.island) }}
            </span>
          </div>

          <!-- Título -->
          <h2 class="text-base sm:text-lg font-black text-white leading-snug mb-1">
            {{ event.title }}
          </h2>

          <!-- Venue + rango de fechas legible -->
          <div class="flex items-center gap-3 flex-wrap mb-2">
            <span v-if="event.venue" class="text-xs text-slate-400 flex items-center gap-1">
              <svg class="w-3 h-3 text-slate-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              {{ event.venue }}
            </span>
            <span class="text-xs text-slate-500">{{ fmtDateRange(event) }}</span>
          </div>

          <!-- Descripción -->
          <p v-if="event.description" class="text-sm text-slate-400 leading-relaxed line-clamp-2">
            {{ event.description }}
          </p>

          <!-- Acciones -->
          <div v-if="event.ticket_url || event.source_url" class="flex gap-3 mt-3">
            <a
              v-if="event.ticket_url"
              :href="event.ticket_url"
              target="_blank"
              rel="noopener noreferrer"
              class="btn-tickets"
            >Entradas →</a>
            <a
              v-if="event.source_url"
              :href="event.source_url"
              target="_blank"
              rel="noopener noreferrer"
              class="btn-source"
            >Más info ↗</a>
          </div>
        </div>

      </article>
    </div>

    <!-- ── Paginación ─────────────────────────────────────────────────── -->
    <div v-if="pagination && pagination.last_page > 1" class="flex justify-center flex-wrap gap-1.5 mt-10">
      <button
        v-for="p in pagination.last_page"
        :key="p"
        class="pagination-btn"
        :class="{ active: p === pagination.current_page }"
        @click="goToPage(p)"
      >{{ p }}</button>
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

/* ── Filtros ────────────────────────────────────────────────── */
.filters-block {
  border-bottom: 1px solid rgb(51 65 85 / 0.3);
  padding-bottom: 20px;
}

.period-pill {
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  padding: 5px 14px; border-radius: 99px;
  border: 1px solid rgb(51 65 85 / 0.5);
  color: #64748b; background: transparent;
  transition: all 150ms; cursor: pointer; white-space: nowrap;
}
.period-pill:hover { color: #e2e8f0; border-color: #475569; }
.period-pill.active {
  background: rgb(245 158 11 / 0.12);
  border-color: rgb(245 158 11 / 0.4);
  color: #f59e0b;
}

.filter-select {
  background: rgb(15 23 42 / 0.8); border: 1px solid rgb(51 65 85 / 0.6);
  border-radius: 8px; color: #94a3b8; font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.05em;
  padding: 6px 10px; outline: none; transition: border-color 150ms;
  flex: 1 1 auto; min-width: 130px; max-width: 200px;
}
.filter-select:focus { border-color: #475569; color: #e2e8f0; }
.filter-select option { background: #1e293b; }

.active-filter-tag {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
  padding: 3px 8px; border-radius: 5px;
  background: rgb(245 158 11 / 0.12); color: #f59e0b;
  border: 1px solid rgb(245 158 11 / 0.3);
  cursor: pointer; transition: background 150ms;
}
.active-filter-tag:hover { background: rgb(245 158 11 / 0.22); }

/* ── Cards horizontales ─────────────────────────────────────── */
.event-card {
  display: flex;
  gap: 0;
  background: #1b2228;
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: 14px;
  overflow: hidden;
  transition: border-color 200ms, background 200ms;
}
.event-card:hover {
  border-color: rgba(255,255,255,0.2);
  background: #1e262d;
}

/* Miniatura imagen */
.event-thumb {
  flex-shrink: 0;
  width: 140px;
  overflow: hidden;
}
.event-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
  opacity: 0.85;
  transition: opacity 200ms;
}
.event-card:hover .event-thumb-img { opacity: 1; }

@media (max-width: 479px) {
  .event-thumb { width: 90px; }
}

/* Bloque de fecha — columna izquierda -->*/
.date-block {
  flex-shrink: 0;
  width: 72px;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  padding: 16px 8px;
  background: rgb(245 158 11 / 0.06);
  border-right: 1px solid rgb(245 158 11 / 0.12);
  text-align: center;
  gap: 0;
}
.date-block--range { background: rgb(99 102 241 / 0.06); border-right-color: rgb(99 102 241 / 0.12); }

.date-day {
  font-size: 22px; font-weight: 900; line-height: 1;
  color: #f8fafc; letter-spacing: -0.02em;
  display: block;
}
.date-block--range .date-day { font-size: 14px; font-weight: 800; color: #c4b5fd; }
.date-month {
  font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em;
  color: #f59e0b; margin-top: 2px; display: block;
}
.date-block--range .date-month { color: #a5b4fc; }
.date-year {
  font-size: 10px; font-weight: 600; color: #475569; margin-top: 1px; display: block;
}

/* Cuerpo del evento */
.event-body {
  flex: 1;
  padding: 16px 18px;
  min-width: 0;
}

/* Badges de tipo */
.event-type-badge {
  font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;
  padding: 2px 7px; border-radius: 4px; white-space: nowrap;
}
.type--festival   { background: rgb(76 29 149/0.3);  color: #c4b5fd; }
.type--projection { background: rgb(12 74 110/0.3);  color: #7dd3fc; }
.type--cycle      { background: rgb(6 78 59/0.3);    color: #6ee7b7; }
.type--workshop   { background: rgb(120 53 15/0.3);  color: #fcd34d; }
.type--other      { background: rgb(51 65 85/0.5);   color: #94a3b8; }

.island-badge {
  font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  padding: 2px 7px; border-radius: 4px;
  background: rgb(30 41 59/0.8); color: #64748b;
  border: 1px solid rgb(51 65 85/0.4);
  white-space: nowrap;
}

/* Botones de acción */
.btn-tickets {
  display: inline-block; font-size: 10px; font-weight: 800;
  text-transform: uppercase; letter-spacing: 0.07em;
  padding: 5px 14px; border-radius: 6px;
  background: #f59e0b; color: #0f172a;
  transition: background 150ms;
}
.btn-tickets:hover { background: #fbbf24; }

.btn-source {
  display: inline-block; font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.07em;
  padding: 5px 12px; border-radius: 6px;
  color: #64748b; border: 1px solid rgb(51 65 85/0.5);
  transition: color 150ms, border-color 150ms;
}
.btn-source:hover { color: #e2e8f0; border-color: #475569; }

/* ── Paginación ─────────────────────────────────────────────── */
.pagination-btn {
  min-width: 32px; height: 32px; border-radius: 6px;
  background: #1e293b; border: 1px solid #334155;
  color: #64748b; font-size: 12px; font-weight: 700; transition: all 150ms;
}
.pagination-btn:hover, .pagination-btn.active { background: #334155; color: #e2e8f0; }

/* ── Responsive ─────────────────────────────────────────────── */
@media (max-width: 479px) {
  .date-block { width: 58px; padding: 12px 6px; }
  .date-day   { font-size: 18px; }
  .event-body { padding: 12px 14px; }
}
</style>
