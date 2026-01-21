<template>
  <div class="relative w-full pt-12 pb-8 bg-[#17191c] overflow-hidden">
    <div class="absolute inset-0 bg-gradient-to-b to-transparent opacity-20" :class="bgGradient"></div>
    
    <div class="relative max-w-6xl mx-auto px-6">
      <div class="flex flex-wrap items-center gap-6 mb-8">
        <div class="flex items-center gap-2 px-3 py-1 bg-slate-900/80 border border-slate-800 rounded-full shadow-sm">
          <span class="w-1.5 h-1.5 rounded-full animate-pulse" :class="accentColor.replace('text', 'bg')"></span>
          <span class="text-[10px] font-black uppercase tracking-[2px]" :class="accentColor">
            {{ typeLabel }}
          </span>
        </div>

        <span class="text-slate-800 font-thin text-2xl select-none">|</span>

        <div class="flex items-center gap-4 group cursor-pointer">
          <img :src="user?.avatar_url || '/default-avatar.png'" 
               class="w-8 h-8 rounded-full border border-slate-700 object-cover shadow-xl transition-all duration-300 group-hover:border-orange-500/50">
          <p class="text-[11px] tracking-tight">
            <span class="text-slate-500 italic font-light lowercase">escrito por</span>
            <span class="text-white font-black ml-3 uppercase tracking-[2px] transition-colors group-hover:text-emerald-400">
              @{{ user?.name }}
            </span>
          </p>
        </div>
      </div>

      <h1 class="text-4xl md:text-5xl lg:text-6xl font-black text-white tracking-tighter leading-[1.1] max-w-4xl drop-shadow-md">
        {{ title }}
      </h1>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps(['user', 'title', 'type', 'accentColor', 'bgGradient']);

const typeLabel = computed(() => {
  if (props.type === 'user_list') return 'Lista';
  if (props.type === 'user_debate') return 'Debate';
  return 'Crítica';
});
</script>

