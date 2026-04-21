<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()

const isEditor = computed(() => auth.user?.idRol === 1 || auth.user?.idRol === 2)

// ─── Estado ───────────────────────────────────────────────────────────────
const items          = ref([])
const sources        = ref([])
const pagination     = ref(null)
const isLoading      = ref(false)
const error          = ref(null)
const isCheckingAll  = ref(false)
const isProcessingAI = ref(false)
const isUpdatingStatus = ref(false)

// Filtros — por defecto todo abierto
const filters = ref({
  status:    '',
  source_id: '',
  category:  '',
  min_score: '',
  sort:      'fecha_desc',
  page:      1,
})

// Modal de preview
const selectedItem = ref(null)
const showDetail   = ref(false)

// Toast
const toastMsg  = ref('')
const toastType = ref('success')

// ─── Helpers ──────────────────────────────────────────────────────────────
const CATEGORIES = ['festival', 'produccion', 'estreno', 'convocatoria', 'otro']

const scoreColor = (score) => {
  if (score == null) return 'bg-slate-700/60 text-slate-500 border-slate-700/40'
  if (score >= 8)    return 'bg-emerald-900/60 text-emerald-300 border-emerald-800/40'
  if (score >= 5)    return 'bg-amber-900/60 text-amber-300 border-amber-800/40'
  return 'bg-slate-700/60 text-slate-400 border-slate-600/40'
}

const categoryLabel = (cat) => ({
  festival:     'Festival',
  produccion:   'Producción',
  estreno:      'Estreno',
  convocatoria: 'Convocatoria',
  otro:         'Otro',
}[cat] ?? cat ?? '—')

const statusLabel = (s) => ({
  pending:  'Pendiente',
  approved: 'Aprobado',
  rejected: 'Rechazado',
  drafted:  'En borrador',
}[s] ?? s)

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
  : '—'

const showToast = (msg, type = 'success') => {
  toastMsg.value  = msg
  toastType.value = type
  setTimeout(() => { toastMsg.value = '' }, 3500)
}

// ─── Carga ────────────────────────────────────────────────────────────────
const fetchItems = async () => {
  isLoading.value = true
  error.value     = null
  try {
    const params = Object.fromEntries(
      Object.entries(filters.value).filter(([, v]) => v !== '' && v !== null)
    )
    const { data } = await api.get('/editorial/news-items', { params })
    items.value      = data.data?.data ?? []
    pagination.value = data.data
  } catch (e) {
    error.value = 'No se pudo cargar el inbox.'
    console.error(e)
  } finally {
    isLoading.value = false
  }
}

const fetchSources = async () => {
  try {
    const { data } = await api.get('/editorial/sources')
    sources.value = data.data ?? []
  } catch {/* silencioso */}
}

onMounted(() => {
  if (!isEditor.value) { router.push({ name: 'home' }); return }
  fetchItems()
  fetchSources()
})

watch([
  () => filters.value.status,
  () => filters.value.source_id,
  () => filters.value.category,
  () => filters.value.min_score,
  () => filters.value.sort,
], () => { filters.value.page = 1; fetchItems() })

// ─── Acciones ─────────────────────────────────────────────────────────────
const quickStatus = async (item, status) => {
  isUpdatingStatus.value = true
  try {
    await api.patch(`/editorial/news-items/${item.id}/status`, { status })
    item.status = status
    showToast(
      status === 'approved' ? 'Aprobado — aparecerá en el filtro "Aprobado"' :
      status === 'rejected' ? 'Rechazado — ocultado del flujo editorial' :
      'Estado actualizado'
    )
    if (filters.value.status && filters.value.status !== status) {
      items.value = items.value.filter(i => i.id !== item.id)
    }
  } catch {
    showToast('Error al actualizar el estado', 'error')
  } finally {
    isUpdatingStatus.value = false
  }
}

const checkAllSources = async () => {
  isCheckingAll.value = true
  try {
    const { data } = await api.post('/editorial/sources/check-all', {}, { timeout: 130000 })
    showToast(data.message, data.total_new > 0 ? 'success' : 'info')
    await fetchItems()
  } catch {
    showToast('Error al rastrear las fuentes', 'error')
  } finally {
    isCheckingAll.value = false
  }
}

const processAI = async () => {
  isProcessingAI.value = true
  try {
    const { data } = await api.post('/editorial/news-items/process-ai', {}, { timeout: 190000 })
    showToast(data.message, data.pending_left > 0 ? 'info' : 'success')
    await fetchItems()
  } catch {
    showToast('Error al procesar con IA', 'error')
  } finally {
    isProcessingAI.value = false
  }
}

const goToWrite = (item) => {
  router.push({ name: 'editorial-write', params: { id: item.id } })
}

const openDetail = (item) => {
  selectedItem.value = item
  showDetail.value   = true
}

const closeDetail = () => {
  showDetail.value   = false
  selectedItem.value = null
}

const goToPage = (page) => {
  filters.value.page = page
  fetchItems()
}
</script>

<template>
  <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10">

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-xl sm:text-2xl font-black uppercase tracking-wider text-white">Inbox editorial</h1>
        <p class="text-[10px] text-slate-500 mt-1 uppercase tracking-widest">Panel de asistente IA · cine canario</p>
      </div>
      <div class="flex items-center gap-2 sm:gap-3 flex-wrap">
        <button
          class="header-action-btn"
          :class="isCheckingAll ? 'loading' : 'scrape'"
          :disabled="isCheckingAll || isProcessingAI"
          title="Descarga las últimas noticias de todas las fuentes activas ahora mismo"
          @click="checkAllSources"
        >
          <svg v-if="isCheckingAll" class="spinner" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
          </svg>
          {{ isCheckingAll ? 'Rastreando…' : 'Rastrear fuentes' }}
        </button>
        <button
          class="header-action-btn"
          :class="isProcessingAI ? 'loading' : 'ai'"
          :disabled="isProcessingAI || isCheckingAll"
          title="Envía las noticias sin analizar a la IA para generar resumen, puntuación y etiquetas"
          @click="processAI"
        >
          <svg v-if="isProcessingAI" class="spinner" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
          </svg>
          {{ isProcessingAI ? 'Procesando IA…' : 'Procesar con IA' }}
        </button>
        <button
          class="text-[10px] font-bold uppercase tracking-wider text-slate-500 hover:text-white transition-colors whitespace-nowrap"
          @click="router.push({ name: 'editorial-sources' })"
        >
          Fuentes →
        </button>
      </div>
    </div>

    <!-- ── Descripción ───────────────────────────────────────────────── -->
    <p class="section-desc mb-6">
      Noticias recogidas automáticamente de las
      <button class="inline-link" @click="router.push({ name: 'editorial-sources' })">fuentes activas</button>
      y analizadas por IA. Filtra por relevancia, aprueba lo que te interese y abre cualquier noticia
      en el editor para escribir sobre ella.
    </p>

    <!-- ── Filtros ────────────────────────────────────────────────────── -->
    <div class="filter-bar mb-6">
      <select
        v-model="filters.sort"
        class="filter-select"
        title="Orden en que se muestran las noticias en el inbox"
      >
        <option value="fecha_desc">Más recientes</option>
        <option value="fecha_asc">Más antiguos</option>
        <option value="relevancia_desc">Mayor relevancia</option>
        <option value="relevancia_asc">Menor relevancia</option>
      </select>
      <select
        v-model="filters.status"
        class="filter-select"
        title="Filtra por estado editorial: Pendiente (sin revisar), Aprobado, Rechazado o En borrador (post ya creado)"
      >
        <option value="">Todos los estados</option>
        <option value="pending">Pendiente</option>
        <option value="approved">Aprobado</option>
        <option value="rejected">Rechazado</option>
        <option value="drafted">En borrador</option>
      </select>
      <select
        v-model="filters.source_id"
        class="filter-select"
        title="Filtra noticias por fuente de origen (RSS o scraping)"
      >
        <option value="">Todas las fuentes</option>
        <option v-for="s in sources" :key="s.id" :value="s.id">{{ s.name }}</option>
      </select>
      <select
        v-model="filters.category"
        class="filter-select"
        title="Categoría asignada por la IA: festival, producción, estreno, convocatoria u otro"
      >
        <option value="">Todas las categorías</option>
        <option v-for="c in CATEGORIES" :key="c" :value="c">{{ categoryLabel(c) }}</option>
      </select>
      <select
        v-model="filters.min_score"
        class="filter-select filter-select--ai"
        title="Puntuación de relevancia calculada por la IA (0-10). Requiere haber pulsado 'Procesar con IA' primero"
      >
        <option value="">Cualquier relevancia</option>
        <option value="5">Relevancia ≥ 5</option>
        <option value="7">Relevancia ≥ 7</option>
        <option value="9">Relevancia ≥ 9</option>
      </select>
      <button
        class="btn-ghost"
        title="Recarga el inbox con los filtros actuales"
        @click="fetchItems"
      >
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

    <div v-else-if="items.length === 0" class="py-20 flex flex-col items-center gap-5 text-center">
      <p class="text-slate-500 text-sm max-w-sm">
        {{ filters.status || filters.source_id || filters.category || filters.min_score
          ? 'No hay items con los filtros seleccionados. Prueba a ampliar los criterios.'
          : 'Aún no hay noticias. Pulsa "Rastrear fuentes" para obtener contenido ahora.' }}
      </p>
      <button
        class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider px-5 py-2.5 rounded-lg bg-[#13c090]/10 text-[#13c090] border border-[#13c090]/30 hover:bg-[#13c090]/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="isCheckingAll"
        @click="checkAllSources"
      >
        <svg v-if="isCheckingAll" class="spinner" viewBox="0 0 24 24" fill="none">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
        </svg>
        {{ isCheckingAll ? 'Rastreando…' : 'Rastrear ahora' }}
      </button>
    </div>

    <!-- ── Lista de items ─────────────────────────────────────────────── -->
    <div v-else class="flex flex-col gap-3">
      <article v-for="item in items" :key="item.id" class="news-card">

        <!-- Zona clickable → abre modal -->
        <div class="cursor-pointer" @click="openDetail(item)">
          <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <h2 class="text-sm font-bold text-white leading-snug line-clamp-2">
                {{ item.ai_suggested_title || item.title }}
              </h2>
              <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                <span class="text-[10px] text-slate-500 uppercase tracking-wider truncate max-w-[160px]">
                  {{ item.source?.name ?? '—' }}
                </span>
                <span class="text-slate-700">·</span>
                <span class="text-[10px] text-slate-500 whitespace-nowrap">{{ fmtDate(item.found_at) }}</span>
                <span v-if="item.ai_category" class="badge-category">{{ categoryLabel(item.ai_category) }}</span>
                <span v-if="item.status !== 'pending'" class="badge-status" :class="`badge-status--${item.status}`">
                  {{ statusLabel(item.status) }}
                </span>
              </div>
            </div>
            <!-- Score badge -->
            <div
              class="flex-shrink-0 flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 rounded-xl text-sm font-black border"
              :class="scoreColor(item.ai_relevance_score)"
            >
              {{ item.ai_relevance_score ?? '—' }}
            </div>
          </div>

          <p v-if="item.ai_summary" class="text-xs text-slate-400 mt-2.5 line-clamp-2 leading-relaxed">
            {{ item.ai_summary }}
          </p>
          <p v-else class="text-xs text-slate-600 mt-2 italic">Sin procesar por IA aún…</p>

          <div v-if="item.ai_tags?.length" class="flex flex-wrap gap-1.5 mt-2">
            <span v-for="tag in item.ai_tags" :key="tag" class="badge-tag"># {{ tag }}</span>
          </div>
        </div>

        <!-- ── Barra de acciones ─────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2 mt-3 pt-3 border-t border-slate-800/80">
          <button
            v-if="item.status !== 'approved'"
            class="action-btn approve"
            :disabled="isUpdatingStatus"
            title="Marca esta noticia como relevante para cubrirla"
            @click="quickStatus(item, 'approved')"
          >Aprobar</button>
          <button
            v-if="item.status !== 'rejected'"
            class="action-btn reject"
            :disabled="isUpdatingStatus"
            title="Descarta esta noticia del flujo editorial (recuperable filtrando por 'Rechazado')"
            @click="quickStatus(item, 'rejected')"
          >Rechazar</button>

          <div class="flex-1"></div>

          <a
            :href="item.original_url"
            target="_blank"
            rel="noopener noreferrer"
            class="action-link"
          >Fuente ↗</a>

          <!-- En mobile el card entero ya abre el modal, "Ajustar" solo en sm+ -->
          <button
            class="action-btn preview hidden sm:inline-flex"
            @click="openDetail(item)"
          >Ajustar</button>

          <button
            class="action-btn edit-primary"
            @click="goToWrite(item)"
          >
            <span class="hidden sm:inline">Escribir sobre esto →</span>
            <span class="sm:hidden">Escribir →</span>
          </button>
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

    <!-- ── Modal de preview ───────────────────────────────────────────── -->
    <Transition name="modal">
      <div v-if="showDetail && selectedItem" class="modal-overlay" @click.self="closeDetail">
        <div class="modal-box">
          <div class="flex items-start justify-between gap-4 mb-5">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">
                  {{ selectedItem.source?.name ?? '—' }}
                </span>
                <span class="text-slate-700 text-[10px]">·</span>
                <span class="text-[10px] text-slate-500">{{ fmtDate(selectedItem.found_at) }}</span>
                <span v-if="selectedItem.ai_category" class="badge-category">
                  {{ categoryLabel(selectedItem.ai_category) }}
                </span>
              </div>
              <h2 class="text-base font-black text-white leading-snug">
                {{ selectedItem.title }}
              </h2>
            </div>
            <button class="flex-shrink-0 text-slate-600 hover:text-slate-300 transition-colors mt-0.5" @click="closeDetail">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <!-- Score + tags + entidades -->
          <div class="flex items-start gap-3 mb-4 pb-4 border-b border-slate-800">
            <div
              class="flex items-center justify-center w-12 h-12 rounded-xl text-base font-black border flex-shrink-0"
              :class="scoreColor(selectedItem.ai_relevance_score)"
            >{{ selectedItem.ai_relevance_score ?? '—' }}</div>
            <div class="flex-1 min-w-0 space-y-1.5">
              <div v-if="selectedItem.ai_summary" class="text-xs text-slate-400 leading-relaxed">
                {{ selectedItem.ai_summary }}
              </div>
              <div v-if="selectedItem.ai_tags?.length" class="flex flex-wrap gap-1.5">
                <span v-for="tag in selectedItem.ai_tags" :key="tag" class="badge-tag"># {{ tag }}</span>
              </div>
              <div v-if="selectedItem.ai_canarian_entities?.length" class="flex flex-wrap gap-1.5">
                <span
                  v-for="e in selectedItem.ai_canarian_entities" :key="e"
                  class="px-2 py-0.5 rounded-md bg-indigo-900/40 text-indigo-300 text-[10px] font-medium"
                >{{ e }}</span>
              </div>
            </div>
          </div>

          <!-- Contenido raw colapsado -->
          <details class="mb-5 group">
            <summary class="label-section cursor-pointer hover:text-slate-300 transition-colors list-none flex items-center gap-1.5">
              <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
              Contenido original
            </summary>
            <p class="text-xs text-slate-400 leading-relaxed mt-2 max-h-40 overflow-y-auto pr-1">
              {{ selectedItem.raw_content || 'Sin contenido guardado.' }}
            </p>
          </details>

          <div class="flex items-center gap-3 pt-1">
            <button class="btn-editor flex-1" @click="closeDetail(); goToWrite(selectedItem)">
              Escribir sobre esto →
            </button>
            <a
              :href="selectedItem.original_url"
              target="_blank" rel="noopener noreferrer"
              class="text-xs text-slate-500 hover:text-slate-300 transition-colors whitespace-nowrap"
            >Ver fuente ↗</a>
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
/* ── Layout — igual que HomeView / PostDetailView / SearchView ── */
.content-wrap {
  width: 100%;
  margin-left: auto;
  margin-right: auto;
}

/* ── Spinner ────────────────────────────────────────────────── */
.spinner {
  width: 13px;
  height: 13px;
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
.inline-link {
  color: #94a3b8;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 2px;
  transition: color 150ms;
  cursor: pointer;
}
.inline-link:hover { color: #e2e8f0; }

/* ── Header action buttons ──────────────────────────────────── */
.header-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  padding: 5px 12px;
  border-radius: 7px;
  border: 1px solid transparent;
  transition: all 150ms;
  cursor: pointer;
  white-space: nowrap;
}
.header-action-btn.scrape {
  color: #13c090;
  border-color: rgb(19 192 144 / 0.3);
  background: rgb(19 192 144 / 0.07);
}
.header-action-btn.scrape:hover { background: rgb(19 192 144 / 0.15); border-color: rgb(19 192 144 / 0.5); }
.header-action-btn.ai {
  color: #818cf8;
  border-color: rgb(99 102 241 / 0.3);
  background: rgb(99 102 241 / 0.07);
}
.header-action-btn.ai:hover { background: rgb(99 102 241 / 0.15); border-color: rgb(99 102 241 / 0.5); }
.header-action-btn.loading { color: #475569; border-color: rgb(71 85 105 / 0.3); background: transparent; cursor: not-allowed; }
.header-action-btn:disabled { opacity: 0.6; cursor: not-allowed; }

/* ── Filtros ────────────────────────────────────────────────── */
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
}
.filter-select {
  background: rgb(15 23 42 / 0.8);
  border: 1px solid rgb(51 65 85 / 0.6);
  border-radius: 8px;
  color: #94a3b8;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 6px 10px;
  outline: none;
  transition: border-color 150ms;
  flex: 1 1 auto;
  min-width: 120px;
  max-width: 200px;
}
@media (max-width: 479px) {
  .filter-select { max-width: 100%; flex: 1 1 calc(50% - 4px); }
}
.filter-select:focus { border-color: #475569; color: #e2e8f0; }

/* Filtro de relevancia — tono indigo porque depende de la IA */
.filter-select--ai {
  border-color: rgb(99 102 241 / 0.4);
  color: #a5b4fc;
}
.filter-select--ai:focus {
  border-color: rgb(99 102 241 / 0.7);
  color: #e2e8f0;
}

.btn-ghost {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  border: 1px solid rgb(51 65 85 / 0.6);
  border-radius: 8px;
  color: #64748b;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 6px 12px;
  transition: all 150ms;
  cursor: pointer;
  white-space: nowrap;
}
.btn-ghost:hover { color: #e2e8f0; border-color: #475569; background: #1e293b; }

/* ── Cards ──────────────────────────────────────────────────── */
.news-card {
  background: rgb(15 23 42 / 0.6);
  border: 1px solid rgb(51 65 85 / 0.4);
  border-radius: 12px;
  padding: 14px 16px;
  transition: border-color 150ms, background 150ms;
}
.news-card:hover {
  border-color: rgb(71 85 105 / 0.7);
  background: rgb(15 23 42 / 0.85);
}

/* ── Badges ─────────────────────────────────────────────────── */
.badge-category {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 6px;
  border-radius: 5px;
  background: rgb(51 65 85 / 0.5);
  color: #94a3b8;
  white-space: nowrap;
}
.badge-tag {
  font-size: 10px;
  color: #64748b;
  padding: 1px 6px;
  border-radius: 4px;
  border: 1px solid rgb(51 65 85 / 0.4);
}
.badge-status {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 6px;
  border-radius: 5px;
  white-space: nowrap;
}
.badge-status--approved { background: rgb(6 78 59 / 0.35); color: #6ee7b7; }
.badge-status--rejected { background: rgb(127 29 29 / 0.35); color: #fca5a5; }
.badge-status--drafted  { background: rgb(30 58 138 / 0.35); color: #93c5fd; }

/* ── Barra de acciones ──────────────────────────────────────── */
.action-btn {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  padding: 5px 10px;
  border-radius: 6px;
  transition: all 150ms;
  cursor: pointer;
  white-space: nowrap;
  display: inline-flex;
  align-items: center;
}
.action-btn.approve       { background: rgb(6 78 59 / 0.35); color: #6ee7b7; }
.action-btn.approve:hover { background: rgb(6 78 59 / 0.6); }
.action-btn.reject        { background: rgb(127 29 29 / 0.35); color: #fca5a5; }
.action-btn.reject:hover  { background: rgb(127 29 29 / 0.6); }
.action-btn.preview       { background: rgb(51 65 85 / 0.4); color: #94a3b8; }
.action-btn.preview:hover { background: rgb(71 85 105 / 0.5); color: #cbd5e1; }
.action-btn:disabled      { opacity: 0.4; cursor: not-allowed; }

.action-btn.edit-primary {
  background: #13c090;
  color: #fff;
  font-size: 10px;
  padding: 5px 12px;
  box-shadow: 0 0 10px rgb(19 192 144 / 0.2);
}
.action-btn.edit-primary:hover:not(:disabled) {
  background: #0fa87c;
  box-shadow: 0 0 16px rgb(19 192 144 / 0.4);
}

.action-link {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #475569;
  transition: color 150ms;
  white-space: nowrap;
}
.action-link:hover { color: #94a3b8; }

/* ── Paginación ─────────────────────────────────────────────── */
.pagination-btn {
  min-width: 32px;
  height: 32px;
  border-radius: 6px;
  background: #1e293b;
  border: 1px solid #334155;
  color: #64748b;
  font-size: 12px;
  font-weight: 700;
  transition: all 150ms;
}
.pagination-btn:hover,
.pagination-btn.active { background: #334155; color: #e2e8f0; }

/* ── Modal ──────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgb(0 0 0 / 0.78);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px;
}
.modal-box {
  background: #0f172a;
  border: 1px solid #1e293b;
  border-radius: 16px;
  padding: 20px;
  width: 100%;
  max-width: 560px;
  max-height: 90vh;
  overflow-y: auto;
}

.label-section {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #475569;
}

.btn-editor {
  background: #13c090;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 10px 20px;
  border-radius: 8px;
  box-shadow: 0 2px 12px rgb(19 192 144 / 0.3);
  transition: all 150ms;
  cursor: pointer;
  text-align: center;
}
.btn-editor:hover { background: #0fa87c; }

/* ── Transiciones ───────────────────────────────────────────── */
.modal-enter-active, .modal-leave-active { transition: opacity 200ms; }
.modal-enter-from,   .modal-leave-to     { opacity: 0; }
.toast-enter-active, .toast-leave-active { transition: all 250ms ease; }
.toast-enter-from,   .toast-leave-to     { opacity: 0; transform: translateY(8px); }
</style>
