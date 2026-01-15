<script setup>
defineProps({
  films: Array,
  showNumbers: Boolean,
  isEditable: Boolean 
});

defineEmits(['remove']);
</script>

<template>
  <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 lg:grid-cols-6 gap-4">
    <div v-for="(film, index) in films" :key="film.idFilm || film.id" 
         class="relative group cursor-pointer transition-transform duration-300 hover:-translate-y-2">
      
      <div class="aspect-[2/3] overflow-hidden rounded-lg border border-slate-800 group-hover:border-emerald-500/50 transition-all shadow-xl bg-slate-900">
        <img :src="film.poster_url" 
             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
             :alt="film.title">
             
        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center p-4">
          <p class="text-[10px] text-white font-bold uppercase tracking-wider text-center">
            {{ film.title }}
          </p>
        </div>

        <button 
          v-if="isEditable" 
          @click.stop="$emit('remove', index)"
          class="absolute top-2 right-2 bg-red-600/90 hover:bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center z-20 shadow-lg"
        >
          <span class="text-sm font-light">×</span>
        </button>
      </div>

      <div v-if="showNumbers" 
           class="absolute -top-2 -left-2 bg-slate-900 border border-slate-700 text-indigo-500 text-[10px] font-black w-6 h-6 flex items-center justify-center rounded shadow-2xl z-10">
        {{ index + 1 }}
      </div>
    </div>
  </div>
</template>




