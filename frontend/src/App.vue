<script setup>
import MainNavbar from '@/components/MainNavbar.vue'
import ErrorToast from '@/components/ErrorToast.vue'
import OfflineBanner from '@/components/OfflineBanner.vue'
</script>


<template>
  <div class="min-h-screen text-slate-100 flex flex-col">
    <MainNavbar />
    <OfflineBanner />
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
  }

  /* overflow-x SOLO en body, nunca en html — si se aplica en html
     algunos navegadores convierten html en scroll container y rompen
     window.scrollY y position:sticky */
  body {
    overflow-x: hidden;
  }

  #app {
    background-color: #17191c;
    min-height: 100vh;
  }
</style>






