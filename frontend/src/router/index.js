// Para las views
import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '@/views/HomeView.vue'
import FilmDetailView from '@/views/FilmDetailView.vue'
import { useAuthStore } from '@/stores/auth'
import UserProfile from '@/views/UserProfile.vue'
import EntryPrincipalView from '@/views/EntryPrincipalView.vue'
import EntryFormView from '@/views/EntryFormView.vue'
import EntryFeedView from '@/views/EntryFeedView.vue'
import FilmsFeedView from '@/views/FilmsFeedView.vue'


const routes = [
  {
    path: '/admin',
    name: 'admin-dashboard',
    component: () => import('@/views/AdminDashboardView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/films/new',
    name: 'admin-film-create',
    component: () => import('@/views/AdminFilmFormView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true },
  },
  {
    path: '/admin/films/:id/edit',
    name: 'admin-film-edit',
    component: () => import('@/views/AdminFilmFormView.vue'),
    props: true,
    meta: { requiresAuth: true, requiresAdmin: true },
  },
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
    path: '/profile/:username',
    name: 'user-profile',
    component: UserProfile,
    props: true
  },
  {
  path: '/entry/:type/:id', 
  name: 'entry-detail',
  component: EntryPrincipalView,
  props: true // Esto permite pasar los parámetros como variables automática
  },
  {
  path: '/create-entry/:id?',
  name: 'create-entry',
  component: EntryFormView,
  props: true // Esto permite pasar los parámetros como variables automática
  },
  {
    path: '/entry-feed/',
    name: 'entry-feed',
    component: EntryFeedView,
    props: true
  },
  {
    path: '/films',
    name: 'FilmsFeed',
    component: FilmsFeedView,
  },
  {
    path: '/search',
    name: 'search',
    component: () => import('@/views/SearchView.vue'),
  },
  {
  path: '/post-editor/:id?', 
  name: 'post-editor',
  // Opción recomendada (Lazy load)
  component: () => import('@/views/PostEditorView.vue'),
  props: true // Esto permite pasar los parámetros como variables automática
  },

  {
  path: '/post-feed',
  name: 'post-feed', 
  component: () => import('@/views/PostsFeedView.vue') 
},

{path: '/post-reed/:id?',
  name: 'post-reed', 
  component: () => import('@/views/PostDetailView.vue') 
},
  


  {
    path: '/recomendador',
    name: 'recommender',
    component: () => import('@/views/RecommenderView.vue'),
  },

  {
    path: '/editorial/inbox',
    name: 'editorial-inbox',
    component: () => import('@/views/EditorialInboxView.vue'),
    meta: { requiresAuth: true },
  },

  {
    path: '/editorial/sources',
    name: 'editorial-sources',
    component: () => import('@/views/EditorialSourcesView.vue'),
    meta: { requiresAuth: true },
  },

  {
    path: '/editorial/write/:id',
    name: 'editorial-write',
    component: () => import('@/views/EditorialWriteView.vue'),
    props: true,
    meta: { requiresAuth: true },
  },

  {
    path: '/agenda',
    name: 'agenda',
    component: () => import('@/views/AgendaView.vue'),
  },

  {
    path: '/events',
    name: 'event-manager',
    component: () => import('@/views/EventManagerView.vue'),
    meta: { requiresAuth: true },
  },

  // ******aquí van más rutas después: noticias, perfil, etc.
]

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
  scrollBehavior() {
    return { top: 0, behavior: 'instant' }
  },
})

// Guard : proteger rutas que requieran auth y/o rol admin
router.beforeEach((to, _from, next) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next({ name: 'home' })
  }

  if (to.meta.requiresAdmin && auth.user?.idRol != 1) {
    return next({ name: 'home' })
  }

  return next()
})

export default router

