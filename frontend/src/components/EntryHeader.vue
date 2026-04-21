<template>
  <div class="relative w-full h-[220px] md:h-[280px] bg-[#17191c] overflow-hidden">

    <div
      v-if="headerBg"
      class="absolute inset-0 bg-cover bg-center opacity-40 animate-ken-burns"
      :style="{ backgroundImage: `url(${headerBg})` }"
    ></div>

    <div class="absolute inset-0 bg-gradient-to-t from-[#17191c] via-[#17191c]/20 to-[#17191c]/60"></div>
    <div class="absolute inset-0 bg-gradient-to-b to-transparent opacity-10" :class="bgGradient"></div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps(['bgGradient', 'films']);

const headerBg = computed(() => {
  if (!props.films || props.films.length === 0) return null;
  const [film1, film2, film3] = props.films;
  return film1?.backdrop || film2?.backdrop || film3?.backdrop || film1?.frame || null;
});
</script>

<style scoped>
@keyframes ken-burns {
  0%   { transform: scale(1); }
  50%  { transform: scale(1.06); }
  100% { transform: scale(1); }
}
.animate-ken-burns {
  animation: ken-burns 30s ease-in-out infinite;
}
</style>
