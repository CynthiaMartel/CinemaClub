<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import api from '@/services/api'

const route = useRoute()

const film = ref(null)
const cargando = ref(true)
const error = ref(null)

const filmYear = computed(() => {
  if (!film.value?.release_date) return ''
  return new Date(film.value.release_date).getFullYear()
})

const formattedReleaseDate = computed(() => {
  if (!film.value?.release_date) return ''
  const d = new Date(film.value.release_date)
  return d.toLocaleDateString('es-ES', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
})

const loadFilm = async () => {
  cargando.value = true
  error.value = null

  try {
    const id = route.params.id
    const { data } = await api.get(`/films/${id}`)
    film.value = data
  } catch (e) {
    console.error(e)
    error.value = 'No se pudo cargar la información de la película.'
  } finally {
    cargando.value = false
  }
}

onMounted(loadFilm)
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100">
    <!-- ESTADOS CARGA / ERROR -->
    <div v-if="cargando" class="flex items-center justify-center h-screen">
      <p class="text-slate-300">Cargando película…</p>
    </div>

    <div v-else-if="error" class="flex items-center justify-center h-screen">
      <p class="text-red-400">{{ error }}</p>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div v-else-if="film" class="flex flex-col">
      <!-- HEADER con imagen de fondo -->
      <header
        class="relative h-72 md:h-96 flex items-end px-6 md:px-16 pb-8 bg-cover bg-center"
        :style="film.frame ? { backgroundImage: `url(${film.frame})` } : {}"
      >
        <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent" />
        <div class="relative z-10">
          <h1 class="text-3xl md:text-5xl font-bold drop-shadow-lg">
            {{ film.title }}
          </h1>
          <p class="text-slate-200 text-lg mt-1">
            {{ filmYear }}
            <span
              v-if="film.original_title && film.original_title !== film.title"
              class="text-sm text-slate-400 ml-2"
            >
              ({{ film.original_title }})
            </span>
          </p>
        </div>
      </header>

      <!-- MAIN -->
      <main class="max-w-6xl mx-auto px-4 md:px-8 py-10 grid gap-10 md:grid-cols-[260px,1fr]">
        <!-- POSTER + BLOQUES LATERALES -->
        <aside class="space-y-6">
          <!-- Poster -->
          <div class="flex justify-center md:block">
            <img
              v-if="film.frame"
              :src="film.frame"
              :alt="`Póster de ${film.title}`"
              class="w-56 rounded-xl shadow-lg shadow-black/60 object-cover"
            />
          </div>

          <!-- Ratings -->
          <section class="space-y-2 text-sm">
            <h2 class="text-sm font-semibold text-slate-200 uppercase tracking-wide">
              Valoraciones
            </h2>
            <div class="flex flex-col gap-2">
              <span
                class="px-3 py-1 rounded-full bg-slate-900 border border-slate-700 inline-flex justify-between"
              >
                <span>Media TMDB</span>
                <strong>{{ film.vote_average ?? '–' }}</strong>
              </span>
              <span
                class="px-3 py-1 rounded-full bg-slate-900 border border-slate-700 inline-flex justify-between"
              >
                <span>Global Cinemaclub</span>
                <strong>
                  {{
                    film.globalRate != null ? Number(film.globalRate).toFixed(1) : '–'
                  }}
                </strong>
              </span>
              <span
                class="px-3 py-1 rounded-full bg-slate-900 border border-slate-700 inline-flex justify-between"
              >
                <span>Tu voto</span>
                <strong>{{ film.individualRate != null ? film.individualRate : '–' }}</strong>
              </span>
            </div>
          </section>

          <!-- Estadísticas de premios / festivales -->
          <section class="space-y-2 text-sm">
            <h2 class="text-sm font-semibold text-slate-200 uppercase tracking-wide">
              Reconocimientos
            </h2>
            <ul class="space-y-1 text-slate-300">
              <li v-if="film.total_awards != null">
                <span class="font-semibold">Premios totales:</span> {{ film.total_awards }}
              </li>
              <li v-if="film.total_nominations != null">
                <span class="font-semibold">Nominaciones totales:</span>
                {{ film.total_nominations }}
              </li>
              <li v-if="film.total_festivals != null">
                <span class="font-semibold">Festivales:</span> {{ film.total_festivals }}
              </li>
            </ul>
          </section>
        </aside>

        <!-- COLUMNA PRINCIPAL -->
        <section class="space-y-10">
          <!-- FICHA TÉCNICA -->
          <section class="space-y-2">
            <h2 class="text-lg font-semibold">Ficha técnica</h2>
            <ul class="space-y-1 text-sm text-slate-200">
              <li>
                <span class="font-semibold text-slate-100">Género:</span>
                {{ film.genre }}
              </li>
              <li v-if="film.directors?.length">
                <span class="font-semibold text-slate-100">Dirigida por:</span>
                {{ film.directors.map(d => d.name).join(', ') }}
              </li>
              <li>
                <span class="font-semibold text-slate-100">País:</span>
                {{ film.origin_country }}
              </li>
              <li>
                <span class="font-semibold text-slate-100">Idioma original:</span>
                {{ film.original_language }}
              </li>
              <li>
                <span class="font-semibold text-slate-100">Duración:</span>
                {{ film.duration }} min
              </li>
              <li v-if="film.release_date">
                <span class="font-semibold text-slate-100">Estreno:</span>
                {{ formattedReleaseDate }}
              </li>
              <li v-if="film.awards">
                <span class="font-semibold text-slate-100">Premios:</span>
                {{ film.awards }}
              </li>
              <li v-if="film.nominations">
                <span class="font-semibold text-slate-100">Nominaciones:</span>
                {{ film.nominations }}
              </li>
              <li v-if="film.festivals">
                <span class="font-semibold text-slate-100">Festivales:</span>
                {{ film.festivals }}
              </li>
            </ul>
          </section>

          <!-- REPARTO -->
          <section v-if="film.cast?.length" class="space-y-2">
            <h2 class="text-lg font-semibold">Reparto</h2>
            <ul class="grid gap-2 text-sm text-slate-200 md:grid-cols-2">
              <li
                v-for="person in film.cast"
                :key="person.idPerson"
                class="flex flex-col"
              >
                <span class="font-semibold">{{ person.name }}</span>
                <span v-if="person.character_name" class="text-slate-400 text-xs">
                  como {{ person.character_name }}
                </span>
              </li>
            </ul>
          </section>

          <!-- SINOPSIS -->
          <section class="space-y-2">
            <h2 class="text-lg font-semibold">Sinopsis</h2>
            <p class="text-slate-200 leading-relaxed">
              {{ film.overview }}
            </p>
          </section>

          <!-- HUECO EXTRA PARA VOTOS / COMENTARIOS / LO QUE QUIERAS -->
          <section class="space-y-2">
            <h2 class="text-lg font-semibold">
              <!-- aquí puedes poner el título que quieras luego -->
              Interacción de usuarios
            </h2>
            <div
              class="border border-dashed border-slate-600 rounded-xl p-4 text-sm text-slate-400"
            >
              <!-- Aquí añadirás después:
                   - formulario de voto con estrellas,
                   - estadísticas de votos,
                   - comentarios de usuarios, etc. -->
              Aquí irá la sección de votos y comentarios cuando la implemente.
            </div>
          </section>
        </section>
      </main>
    </div>
  </div>
</template>
