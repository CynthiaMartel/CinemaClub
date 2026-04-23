<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()

const isEditor = computed(() => auth.user?.idRol === 1 || auth.user?.idRol === 2)

const sources    = ref([])
const isLoading  = ref(false)
const error      = ref(null)
const toastMsg   = ref('')
const toastType  = ref('success')
const checkingId = ref(null)
const togglingId = ref(null)
const editingId  = ref(null)
const editForm   = ref({ name: '', url: '' })
const isSaving   = ref(false)

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
  : 'Nunca'

const typeLabel = (t) => ({ rss: 'RSS', scraping: 'Scraping', sitemap: 'Sitemap' }[t] ?? t)
const typeColor = (t) => ({
  rss:      'bg-blue-900/40 text-blue-300',
  scraping: 'bg-purple-900/40 text-purple-300',
  sitemap:  'bg-teal-900/40 text-teal-300',
}[t] ?? 'bg-slate-700 text-slate-400')

const typeTooltip = (t) => ({
  rss:      'Feed RSS/Atom — se parsea el XML directamente, más fiable y rápido',
  scraping: 'Scraping HTML — se descarga la página y se extraen los artículos con selectores CSS',
  sitemap:  'Sitemap XML — se recorre el mapa del sitio para encontrar URLs nuevas',
}[t] ?? t)

const showToast = (msg, type = 'success') => {
  toastMsg.value  = msg
  toastType.value = type
  setTimeout(() => { toastMsg.value = '' }, 3500)
}

const fetchSources = async () => {
  isLoading.value = true
  error.value     = null
  try {
    const { data } = await api.get('/editorial/sources')
    sources.value = data.data ?? []
  } catch {
    error.value = 'No se pudo cargar la lista de fuentes.'
  } finally {
    isLoading.value = false
  }
}

const checkNow = async (source) => {
  checkingId.value = source.id
  try {
    const { data } = await api.post(`/editorial/sources/${source.id}/check-now`, {}, { timeout: 65000 })
    showToast(data.message)
    await fetchSources()
  } catch {
    showToast('Error al rastrear la fuente.', 'error')
  } finally {
    checkingId.value = null
  }
}

const startEdit = (source) => {
  editingId.value = source.id
  editForm.value  = { name: source.name, url: source.url }
}

const cancelEdit = () => {
  editingId.value = null
  editForm.value  = { name: '', url: '' }
}

const saveEdit = async (source) => {
  isSaving.value = true
  try {
    const { data } = await api.patch(`/editorial/sources/${source.id}`, editForm.value)
    source.name = data.data.name
    source.url  = data.data.url
    showToast('Fuente actualizada.')
    cancelEdit()
  } catch {
    showToast('Error al guardar los cambios.', 'error')
  } finally {
    isSaving.value = false
  }
}

const toggleSource = async (source) => {
  togglingId.value = source.id
  try {
    const { data } = await api.patch(`/editorial/sources/${source.id}/toggle`)
    source.is_active = data.is_active
    showToast(data.message)
  } catch {
    showToast('Error al cambiar el estado.', 'error')
  } finally {
    togglingId.value = null
  }
}

onMounted(() => {
  if (!isEditor.value) { router.push({ name: 'home' }); return }
  fetchSources()
})
</script>

<template>
  <div class="content-wrap mx-auto max-w-[1100px] px-6 md:px-10 lg:px-0 py-10">

    <!-- ── Header ─────────────────────────────────────────────────────── -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-8">
      <div>
        <h1 class="text-xl sm:text-2xl font-black uppercase tracking-wider text-white">Fuentes de noticias</h1>
        <p class="text-[10px] text-slate-500 mt-1 uppercase tracking-widest">Cine canario — rastreo automático</p>
      </div>
      <button
        class="text-[10px] font-bold uppercase tracking-wider text-slate-400 hover:text-white transition-colors whitespace-nowrap self-start"
        @click="router.push({ name: 'editorial-inbox' })"
      >
        ← Inbox editorial
      </button>
    </div>

    <!-- ── Descripción ───────────────────────────────────────────────── -->
    <p class="section-desc mb-8">
      Webs y feeds RSS que el sistema rastrea periódicamente para captar noticias de cine canario.
      Cada fuente alimenta el <button class="inline-link" @click="router.push({ name: 'editorial-inbox' })">Inbox editorial</button>,
      donde la IA las analiza y el editor decide qué publicar.
    </p>

    <!-- ── Loading / error ───────────────────────────────────────────── -->
    <div v-if="isLoading" class="flex justify-center py-20">
      <svg class="w-7 h-7 text-slate-600" style="animation: spin 0.7s linear infinite" viewBox="0 0 24 24" fill="none">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
      </svg>
    </div>
    <div v-else-if="error" class="text-center py-16 text-red-400 text-sm">{{ error }}</div>

    <!-- ── Tabla de fuentes ──────────────────────────────────────────── -->
    <div v-else class="sources-table-wrap">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-[10px] font-bold uppercase tracking-widest text-slate-500 border-b border-slate-800">
            <th class="text-left pb-3 pr-4">Fuente</th>
            <th
              class="text-left pb-3 pr-4"
              title="RSS: feed XML directo (más fiable). Scraping: extracción de HTML con selectores."
            >Tipo</th>
            <th class="text-left pb-3 pr-4 hidden md:table-cell">Último rastreo</th>
            <th
              class="text-center pb-3 pr-4 hidden sm:table-cell cursor-help"
              title="Noticias nuevas encontradas en esta fuente durante las últimas 24 horas"
            >Hoy</th>
            <th
              class="text-center pb-3 pr-4 hidden sm:table-cell cursor-help"
              title="Total de noticias acumuladas desde que se activó esta fuente (todas las fechas)"
            >Total</th>
            <th
              class="text-center pb-3 pr-4 cursor-help"
              title="Activa: se rastrea automáticamente. Pausada: desactivada manualmente. Revisar: ha fallado 3 veces seguidas."
            >Estado</th>
            <th class="text-right pb-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="source in sources"
            :key="source.id"
            class="border-b border-slate-800/60 hover:bg-slate-800/20 transition-colors"
            :class="source.needs_review ? 'bg-amber-950/10' : ''"
          >
            <!-- Nombre + fallos + URL -->
            <td class="py-3 pr-4">
              <!-- Modo edición inline -->
              <template v-if="editingId === source.id">
                <input
                  v-model="editForm.name"
                  class="edit-inline-input mb-1"
                  placeholder="Nombre"
                />
                <input
                  v-model="editForm.url"
                  class="edit-inline-input"
                  placeholder="https://…"
                  @keydown.enter="saveEdit(source)"
                  @keydown.escape="cancelEdit"
                />
              </template>
              <!-- Modo lectura -->
              <template v-else>
                <div class="flex items-center gap-2 flex-wrap">
                  <p class="font-semibold text-xs leading-snug" :class="source.needs_review ? 'text-amber-400' : 'text-slate-200'">
                    {{ source.name }}
                  </p>
                  <span
                    v-if="source.needs_review"
                    class="pill-error"
                    :title="source.last_error
                      ? `Último error: ${source.last_error}`
                      : 'Falló 3 veces seguidas. La fuente se pausó automáticamente. Comprueba la URL o actívala manualmente para reintentar.'"
                  >
                    {{ source.failed_attempts }}✕ fallo
                  </span>
                </div>
                <a
                  :href="source.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-[10px] text-slate-600 hover:text-slate-400 transition-colors truncate block max-w-[220px]"
                  :title="source.url"
                >{{ source.url }}</a>
              </template>
            </td>

            <!-- Tipo -->
            <td class="py-3 pr-4">
              <span
                class="badge-type"
                :class="typeColor(source.type)"
                :title="typeTooltip(source.type)"
              >{{ typeLabel(source.type) }}</span>
            </td>

            <!-- Último rastreo -->
            <td
              class="py-3 pr-4 hidden md:table-cell text-[11px] text-slate-500"
              :title="source.last_checked_at ? `Última comprobación: ${fmtDate(source.last_checked_at)}` : 'Nunca se ha rastreado esta fuente'"
            >{{ fmtDate(source.last_checked_at) }}</td>

            <!-- Items hoy -->
            <td
              class="py-3 pr-4 text-center hidden sm:table-cell"
              title="Noticias nuevas encontradas en las últimas 24 h"
            >
              <span
                class="text-xs font-bold"
                :class="source.items_today > 0 ? 'text-emerald-400' : 'text-slate-600'"
              >{{ source.items_today }}</span>
            </td>

            <!-- Total items -->
            <td
              class="py-3 pr-4 text-center hidden sm:table-cell text-xs text-slate-500"
              title="Total acumulado de noticias recogidas desde que se activó la fuente"
            >{{ source.total_items }}</td>

            <!-- Estado -->
            <td class="py-3 pr-4 text-center">
              <span
                v-if="source.needs_review"
                class="status-badge needs-review"
                :title="source.last_error
                  ? `Requiere revisión — falló ${source.failed_attempts} veces seguidas. Último error: ${source.last_error}`
                  : `Requiere revisión — falló ${source.failed_attempts} veces seguidas. Comprueba que la URL siga activa.`"
              >Revisar</span>
              <button
                v-else
                class="status-badge"
                :class="source.is_active ? 'active' : 'paused'"
                :disabled="togglingId === source.id"
                :title="source.is_active
                  ? 'Activa — se rastrea automáticamente cada pocas horas. Pulsa para pausarla.'
                  : 'Pausada — no se rastrea automáticamente. Pulsa para activarla.'"
                @click="toggleSource(source)"
              >{{ source.is_active ? 'Activa' : 'Pausada' }}</button>
            </td>

            <!-- Acciones -->
            <td class="py-3 text-right">
              <div class="flex items-center justify-end gap-1.5">
                <!-- Guardar / Cancelar cuando está en modo edición -->
                <template v-if="editingId === source.id">
                  <button class="action-save" :disabled="isSaving" @click="saveEdit(source)">
                    {{ isSaving ? '…' : 'Guardar' }}
                  </button>
                  <button class="action-cancel" @click="cancelEdit">Cancelar</button>
                </template>
                <!-- Acciones normales -->
                <template v-else>
                  <button
                    class="action-edit"
                    title="Editar nombre y URL de esta fuente"
                    @click="startEdit(source)"
                  >
                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/>
                    </svg>
                    Editar
                  </button>
                  <button
                    class="action-check inline-flex items-center gap-1.5"
                    :disabled="checkingId === source.id"
                    :title="source.needs_review
                      ? 'Forzar un rastreo ahora para comprobar si la fuente ha vuelto a funcionar'
                      : 'Rastrear esta fuente ahora mismo, sin esperar al ciclo automático'"
                    @click="checkNow(source)"
                  >
                    <svg v-if="checkingId === source.id" class="spinner" viewBox="0 0 24 24" fill="none">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                    </svg>
                    {{ checkingId === source.id ? 'Rastreando…' : 'Rastrear' }}
                  </button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ── Toast ──────────────────────────────────────────────────────── -->
    <Transition name="toast">
      <div
        v-if="toastMsg"
        class="fixed bottom-6 right-4 sm:right-6 z-50 px-4 py-2.5 rounded-xl text-xs font-bold shadow-lg max-w-[calc(100vw-2rem)]"
        :class="toastType === 'error' ? 'bg-red-900 text-red-200' : 'bg-emerald-900 text-emerald-200'"
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
  width: 11px;
  height: 11px;
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

/* ── Tabla ──────────────────────────────────────────────────── */
.sources-table-wrap {
  background: rgb(15 23 42 / 0.6);
  border: 1px solid rgb(51 65 85 / 0.4);
  border-radius: 14px;
  padding: 20px 24px;
  overflow-x: auto;
}

/* ── Badges ─────────────────────────────────────────────────── */
.badge-type {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  padding: 2px 8px;
  border-radius: 5px;
  white-space: nowrap;
  cursor: help;
}

.pill-error {
  font-size: 9px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 6px;
  border-radius: 4px;
  background: rgb(120 53 15 / 0.4);
  color: #fbbf24;
  border: 1px solid rgb(146 64 14 / 0.4);
  cursor: help;
  white-space: nowrap;
}

/* ── Estado ─────────────────────────────────────────────────── */
.status-badge {
  display: inline-block;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 3px 10px;
  border-radius: 6px;
  transition: all 150ms;
  white-space: nowrap;
}
button.status-badge { cursor: pointer; }
button.status-badge:disabled { opacity: 0.4; cursor: not-allowed; }

.status-badge.active {
  background: rgb(6 78 59 / 0.35);
  color: #6ee7b7;
}
button.status-badge.active:hover { background: rgb(6 78 59 / 0.6); }

.status-badge.paused {
  background: rgb(51 65 85 / 0.4);
  color: #64748b;
}
button.status-badge.paused:hover { background: rgb(71 85 105 / 0.5); color: #94a3b8; }

.status-badge.needs-review {
  background: rgb(120 53 15 / 0.35);
  color: #fbbf24;
  cursor: help;
}

/* ── Botón rastrear ahora ────────────────────────────────────── */
.action-check {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: #475569;
  padding: 3px 10px;
  border-radius: 6px;
  border: 1px solid rgb(51 65 85 / 0.4);
  transition: all 150ms;
  cursor: pointer;
  white-space: nowrap;
}
.action-check:hover:not(:disabled) { color: #e2e8f0; border-color: #475569; background: #1e293b; }
.action-check:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Edición inline ──────────────────────────────────────────── */
.edit-inline-input {
  display: block; width: 100%;
  background: rgb(30 41 59 / 0.8); border: 1px solid rgb(99 102 241 / 0.4);
  border-radius: 5px; color: #e2e8f0; font-size: 11px;
  padding: 4px 8px; outline: none; transition: border-color 150ms;
}
.edit-inline-input:focus { border-color: #6366f1; }

.action-edit {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  color: #475569; padding: 3px 8px; border-radius: 5px;
  border: 1px solid rgb(51 65 85 / 0.4); transition: all 150ms; cursor: pointer;
  white-space: nowrap;
}
.action-edit:hover { color: #94a3b8; border-color: #475569; background: #1e293b; }

.action-save {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  color: #6ee7b7; padding: 3px 10px; border-radius: 5px;
  background: rgb(6 78 59 / 0.3); border: 1px solid rgb(6 78 59 / 0.4);
  transition: all 150ms; cursor: pointer; white-space: nowrap;
}
.action-save:hover:not(:disabled) { background: rgb(6 78 59 / 0.5); }
.action-save:disabled { opacity: 0.4; cursor: not-allowed; }

.action-cancel {
  font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
  color: #64748b; padding: 3px 8px; border-radius: 5px;
  border: 1px solid rgb(51 65 85 / 0.4); transition: all 150ms; cursor: pointer;
  white-space: nowrap;
}
.action-cancel:hover { color: #94a3b8; background: #1e293b; }

/* ── Toast ──────────────────────────────────────────────────── */
.toast-enter-active, .toast-leave-active { transition: all 250ms ease; }
.toast-enter-from, .toast-leave-to       { opacity: 0; transform: translateY(8px); }
</style>
