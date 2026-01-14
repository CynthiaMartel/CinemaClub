<script setup>
defineProps(['coverImage', 'user', 'title', 'typeLabel']);
</script>

<template>
  <div class="relative w-full h-[350px] md:h-[450px] overflow-hidden">
    <div 
      class="absolute inset-0 bg-cover bg-center transition-opacity duration-700"
      :style="{ backgroundImage: `url(${coverImage || '/default-backdrop.jpg'})` }"
    ></div>
    <div class="absolute inset-0 bg-gradient-to-b from-black/20 via-[#14181c]/80 to-[#14181c]"></div>
    
    <div class="relative max-w-5xl mx-auto px-4 h-full flex flex-col justify-end pb-10">
      <div class="flex items-center gap-4 mb-4">
        <img :src="user?.avatar_url || '/default-avatar.png'" class="w-10 h-10 rounded-full border-2 border-gray-500 shadow-xl" alt="Avatar">
        <p class="text-sm">
          <span class="text-gray-400">{{ typeLabel }} de</span> 
          <span class="text-white font-bold hover:text-blue-400 cursor-pointer ml-1">{{ user?.name }}</span>
        </p>
      </div>
      <slot name="title">
        <h1 class="text-3xl md:text-5xl font-bold text-white tracking-tight leading-tight">{{ title }}</h1>
      </slot>
    </div>
  </div>
</template>
