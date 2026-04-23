<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const isEditor = computed(() => auth.user?.idRol === 1 || auth.user?.idRol === 2)

// ─── Estado ───────────────────────────────────────────────────────────────
const events       = ref([])
const sources      = ref([])
const pagination   = ref(null)
const isLoading   = ref(false)
const error       = ref(null)
const isActioning = ref(false)

const filters = ref({
  status:     '',
  island:     '',
  event_type: '',
  source_id:  '',
  sort:       'fecha_asc',
  page:       1,
})

// Modal de edición
const selectedEvent = ref(null)
const showModal     = ref(false)
const editForm      = ref({})
const isSaving      = ref(false)

// Modal de creación
const showCreateModal = ref(false)
const isCreating      = ref(false)
const createForm      = ref({
  title:       '',
  description: '',
  start_date:  '',
  end_date:    '',
  event_type:  '',
  venue:       '',
  island:      '',
  ticket_url:  '',
  image_url:   '',
  source_url:  '',
})

// Toast
const toastMsg  = ref('')
const toastType = ref('success')

// ─── Constantes ───────────────────────────────────────────────────────────
const ISLANDS = [
  { code: 'GC', label: 'Gran Canaria' },
  { code: 'TF', label: 'Tenerife' },
  { code: 'LZ', label: 'Lanzarote' },
  { code: 'FV', label: 'Fuerteventura' },
  { code: 'LP', label: 'La Palma' },
  { code: 'EH', label: 'El Hierro' },
  { code: 'GO', label: 'La Gomera' },
  { code: 'ALL', label: 'Todas las islas' },
]

const EVENT_TYPES = [
  { value: 'festival',   label: 'Festival' },
  { value: 'projection', label: 'Proyección' },
  { value: 'cycle',      label: 'Ciclo' },
  { value: 'workshop',   label: 'Taller' },
  { value: 'other',      label: 'Otro' },
]

// ─── Helpers ──────────────────────────────────────────────────────────────
const islandLabel  = (code) => ISLANDS.find(i => i.code === code)?.label ?? code ?? '—'
const typeLabel    = (val)  => EVENT_TYPES.find(t => t.value === val)?.label  ?? val  ?? '—'

const statusLabel = (s) => ({
  pending:      'Pendiente',
  confirmed:    'Confirmado',
  rejected:     'Rechazado',
  needs_review: 'Revisar',
}[s] ?? s)

const confidenceColor = (c) => {
  if (c == null)  return 'bg-slate-700/60 text-slate-500 border-slate-700/40'
  if (c >= 0.8)   return 'bg-emerald-900/60 text-emerald-300 border-emerald-800/40'
  if (c >= 0.6)   return 'bg-amber-900/60 text-amber-300 border-amber-800/40'
  return 'bg-red-900/50 text-red-400 border-red-800/40'
}

const fmtDate = (d) => {
  if (!d) return null
  const clean = String(d).split('T')[0]
  const date  = new Date(clean + 'T12:00:00')
  if (isNaN(date.getTime())) return '—'
  return date.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
}

const fmtDateRange = (event) => {
  const start = fmtDate(event.start_date)
  const end   = fmtDate(event.end_date)
  if (!start)       return '—'
  if (!end || event.start_date === event.end_date) return start
  return `${start} – ${end}`
}

const showToast = (msg, type = 'success') => {
  toastMsg.value  = msg
  toastType.value = type
  setTimeout(() => { toastMsg.value = '' }, 3500)
}

// ─── Carga ────────────────────────────────────────────────────────────────
const fetchEvents = async () => {
  isLoading.value = true
  error.value     = null
  try {
    const params = Object.fromEntries(
      Object.entries(filters.value).filter(([, v]) => v !== '' && v !== null)
    )
    const { data } = await api.get('/events', { params })
    events.value     = data.data?.data ?? []
    pagination.value = data.data
  } catch (e) {
    error.value = 'No se pudo cargar los eventos.'
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

const fetchSources = async () => {
  try {
    const { data } = await api.get('/events/sources/list')
    sources.value = data.data ?? []
  } catch {/* silencioso */}
}

onMounted(() => {
  if (!isEditor.value) { router.push({ name: 'home' }); return }
  fetchEvents()
  fetchSources()

  // Pre-rellenar formulario si viene desde el Inbox via query params
  const { prefill_title, prefill_desc, prefill_url } = route.query
  if (prefill_title) {
    createForm.value.title       = prefill_title
    createForm.value.description = prefill_desc || ''
    createForm.value.source_url  = prefill_url  || ''
    showCreateModal.value = true
    // Limpiar query params de la URL sin navegar
    router.replace({ name: 'event-manager' })
  }
})

watch([
  () => filters.value.status,
  () => filters.value.island,
  () => filters.value.event_type,
  () => filters.value.source_id,
  () => filters.value.sort,
], () => { filters.value.page = 1; fetchEvents() })

// ─── Acciones de estado ───────────────────────────────────────────────────
const quickStatus = async (event, status) => {
  isActioning.value = true
  try {
    await api.patch(`/events/${event.id}/status`, { status })
    event.status = status
    showToast(
      status === 'confirmed'    ? 'Evento confirmado — aparece en la agenda pública.' :
      status === 'rejected'     ? 'Evento rechazado.' :
      status === 'needs_review' ? 'Marcado para revisión.' :
      'Estado actualizado.'
    )
    if (filters.value.status && filters.value.status !== status) {
      events.value = events.value.filter(e => e.id !== event.id)
    }
  } catch {
    showToast('Error al actualizar el estado.', 'error')
  } finally {
    isActioning.value = false
  }
}

const deleteEvent = async (event) => {
  if (!confirm(`¿Eliminar "${event.title}"?`)) return
  isActioning.value = true
  try {
    await api.delete(`/events/${event.id}`)
    events.value = events.value.filter(e => e.id !== event.id)
    showToast('Evento eliminado.')
    if (showModal.value) closeModal()
  } catch {
    showToast('Error al eliminar el evento.', 'error')
  } finally {
    isActioning.value = false
  }
}

// ─── Edición de fuentes ───────────────────────────────────────────────────
const editingSourceId = ref(null)
const editSourceForm  = ref({ name: '', url: '' })
const isSavingSource  = ref(false)

const startEditSource  = (source) => { editingSourceId.value = source.id; editSourceForm.value = { name: source.name, url: source.url } }
const cancelEditSource = () => { editingSourceId.value = null }

const saveEditSource = async (source) => {
  isSavingSource.value = true
  try {
    const { data } = await api.patch(`/events/sources/${source.id}`, editSourceForm.value)
    source.name = data.data.name
    source.url  = data.data.url
    showToast('Fuente actualizada.')
    cancelEditSource()
  } catch {
    showToast('Error al guardar.', 'error')
  } finally {
    isSavingSource.value = false
  }
}

// ─── Modal de edición ────────────────────────────────────────────────────
const openModal = (event) => {
  selectedEvent.value = event
  editForm.value = {
    title:       event.title       ?? '',
    description: event.description ?? '',
    start_date:  event.start_date  ? String(event.start_date).split('T')[0] : '',
    end_date:    event.end_date    ? String(event.end_date).split('T')[0]   : '',
    event_type:  event.event_type  ?? 'other',
    venue:       event.venue       ?? '',
    island:      event.island      ?? '',
    ticket_url:  event.ticket_url  ?? '',
    image_url:   event.image_url   ?? '',
  }
  showModal.value = true
}

const closeModal = () => {
  showModal.value     = false
  selectedEvent.value = null
}

const saveEdit = async () => {
  if (!selectedEvent.value) return
  isSaving.value = true
  try {
    const { data } = await api.patch(`/events/${selectedEvent.value.id}`, editForm.value)
    // Actualizar en la lista local
    const idx = events.value.findIndex(e => e.id === selectedEvent.value.id)
    if (idx !== -1) events.value[idx] = { ...events.value[idx], ...data.data }
    showToast('Evento actualizado.')
    closeModal()
  } catch {
    showToast('Error al guardar los cambios.', 'error')
  } finally {
    isSaving.value = false
  }
}

const openCreateModal = () => {
  createForm.value = {
    title: '', description: '', start_date: '', end_date: '',
    event_type: '', venue: '', island: '', ticket_url: '', image_url: '', source_url: '',
  }
  showCreateModal.value = true
}

const closeCreateModal = () => { showCreateModal.value = false }

const createEvent = async (status = 'confirmed') => {
  if (!createForm.value.title || !createForm.value.start_date) {
    showToast('Título y fecha de inicio son obligatorios.', 'error')
    return
  }
  isCreating.value = true
  try {
    const { data } = await api.post('/events', { ...createForm.value, status })
    events.value.unshift(data.data)
    showToast(status === 'pending' ? 'Evento guardado como borrador.' : 'Evento creado y confirmado.')
    closeCreateModal()
  } catch {
    showToast('Error al crear el evento.', 'error')
  } finally {
    isCreating.value = false
  }
}

const goToPage = (page) => {
  filters.value.page = page
  fetchEvents()
}
</script>

<template>
  <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10">

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-xl sm:text-2xl font-black uppercase tracking-wider text-white">Event Manager</h1>
        <p class="text-[10px] text-slate-500 mt-1 uppercase tracking-widest">Agenda cinematográfica · Canarias</p>
      </div>
      <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
        <button
          class="header-action-btn new-event"
          @click="openCreateModal"
        >
          + Nuevo evento
        </button>
      </div>
    </div>

    <!-- ── Descripción ───────────────────────────────────────────────── -->
    <p class="section-desc mb-6">
      Añade eventos manualmente con el botón <strong class="text-slate-300">Nuevo evento</strong>
      y úsalas fuentes de referencia para consultar la programación en webs externas.
      Confirma cada evento para que aparezca en la agenda pública.
    </p>

    <!-- ── Panel de fuentes ────────────────────────────────────────────── -->
    <details class="sources-panel mb-6" :open="sources.length === 0">
      <summary class="sources-summary">
        <svg class="sources-chevron" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        </svg>
        <span class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-400">Fuentes configuradas</span>
        <span class="ml-auto text-[10px] text-slate-600">{{ sources.length }} fuentes</span>
      </summary>

      <!-- Sin fuentes en BD -->
      <div v-if="sources.length === 0" class="sources-empty">
        <p class="text-xs text-slate-600">No hay fuentes configuradas.</p>
      </div>

      <!-- Lista de fuentes -->
      <div v-else class="sources-list">
        <div
          v-for="s in sources"
          :key="s.id"
          class="source-row"
        >
          <!-- Tipo RSS / Scraping -->
          <span class="source-type" :class="s.type === 'rss' ? 'source-type--rss' : 'source-type--scrape'">
            {{ s.type === 'rss' ? 'RSS' : 'HTML' }}
          </span>

          <!-- Nombre + URL / formulario inline -->
          <div class="source-name-block">
            <template v-if="editingSourceId === s.id">
              <input v-model="editSourceForm.name" class="src-edit-input mb-0.5" placeholder="Nombre" />
              <input
                v-model="editSourceForm.url"
                class="src-edit-input"
                placeholder="https://…"
                @keydown.enter="saveEditSource(s)"
                @keydown.escape="cancelEditSource"
              />
            </template>
            <template v-else>
              <span class="source-name">{{ s.name }}</span>
              <a :href="s.url" target="_blank" rel="noopener noreferrer" class="source-url">{{ s.url }}</a>
            </template>
          </div>

          <!-- Botones -->
          <div class="flex items-center gap-1.5 flex-shrink-0">

            <!-- Modo edición: Guardar / Cancelar -->
            <template v-if="editingSourceId === s.id">
              <button class="btn-src-save" :disabled="isSavingSource" @click.stop="saveEditSource(s)">
                {{ isSavingSource ? '…' : 'Guardar' }}
              </button>
              <button class="btn-src-cancel" @click.stop="cancelEditSource">Cancelar</button>
            </template>
            <!-- Modo normal: solo Editar -->
            <template v-else>
              <button class="btn-src-edit" title="Editar nombre y URL" @click.stop="startEditSource(s)">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/>
                </svg>
              </button>
            </template>
          </div>
        </div>
      </div>
    </details>

    <!-- ── Filtros ────────────────────────────────────────────────────── -->
    <div class="filter-bar mb-6">
      <select v-model="filters.sort" class="filter-select">
        <option value="fecha_asc">Más próximos</option>
        <option value="fecha_desc">Más lejanos</option>
        <option value="confidence_desc">Mayor confianza IA</option>
      </select>
      <select v-model="filters.status" class="filter-select">
        <option value="">Todos los estados</option>
        <option value="pending">Pendiente</option>
        <option value="confirmed">Confirmado</option>
        <option value="needs_review">Revisar</option>
        <option value="rejected">Rechazado</option>
      </select>
      <select v-model="filters.island" class="filter-select">
        <option value="">Todas las islas</option>
        <option v-for="i in ISLANDS" :key="i.code" :value="i.code">{{ i.label }}</option>
      </select>
      <select v-model="filters.event_type" class="filter-select">
        <option value="">Todos los tipos</option>
        <option v-for="t in EVENT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
      </select>
      <select v-model="filters.source_id" class="filter-select">
        <option value="">Todas las fuentes</option>
        <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <button class="btn-ghost" @click="fetchEvents">
        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
        </svg>
        Actualizar
      </button>
    </div>

    <!-- ── Loading / error / vacío ───────────────────────────────────── -->
    <div v-if="isLoading" class="flex justify-center py-20">
      <svg class="w-8 h-8 text-slate-600" style="animation: spin 0.7s linear infinite" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
      </svg>
    </div>

    <div v-else-if="error" class="text-center py-16 text-red-400 text-sm">{{ error }}</div>

    <div v-else-if="events.length === 0" class="py-20 flex flex-col items-center gap-5 text-center">
      <p class="text-slate-500 text-sm max-w-sm">
        <template v-if="filters.status || filters.island || filters.event_type">
          No hay eventos con los filtros seleccionados.
        </template>
        <template v-else>
          Aún no hay eventos en la agenda. Usa <strong class="text-slate-300">Nuevo evento</strong> para añadir el primero manualmente.
        </template>
      </p>
      <button
        class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider px-5 py-2.5 rounded-lg bg-slate-800/70 text-slate-300 border border-slate-700/50 hover:bg-slate-700/60 transition-colors"
        @click="openCreateModal"
      >+ Nuevo evento</button>
    </div>

    <!-- ── Lista de eventos ───────────────────────────────────────────── -->
    <div v-else class="flex flex-col gap-3">
      <article
        v-for="event in events"
        :key="event.id"
        class="event-card"
        :class="{ 'event-card--review': event.status === 'needs_review' }"
      >

        <!-- Zona clickable → abre modal -->
        <div class="cursor-pointer" @click="openModal(event)">
          <div class="flex items-start gap-3">

            <!-- Miniatura -->
            <div v-if="event.image_url" class="flex-shrink-0 w-[60px] h-[60px] rounded-lg overflow-hidden bg-slate-800/60">
              <img :src="event.image_url" :alt="event.title" class="w-full h-full object-cover" loading="lazy" />
            </div>

            <!-- Contenido principal -->
            <div class="flex-1 min-w-0">
              <h2 class="text-sm font-bold text-white leading-snug line-clamp-2">
                {{ event.title }}
              </h2>

              <!-- Metadatos en línea -->
              <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                <!-- Fecha -->
                <span class="text-[11px] font-semibold text-slate-300 whitespace-nowrap">
                  {{ fmtDateRange(event) }}
                </span>
                <span class="text-slate-700">·</span>
                <!-- Tipo -->
                <span class="badge-type" :class="`badge-type--${event.event_type}`">
                  {{ typeLabel(event.event_type) }}
                </span>
                <!-- Isla -->
                <span v-if="event.island" class="badge-island">{{ islandLabel(event.island) }}</span>
                <!-- Estado (solo si no es pending) -->
                <span v-if="event.status !== 'pending'" class="badge-status" :class="`badge-status--${event.status}`">
                  {{ statusLabel(event.status) }}
                </span>
              </div>

              <!-- Venue -->
              <p v-if="event.venue" class="text-xs text-slate-500 mt-1.5 truncate">
                <span class="text-slate-600">📍</span> {{ event.venue }}
              </p>

              <!-- Descripción -->
              <p v-if="event.description" class="text-xs text-slate-400 mt-1.5 line-clamp-2 leading-relaxed">
                {{ event.description }}
              </p>
              <p v-else class="text-xs text-slate-600 mt-1.5 italic">Sin procesar por IA aún…</p>
            </div>

            <!-- Confianza IA badge -->
            <div
              class="flex-shrink-0 flex flex-col items-center justify-center w-10 h-10 rounded-xl text-[11px] font-black border"
              :class="confidenceColor(event.ai_confidence)"
              :title="event.ai_confidence ? `Confianza IA: ${Math.round(event.ai_confidence * 100)}%` : 'Sin procesar'"
            >
              <span v-if="event.ai_confidence != null">{{ Math.round(event.ai_confidence * 100) }}%</span>
              <span v-else class="text-slate-600">—</span>
            </div>

          </div>
        </div>

        <!-- ── Barra de acciones ─────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mt-3 pt-3 border-t border-slate-800/80">

          <button
            v-if="event.status !== 'confirmed'"
            class="action-btn confirm"
            :disabled="isActioning"
            title="Publica este evento en la agenda pública"
            @click="quickStatus(event, 'confirmed')"
          >Confirmar</button>

          <div class="flex-1"></div>

          <a
            v-if="event.source_url"
            :href="event.source_url"
            target="_blank"
            rel="noopener noreferrer"
            class="action-link"
          >Fuente ↗</a>

          <button
            class="action-btn preview hidden sm:inline-flex"
            @click="openModal(event)"
          >Editar</button>

        </div>

      </article>
    </div>

    <!-- ── Paginación ─────────────────────────────────────────────────── -->
    <div v-if="pagination && pagination.last_page > 1" class="flex justify-center flex-wrap gap-1.5 mt-8">
      <button
        v-for="p in pagination.last_page"
        :key="p"
        class="pagination-btn"
        :class="{ active: p === pagination.current_page }"
        @click="goToPage(p)"
      >{{ p }}</button>
    </div>

    <!-- ── Modal de creación manual ─────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showCreateModal" class="modal-overlay" @click.self="closeCreateModal">
        <div class="modal-box">

          <div class="flex items-start justify-between gap-4 mb-5">
            <div>
              <h2 class="text-base font-black text-white">Nuevo evento</h2>
              <p class="text-[10px] text-slate-500 mt-0.5 uppercase tracking-widest">Entrada manual · se confirma directamente</p>
            </div>
            <button class="text-slate-600 hover:text-slate-300 transition-colors" @click="closeCreateModal">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="space-y-3 mb-5">
            <div>
              <label class="label-field">Título <span class="text-red-500">*</span></label>
              <input v-model="createForm.title" type="text" class="field-input" placeholder="Ej: MiradasDoc 2025" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="label-field">Fecha inicio <span class="text-red-500">*</span></label>
                <input v-model="createForm.start_date" type="date" class="field-input" />
              </div>
              <div>
                <label class="label-field">Fecha fin <span class="text-slate-600">(opcional)</span></label>
                <input v-model="createForm.end_date" type="date" class="field-input" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="label-field">Tipo <span class="text-slate-600">(auto si vacío)</span></label>
                <select v-model="createForm.event_type" class="field-input">
                  <option value="">Detectar por fechas</option>
                  <option v-for="t in EVENT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </div>
              <div>
                <label class="label-field">Isla</label>
                <select v-model="createForm.island" class="field-input">
                  <option value="">Sin especificar</option>
                  <option v-for="i in ISLANDS" :key="i.code" :value="i.code">{{ i.label }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="label-field">Venue / Sala</label>
              <input v-model="createForm.venue" type="text" class="field-input" placeholder="Ej: Sala Guiniguada, Las Palmas" />
            </div>

            <div>
              <label class="label-field">Descripción</label>
              <textarea v-model="createForm.description" class="field-input resize-none" rows="2" placeholder="Breve descripción del evento" />
            </div>

            <div>
              <label class="label-field">URL imagen <span class="text-slate-600">(opcional)</span></label>
              <input v-model="createForm.image_url" type="url" class="field-input" placeholder="https://… (miniatura en la agenda)" />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="label-field">URL entradas <span class="text-slate-600">(opcional)</span></label>
                <input v-model="createForm.ticket_url" type="url" class="field-input" placeholder="https://…" />
              </div>
              <div>
                <label class="label-field">URL fuente <span class="text-slate-600">(opcional)</span></label>
                <input v-model="createForm.source_url" type="url" class="field-input" placeholder="https://…" />
              </div>
            </div>
          </div>

          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <button
                class="btn-confirm flex-1"
                :disabled="isCreating || !createForm.title || !createForm.start_date"
                @click="createEvent('confirmed')"
              >
                <svg v-if="isCreating" class="spinner" viewBox="0 0 24 24" fill="none">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                </svg>
                {{ isCreating ? 'Guardando…' : 'Crear y publicar en agenda' }}
              </button>
              <button class="action-btn preview" @click="closeCreateModal">Cancelar</button>
            </div>
            <button
              class="btn-draft w-full"
              :disabled="isCreating || !createForm.title || !createForm.start_date"
              @click="createEvent('pending')"
            >Guardar como borrador</button>
          </div>

        </div>
      </div>
    </Transition>

    <!-- ── Modal de edición ──────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showModal && selectedEvent" class="modal-overlay" @click.self="closeModal">
        <div class="modal-box">

          <!-- Cabecera del modal -->
          <div class="flex items-start justify-between gap-4 mb-5">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                  {{ selectedEvent.source?.name ?? 'Fuente desconocida' }}
                </span>
                <span
                  v-if="selectedEvent.ai_confidence != null"
                  class="px-2 py-0.5 rounded-md text-[10px] font-bold border"
                  :class="confidenceColor(selectedEvent.ai_confidence)"
                >IA {{ Math.round(selectedEvent.ai_confidence * 100) }}%</span>
              </div>
              <h2 class="text-base font-black text-white leading-snug">
                {{ selectedEvent.title }}
              </h2>
            </div>
            <button class="flex-shrink-0 text-slate-600 hover:text-slate-300 transition-colors mt-0.5" @click="closeModal">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Formulario de edición -->
          <div class="space-y-3 mb-5">
            <div>
              <label class="label-field">Título</label>
              <input v-model="editForm.title" type="text" class="field-input" placeholder="Título del evento" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="label-field">Fecha inicio</label>
                <input v-model="editForm.start_date" type="date" class="field-input" />
              </div>
              <div>
                <label class="label-field">Fecha fin <span class="text-slate-600">(opcional)</span></label>
                <input v-model="editForm.end_date" type="date" class="field-input" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="label-field">Tipo de evento</label>
                <select v-model="editForm.event_type" class="field-input">
                  <option v-for="t in EVENT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
              </div>
              <div>
                <label class="label-field">Isla</label>
                <select v-model="editForm.island" class="field-input">
                  <option value="">Sin especificar</option>
                  <option v-for="i in ISLANDS" :key="i.code" :value="i.code">{{ i.label }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="label-field">Venue / Sala</label>
              <input v-model="editForm.venue" type="text" class="field-input" placeholder="Ej: Sala Guiniguada" />
            </div>

            <div>
              <label class="label-field">Descripción</label>
              <textarea v-model="editForm.description" class="field-input resize-none" rows="3" placeholder="Descripción breve del evento" />
            </div>

            <div>
              <label class="label-field">URL de entradas <span class="text-slate-600">(opcional)</span></label>
              <input v-model="editForm.ticket_url" type="url" class="field-input" placeholder="https://…" />
            </div>

            <div>
              <label class="label-field">URL imagen <span class="text-slate-600">(opcional)</span></label>
              <input v-model="editForm.image_url" type="url" class="field-input" placeholder="https://… (miniatura en la agenda)" />
            </div>
          </div>

          <!-- Raw text (colapsado) -->
          <details class="mb-5 group">
            <summary class="label-section cursor-pointer hover:text-slate-300 transition-colors list-none flex items-center gap-1.5">
              <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
              Texto bruto scrapeado
            </summary>
            <p class="text-xs text-slate-500 leading-relaxed mt-2 max-h-32 overflow-y-auto pr-1 whitespace-pre-wrap">
              {{ selectedEvent.raw_text || 'Sin contenido guardado.' }}
            </p>
          </details>

          <!-- Acciones del modal -->
          <div class="flex items-center gap-2 pt-1 flex-wrap">
            <button
              class="btn-confirm flex-1"
              :disabled="isSaving"
              @click="saveEdit"
            >
              <svg v-if="isSaving" class="spinner" viewBox="0 0 24 24" fill="none">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
              </svg>
              {{ isSaving ? 'Guardando…' : 'Guardar cambios' }}
            </button>

            <button
              v-if="selectedEvent.status !== 'confirmed'"
              class="action-btn confirm"
              :disabled="isActioning"
              @click="quickStatus(selectedEvent, 'confirmed'); closeModal()"
            >Confirmar</button>

            <button
              class="action-btn reject"
              :disabled="isActioning"
              @click="deleteEvent(selectedEvent)"
            >Eliminar</button>
          </div>

        </div>
      </div>
    </Transition>

    <!-- ── Toast ──────────────────────────────────────────────────────── -->
    <Transition name="toast">
      <div
        v-if="toastMsg"
        class="fixed bottom-6 right-4 sm:right-6 z-50 px-4 py-2.5 rounded-xl text-xs font-bold shadow-xl max-w-[calc(100vw-2rem)]"
        :class="{
          'bg-red-900 text-red-200':         toastType === 'error',
          'bg-slate-800 text-slate-300':     toastType === 'info',
          'bg-emerald-900 text-emerald-200': toastType === 'success',
        }"
      >{{ toastMsg }}</div>
    </Transition>

  </div>
</template>

<style scoped>
.content-wrap { width: 100%; margin-left: auto; margin-right: auto; }

/* ── Spinner ────────────────────────────────────────────────── */
.spinner {
  width: 13px; height: 13px;
  animation: spin 0.7s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Descripción ────────────────────────────────────────────── */
.section-desc {
  font-size: 12px;
  color: #64748b;
  line-height: 1.6;
  max-width: 680px;
}

/* ── Header action buttons ──────────────────────────────────── */
.header-action-btn {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  padding: 5px 12px; border-radius: 7px; border: 1px solid transparent;
  transition: all 150ms; cursor: pointer; white-space: nowrap;
}
.header-action-btn.new-event { color: #f8fafc; background: rgb(30 41 59/0.9); border-color: rgb(71 85 105/0.6); }
.header-action-btn.new-event:hover { background: #1e293b; border-color: #475569; }
.header-action-btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* ── Filtros ────────────────────────────────────────────────── */
.filter-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
.filter-select {
  background: rgb(15 23 42/0.8); border: 1px solid rgb(51 65 85/0.6); border-radius: 8px;
  color: #94a3b8; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
  padding: 6px 10px; outline: none; transition: border-color 150ms;
  flex: 1 1 auto; min-width: 120px; max-width: 190px;
}
@media (max-width: 479px) { .filter-select { max-width: 100%; flex: 1 1 calc(50% - 4px); } }
.filter-select:focus { border-color: #475569; color: #e2e8f0; }
.btn-ghost {
  display: inline-flex; align-items: center; gap: 5px;
  border: 1px solid rgb(51 65 85/0.6); border-radius: 8px; color: #64748b;
  font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;
  padding: 6px 12px; transition: all 150ms; cursor: pointer; white-space: nowrap;
}
.btn-ghost:hover { color: #e2e8f0; border-color: #475569; background: #1e293b; }

/* ── Cards ──────────────────────────────────────────────────── */
.event-card {
  background: rgb(15 23 42/0.6); border: 1px solid rgb(51 65 85/0.4);
  border-radius: 12px; padding: 14px 16px;
  transition: border-color 150ms, background 150ms;
}
.event-card:hover { border-color: rgb(71 85 105/0.7); background: rgb(15 23 42/0.85); }
.event-card--review { border-color: rgb(180 83 9/0.4); background: rgb(120 53 15/0.05); }
.event-card--review:hover { border-color: rgb(217 119 6/0.5); }

/* ── Badges ─────────────────────────────────────────────────── */
.badge-type {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  padding: 2px 6px; border-radius: 5px; white-space: nowrap;
  background: rgb(51 65 85/0.5); color: #94a3b8;
}
.badge-type--festival   { background: rgb(76 29 149/0.35); color: #c4b5fd; }
.badge-type--projection { background: rgb(12 74 110/0.35); color: #7dd3fc; }
.badge-type--cycle      { background: rgb(6 78 59/0.3);    color: #6ee7b7; }
.badge-type--workshop   { background: rgb(120 53 15/0.35); color: #fcd34d; }

.badge-island {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  padding: 2px 6px; border-radius: 5px;
  background: rgb(30 41 59/0.8); color: #64748b; border: 1px solid rgb(51 65 85/0.5);
  white-space: nowrap;
}

.badge-status {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  padding: 2px 6px; border-radius: 5px; white-space: nowrap;
}
.badge-status--confirmed    { background: rgb(6 78 59/0.35);   color: #6ee7b7; }
.badge-status--rejected     { background: rgb(127 29 29/0.35); color: #fca5a5; }
.badge-status--needs_review { background: rgb(120 53 15/0.35); color: #fcd34d; }

/* ── Acciones ────────────────────────────────────────────────── */
.action-btn {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  padding: 5px 10px; border-radius: 6px; transition: all 150ms; cursor: pointer;
  white-space: nowrap; display: inline-flex; align-items: center;
}
.action-btn.confirm       { background: rgb(6 78 59/0.35);   color: #6ee7b7; }
.action-btn.confirm:hover { background: rgb(6 78 59/0.6); }
.action-btn.reject        { background: rgb(127 29 29/0.35); color: #fca5a5; }
.action-btn.reject:hover  { background: rgb(127 29 29/0.6); }
.action-btn.preview       { background: rgb(51 65 85/0.4);   color: #94a3b8; }
.action-btn.preview:hover { background: rgb(71 85 105/0.5);  color: #cbd5e1; }
.action-btn:disabled      { opacity: 0.4; cursor: not-allowed; }
.action-link {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  color: #475569; transition: color 150ms; white-space: nowrap;
}
.action-link:hover { color: #94a3b8; }

/* ── Paginación ─────────────────────────────────────────────── */
.pagination-btn {
  min-width: 32px; height: 32px; border-radius: 6px;
  background: #1e293b; border: 1px solid #334155;
  color: #64748b; font-size: 12px; font-weight: 700; transition: all 150ms;
}
.pagination-btn:hover, .pagination-btn.active { background: #334155; color: #e2e8f0; }

/* ── Modal ──────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0; background: rgb(0 0 0/0.78);
  backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
  z-index: 50; display: flex; align-items: center; justify-content: center; padding: 16px;
}
.modal-box {
  background: #0f172a; border: 1px solid #1e293b; border-radius: 16px;
  padding: 20px; width: 100%; max-width: 560px; max-height: 92vh; overflow-y: auto;
}

/* ── Campos del formulario ──────────────────────────────────── */
.label-field {
  display: block; font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.07em; color: #475569; margin-bottom: 4px;
}
.label-section {
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.07em; color: #475569;
}
.field-input {
  width: 100%; background: rgb(30 41 59/0.7); border: 1px solid rgb(51 65 85/0.7);
  border-radius: 8px; color: #e2e8f0; font-size: 12px;
  padding: 8px 10px; outline: none; transition: border-color 150ms;
}
.field-input:focus { border-color: #475569; }
.field-input option { background: #1e293b; }

/* ── Botones guardar ────────────────────────────────────────── */
.btn-draft {
  display: inline-flex; align-items: center; justify-content: center;
  background: transparent; color: #64748b; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.06em;
  padding: 8px 20px; border-radius: 8px;
  border: 1px solid rgb(51 65 85 / 0.5);
  transition: all 150ms; cursor: pointer;
}
.btn-draft:hover:not(:disabled) { color: #94a3b8; border-color: #475569; background: rgb(30 41 59 / 0.4); }
.btn-draft:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-confirm {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  background: #13c090; color: #fff; font-size: 11px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.06em;
  padding: 10px 20px; border-radius: 8px;
  box-shadow: 0 2px 12px rgb(19 192 144/0.3);
  transition: all 150ms; cursor: pointer;
}
.btn-confirm:hover:not(:disabled) { background: #0fa87c; }
.btn-confirm:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── Transiciones ───────────────────────────────────────────── */
.modal-enter-active, .modal-leave-active { transition: opacity 200ms; }
.modal-enter-from,   .modal-leave-to     { opacity: 0; }
.toast-enter-active, .toast-leave-active { transition: all 250ms ease; }
.toast-enter-from,   .toast-leave-to     { opacity: 0; transform: translateY(8px); }

/* ── Panel de fuentes ───────────────────────────────────────── */
.sources-panel {
  background: rgb(15 23 42 / 0.4);
  border: 1px solid rgb(51 65 85 / 0.35);
  border-radius: 12px;
  overflow: hidden;
}
.sources-summary {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; cursor: pointer; user-select: none; list-style: none;
}
.sources-summary::-webkit-details-marker { display: none; }
.sources-summary:hover { background: rgb(30 41 59 / 0.3); }
.sources-chevron {
  width: 12px; height: 12px; color: #475569;
  transition: transform 150ms; flex-shrink: 0;
}
details[open] .sources-chevron { transform: rotate(90deg); }
.sources-empty { padding: 16px 14px; border-top: 1px solid rgb(51 65 85 / 0.3); }
.sources-list  { border-top: 1px solid rgb(51 65 85 / 0.3); }
.source-row {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 14px; border-bottom: 1px solid rgb(51 65 85 / 0.2);
  transition: background 150ms;
}
.source-row:last-child { border-bottom: none; }
.source-row:hover      { background: rgb(30 41 59 / 0.3); }
.source-type {
  flex-shrink: 0;
  font-size: 9px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.08em;
  padding: 2px 6px; border-radius: 4px;
}
.source-type--rss    { background: rgb(6 78 59 / 0.35);   color: #6ee7b7; }
.source-type--scrape { background: rgb(30 58 138 / 0.35); color: #93c5fd; }
.source-name-block {
  flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 1px;
}
.source-name {
  font-size: 11px; font-weight: 600; color: #cbd5e1;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.source-url {
  font-size: 9px; color: #475569;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color 150ms;
}
.source-url:hover { color: #64748b; }



.src-edit-input {
  display: block; width: 100%;
  background: rgb(30 41 59 / 0.8); border: 1px solid rgb(99 102 241 / 0.4);
  border-radius: 4px; color: #e2e8f0; font-size: 11px;
  padding: 3px 7px; outline: none; transition: border-color 150ms;
}
.src-edit-input:focus { border-color: #6366f1; }

.btn-src-edit {
  flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
  width: 24px; height: 24px; border-radius: 5px;
  color: #475569; border: 1px solid rgb(51 65 85 / 0.4);
  transition: all 150ms; cursor: pointer;
}
.btn-src-edit:hover { color: #94a3b8; border-color: #475569; background: #1e293b; }

.btn-src-save {
  font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  padding: 3px 9px; border-radius: 5px;
  color: #6ee7b7; background: rgb(6 78 59 / 0.3); border: 1px solid rgb(6 78 59 / 0.4);
  transition: all 150ms; cursor: pointer; white-space: nowrap;
}
.btn-src-save:hover:not(:disabled) { background: rgb(6 78 59 / 0.5); }
.btn-src-save:disabled { opacity: 0.4; cursor: not-allowed; }

.btn-src-cancel {
  font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
  padding: 3px 8px; border-radius: 5px;
  color: #64748b; border: 1px solid rgb(51 65 85 / 0.4);
  transition: all 150ms; cursor: pointer; white-space: nowrap;
}
.btn-src-cancel:hover { color: #94a3b8; background: #1e293b; }
</style>
