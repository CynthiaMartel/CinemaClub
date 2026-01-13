<script setup>
import MainNavbar from '@/components/MainNavbar.vue'
import ErrorToast from '@/components/ErrorToast.vue'
</script>


<template>
  <div class="min-h-screen text-slate-100 flex flex-col">
    <MainNavbar />
    <main class="flex-1">
      <router-view />
    </main>
    <ErrorToast />
  </div>
</template>

<style>
  html, body {
    background-color: #17191c !important;
    margin: 0;
    padding: 0;
    /* Para scroll en tlfno móvil*/
    overflow-x: hidden; 
  }

  #app {
    background-color: #17191c;
    min-height: 100vh;
  }
</style>






