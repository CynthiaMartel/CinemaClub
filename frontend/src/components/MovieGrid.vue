<script setup>
defineProps({
  films: Array,
  showNumbers: Boolean,
  isEditable: Boolean 
});

defineEmits(['remove']);
</script>

<template>
  <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3 md:gap-5">
    <div v-for="(film, index) in films" :key="film.idFilm || film.id" class="relative group">
      
      <div class="aspect-[2/3] overflow-hidden rounded-md border border-gray-800 group-hover:border-red-500 transition-all shadow-lg relative">
        <img :src="film.poster_url" class="w-full h-full object-cover">

        <button 
          v-if="isEditable" 
          @click="$emit('remove', index)"
          class="absolute top-1 right-1 bg-red-600/90 hover:bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center transition-all z-20 shadow-xl"
          title="Eliminar de la lista"
        >
          <span class="text-xs font-bold">×</span>
        </button>
      </div>

      <span v-if="showNumbers" class="absolute -top-2 -left-2 bg-[#445566] text-white text-[10px] font-bold px-1.5 rounded-sm shadow-md">
        {{ index + 1 }}
      </span>
    </div>
  </div>
</template>




