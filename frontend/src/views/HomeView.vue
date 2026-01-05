<script setup>
import { useAuthStore } from '@/stores/auth'
import { computed, ref } from 'vue'
import RegisterModal from '@/components/RegisterModal.vue'


const auth = useAuthStore()

const userName = computed(() =>
  auth.user?.name
    ? auth.user.name.charAt(0).toUpperCase() + auth.user.name.slice(1).toLowerCase()
    : '',
)

  const isRegisterOpen = ref(false)

  const openRegister = () => { isRegisterOpen.value = true }
</script>

<template>
  <div class="min-h-[calc(100vh-4rem)] w-full px-4 py-10">
    <!-- Mensaje de bienvenida -->
    <div v-if="auth.isAuthenticated" class="mb-6 text-center text-slate-100">
      <h1 class="text-2xl md:text-3xl font-semibold">
        ¡Te damos la bienvenida, {{ userName }}!
      </h1>
    </div>

    <!-- Hero ancho completo -->
    <div
      class="w-full py-16 px-6 md:px-10 rounded-3xl bg-[radial-gradient(circle_at_top,_#7f1d1d,_#020617)] shadow-xl flex flex-col items-center text-center gap-4"
    >
      <h1 class="text-3xl md:text-4xl font-bold tracking-wide text-red-400 drop-shadow-lg">
        Watch. Rate. Debate.
      </h1>

      <h3 class="text-lg md:text-2xl font-medium text-red-100 drop-shadow">
        The film lovers' community starts here
      </h3>

      <p class="mt-2 text-sm md:text-base text-slate-200 max-w-xl">
        Descubre películas, puntúalas, crea listas y debate con otras personas cinéfilas.
      </p>

      <button
        v-if="!auth.isAuthenticated"
        type="button"
        class="mt-6 px-6 py-2.5 rounded-full bg-emerald-500 text-slate-900 font-semibold text-sm md:text-base hover:bg-emerald-400"
        @click="openRegister"
      >
        ¡Únete a la comunidad!
      </button>
    </div>
  </div>
  <!-- Modal -->
  <RegisterModal v-model="isRegisterOpen" />
</template>



