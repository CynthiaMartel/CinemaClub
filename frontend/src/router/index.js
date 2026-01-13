// Para las views
import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '@/views/HomeView.vue'
import FilmDetailView from '@/views/FilmDetailView.vue'
import { useAuthStore } from '@/stores/auth'
import UserProfile from '@/views/UserProfile.vue'

const routes = [
  {
    path: '/',
    name: 'home',
    component: HomeView,
  },
  {
    path: '/films/:id',
    name: 'film-detail',
    component: FilmDetailView,
  },
  {
    path: '/profile/:id',
    name: 'user-profile',
    component: UserProfile,
    props: true // permite que el id llegue como prop a la vista
  }

  // ******aquí van más rutas después: noticias, perfil, etc.
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
})

// Guard : proteger rutas que requieran auth******************
router.beforeEach((to, from, next) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next({ name: 'login' })
  }
  return next()
})

export default router

