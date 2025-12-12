<script setup>
import MainNavbar from '@/components/MainNavbar.vue'
</script>

<template>
  <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col">
    <MainNavbar />
    <main class="flex-1">
      <router-view />
    </main>
  </div>
</template>


