<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import { Ckeditor } from '@ckeditor/ckeditor5-vue'
import ClassicEditor from '@ckeditor/ckeditor5-build-classic'

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const isEditor = computed(() => auth.user?.idRol === 1 || auth.user?.idRol === 2)

// ─── Estado ───────────────────────────────────────────────────────────────
const newsItem   = ref(null)
const isLoading  = ref(true)
const isSaving   = ref(false)
const saveLabel  = ref('Guardar borrador')

// Formulario del post
const form = ref({
  title:      '',
  subtitle:   '',
  content:    '',
  visible:    false,
  editorName: '',
})

// CKEditor
const editor       = ClassicEditor
const editorConfig = {
  toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|', 'undo', 'redo'],
  placeholder: 'Escribe el cuerpo del post aquí…',
}

// ─── Helpers ──────────────────────────────────────────────────────────────
const categoryLabel = (cat) => ({
  festival: 'Festival', produccion: 'Producción', estreno: 'Estreno',
  convocatoria: 'Convocatoria', otro: 'Otro',
}[cat] ?? cat ?? '—')

const scoreColor = (score) => {
  if (score == null) return 'bg-slate-700 text-slate-400'
  if (score >= 8)    return 'bg-emerald-900/60 text-emerald-300'
  if (score >= 5)    return 'bg-amber-900/60 text-amber-300'
  return 'bg-slate-700/60 text-slate-400'
}

const fmtDate = (d) => d
  ? new Date(d).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' })
  : '—'

// ─── Carga del news item ──────────────────────────────────────────────────
onMounted(async () => {
  if (!isEditor.value) { router.push({ name: 'home' }); return }

  try {
    const { data } = await api.get(`/editorial/news-items/${route.params.id}`)
    newsItem.value = data.data

    // Pre-rellenar el formulario con los datos de la IA
    form.value.title      = newsItem.value.ai_suggested_title || newsItem.value.title || ''
    form.value.subtitle   = Array.isArray(newsItem.value.ai_tags)
      ? newsItem.value.ai_tags.join(', ')
      : ''
    form.value.content    = buildInitialContent(newsItem.value)
    form.value.editorName = auth.user?.name || ''
  } catch (e) {
    console.error(e)
    router.push({ name: 'editorial-inbox' })
  } finally {
    isLoading.value = false
  }
})

// Cierra el atajo de teclado al salir
const handleKey = (e) => {
  if ((e.metaKey || e.ctrlKey) && e.key === 's') {
    e.preventDefault()
    savePost(false)
  }
}
onMounted(() => document.addEventListener('keydown', handleKey))
onUnmounted(() => document.removeEventListener('keydown', handleKey))

// ─── Contenido inicial ────────────────────────────────────────────────────
function buildInitialContent(item) {
  const summary    = item.ai_summary || ''
  const sourceUrl  = item.original_url || '#'
  const sourceName = item.source?.name || 'fuente'

  const entitiesHtml = Array.isArray(item.ai_canarian_entities) && item.ai_canarian_entities.length
    ? `<p><strong>Entidades mencionadas:</strong> ${item.ai_canarian_entities.join(', ')}.</p>`
    : ''

  return [
    summary ? `<p>${summary}</p>` : '',
    entitiesHtml,
    `<p><em>Fuente: <a href="${sourceUrl}" target="_blank" rel="noopener noreferrer">${sourceName}</a></em></p>`,
  ].filter(Boolean).join('\n')
}

// ─── Guardar post ─────────────────────────────────────────────────────────
const savePost = async (publish = false) => {
  if (isSaving.value) return

  if (!form.value.title.trim()) {
    alert('El título es obligatorio.')
    return
  }
  if (!form.value.content.trim()) {
    alert('El contenido no puede estar vacío.')
    return
  }

  isSaving.value   = true
  saveLabel.value  = publish ? 'Publicando…' : 'Guardando…'

  try {
    const { data } = await api.post(`/editorial/news-items/${route.params.id}/create-draft`, {
      title:   form.value.title,
      summary: '',
      content: form.value.content,
      visible: publish,
    })

    // Actualizar el subtitle (tags) si hay
    if (form.value.subtitle) {
      await api.put(`/post-update/${data.post_id}`, {
        ...form.value,
        visible: publish,
      }).catch(() => null) // no crítico
    }

    saveLabel.value = publish ? '¡Publicado! ✓' : 'Guardado ✓'
    setTimeout(() => {
      router.push(publish
        ? { name: 'post-reed', params: { id: data.post_id } }
        : { name: 'editorial-inbox' }
      )
    }, 600)
  } catch (e) {
    saveLabel.value = 'Error al guardar'
    console.error(e)
    setTimeout(() => { saveLabel.value = publish ? 'Publicar' : 'Guardar borrador' }, 2000)
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div class="editorial-write min-h-screen bg-[#0d1117]">

    <!-- Loading -->
    <div v-if="isLoading" class="flex items-center justify-center h-screen">
      <div class="w-8 h-8 border-2 border-slate-700 border-t-[#13c090] rounded-full animate-spin"></div>
    </div>

    <div v-else class="flex h-screen overflow-hidden">

      <!-- ── Panel izquierdo: noticia de contexto ──────────────────────── -->
      <aside class="context-panel w-[38%] min-w-[300px] max-w-[480px] flex flex-col border-r border-slate-800 overflow-y-auto">

        <!-- Cabecera del panel -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-800 sticky top-0 bg-[#0d1117] z-10">
          <button
            class="text-[10px] font-bold uppercase tracking-wider text-slate-500 hover:text-white transition-colors"
            @click="router.push({ name: 'editorial-inbox' })"
          >
            ← Inbox
          </button>
          <span class="text-[10px] font-bold uppercase tracking-widest text-slate-600">Contexto</span>
        </div>

        <!-- Contenido de la noticia -->
        <div class="p-5 flex flex-col gap-5">

          <!-- Fuente y fecha -->
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
              {{ newsItem.source?.name ?? '—' }}
            </span>
            <span class="text-slate-700 text-xs">·</span>
            <span class="text-[10px] text-slate-500">{{ fmtDate(newsItem.found_at) }}</span>
            <a
              :href="newsItem.original_url"
              target="_blank"
              rel="noopener noreferrer"
              class="ml-auto text-[10px] font-bold uppercase tracking-wider text-indigo-500 hover:text-indigo-300 transition-colors whitespace-nowrap"
            >
              Ver original ↗
            </a>
          </div>

          <!-- Título original -->
          <h2 class="text-sm font-black text-white leading-snug">
            {{ newsItem.title }}
          </h2>

          <!-- Métricas IA -->
          <div class="flex items-center gap-2 flex-wrap">
            <span v-if="newsItem.ai_category" class="badge-meta">
              {{ categoryLabel(newsItem.ai_category) }}
            </span>
            <span
              v-if="newsItem.ai_relevance_score != null"
              class="badge-score"
              :class="scoreColor(newsItem.ai_relevance_score)"
            >
              Relevancia {{ newsItem.ai_relevance_score }}/10
            </span>
          </div>

          <!-- Resumen IA -->
          <div v-if="newsItem.ai_summary">
            <p class="label-xs mb-2">Resumen IA</p>
            <p class="text-xs text-slate-300 leading-relaxed bg-slate-800/50 rounded-lg p-3 border border-slate-700/50">
              {{ newsItem.ai_summary }}
            </p>
          </div>

          <!-- Tags -->
          <div v-if="newsItem.ai_tags?.length">
            <p class="label-xs mb-2">Tags</p>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="tag in newsItem.ai_tags"
                :key="tag"
                class="badge-tag cursor-pointer hover:border-slate-500 transition-colors"
                title="Clic para insertar en el título"
                @click="form.subtitle = form.subtitle ? form.subtitle + ', ' + tag : tag"
              ># {{ tag }}</span>
            </div>
          </div>

          <!-- Entidades canarias -->
          <div v-if="newsItem.ai_canarian_entities?.length">
            <p class="label-xs mb-2">Entidades canarias</p>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="e in newsItem.ai_canarian_entities"
                :key="e"
                class="px-2 py-0.5 rounded-md bg-indigo-900/40 text-indigo-300 text-[10px] font-medium border border-indigo-800/40"
              >{{ e }}</span>
            </div>
          </div>

          <!-- Contenido original completo -->
          <div v-if="newsItem.raw_content">
            <p class="label-xs mb-2">Contenido original</p>
            <div class="text-xs text-slate-400 leading-relaxed bg-slate-900/60 rounded-lg p-3 border border-slate-800 max-h-64 overflow-y-auto whitespace-pre-wrap">{{ newsItem.raw_content }}</div>
          </div>

        </div>
      </aside>

      <!-- ── Panel derecho: editor de post ────────────────────────────── -->
      <main class="flex-1 flex flex-col overflow-hidden">

        <!-- Barra superior del editor -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-800 bg-[#0d1117] sticky top-0 z-10 gap-4">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500 whitespace-nowrap">
              Nuevo post
            </span>
            <span class="text-slate-800 text-xs">·</span>
            <span class="text-[10px] text-slate-600 truncate">
              Ctrl+S para guardar borrador
            </span>
          </div>

          <!-- Acciones -->
          <div class="flex items-center gap-2 flex-shrink-0">
            <!-- Toggle visibilidad -->
            <label class="flex items-center gap-2 cursor-pointer">
              <span class="text-[10px] font-bold uppercase tracking-wider"
                :class="form.visible ? 'text-[#13c090]' : 'text-slate-500'"
              >
                {{ form.visible ? 'Publicar' : 'Borrador' }}
              </span>
              <div class="relative inline-flex items-center">
                <input type="checkbox" v-model="form.visible" class="sr-only peer">
                <div class="w-9 h-5 bg-slate-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#13c090]"></div>
              </div>
            </label>

            <!-- Guardar borrador -->
            <button
              class="btn-save"
              :disabled="isSaving"
              @click="savePost(false)"
            >
              {{ isSaving && !form.visible ? saveLabel : 'Guardar borrador' }}
            </button>

            <!-- Publicar -->
            <button
              class="btn-publish"
              :disabled="isSaving"
              @click="savePost(true)"
            >
              <span v-if="isSaving && form.visible" class="inline-flex items-center gap-1.5">
                <span class="w-2.5 h-2.5 border-2 border-current border-t-transparent rounded-full animate-spin"></span>
                {{ saveLabel }}
              </span>
              <span v-else>Publicar →</span>
            </button>
          </div>
        </div>

        <!-- Campos del post -->
        <div class="flex-1 overflow-y-auto px-6 py-6 flex flex-col gap-4">

          <!-- Título -->
          <input
            v-model="form.title"
            type="text"
            maxlength="255"
            placeholder="Título del post…"
            class="w-full bg-transparent text-2xl md:text-3xl font-black text-white outline-none placeholder-slate-700 uppercase italic leading-tight tracking-tight"
          />

          <!-- Subtítulo / tags -->
          <input
            v-model="form.subtitle"
            type="text"
            maxlength="255"
            placeholder="Subtítulo, bajada o tags…"
            class="w-full bg-transparent text-base font-light text-slate-400 outline-none placeholder-slate-700 border-b border-slate-800 pb-3"
          />

          <!-- Nombre del editor -->
          <div class="flex items-center gap-3">
            <span class="label-xs">Autor</span>
            <input
              v-model="form.editorName"
              type="text"
              placeholder="Nombre del editor…"
              class="text-xs text-slate-400 bg-transparent outline-none placeholder-slate-700 flex-1"
            />
          </div>

          <!-- CKEditor -->
          <div class="editor-container flex-1 border border-slate-800 rounded-lg overflow-hidden bg-[#1c222b]">
            <div class="bg-slate-900/50 px-4 py-2.5 border-b border-slate-800">
              <span class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Cuerpo del post</span>
            </div>
            <div class="text-black">
              <Ckeditor
                :editor="editor"
                v-model="form.content"
                :config="editorConfig"
              />
            </div>
          </div>

        </div>
      </main>

    </div>
  </div>
</template>

<style scoped>
/* ── Layout ─────────────────────────────────────────────────── */
.editorial-write {
  font-family: inherit;
}

.context-panel {
  background: #0d1117;
}

/* ── Tipografía ─────────────────────────────────────────────── */
.label-xs {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: #475569;
}

/* ── Badges ─────────────────────────────────────────────────── */
.badge-meta {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 8px;
  border-radius: 5px;
  background: rgb(51 65 85 / 0.5);
  color: #94a3b8;
}
.badge-score {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 2px 8px;
  border-radius: 5px;
}
.badge-tag {
  font-size: 10px;
  color: #64748b;
  padding: 2px 7px;
  border-radius: 4px;
  border: 1px solid rgb(51 65 85 / 0.4);
}

/* ── Botones ─────────────────────────────────────────────────── */
.btn-save {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 7px 14px;
  border-radius: 8px;
  border: 1px solid #334155;
  color: #94a3b8;
  background: #1e293b;
  transition: all 150ms;
  cursor: pointer;
  white-space: nowrap;
}
.btn-save:hover:not(:disabled) { background: #334155; color: #e2e8f0; }
.btn-save:disabled              { opacity: 0.4; cursor: not-allowed; }

.btn-publish {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  padding: 7px 16px;
  border-radius: 8px;
  background: #13c090;
  color: #fff;
  box-shadow: 0 0 14px rgb(19 192 144 / 0.3);
  transition: all 150ms;
  cursor: pointer;
  white-space: nowrap;
}
.btn-publish:hover:not(:disabled) { background: #0fa87c; }
.btn-publish:disabled              { opacity: 0.4; cursor: not-allowed; }
</style>

<!-- CKEditor global styles (no scoped) -->
<style>
.ck-editor__editable {
  background-color: #e2e8f0 !important;
  color: #1a202c !important;
  font-size: 1rem;
  padding: 1.5rem !important;
  min-height: 400px;
  max-height: calc(100vh - 300px);
  overflow-y: auto !important;
}
.ck-toolbar {
  background-color: #1e293b !important;
  border: 0 !important;
  border-bottom: 1px solid #334155 !important;
}
.ck-button       { color: #cbd5e0 !important; }
.ck-button:hover { background-color: #334155 !important; }
.ck-button.ck-on { background-color: #13c090 !important; color: white !important; }
.ck-powered-by   { display: none; }
.ck-editor__editable strong, .ck-editor__editable b  { font-weight: 700 !important; }
.ck-editor__editable em,     .ck-editor__editable i  { font-style: italic !important; }
.ck-editor__editable ul { list-style-type: disc !important; padding-left: 1.5rem !important; margin-bottom: 1rem !important; }
.ck-editor__editable ol { list-style-type: decimal !important; padding-left: 1.5rem !important; margin-bottom: 1rem !important; }
</style>
