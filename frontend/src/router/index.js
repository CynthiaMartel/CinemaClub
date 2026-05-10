import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

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
    component: () => import('@/views/HomeView.vue'),
  },
  {
    path: '/films/:id',
    name: 'film-detail',
    component: () => import('@/views/FilmDetailView.vue'),
  },
  {
    path: '/profile/:username',
    name: 'user-profile',
    component: () => import('@/views/UserProfile.vue'),
    props: true,
  },
  {
    path: '/entry/:type/:id',
    name: 'entry-detail',
    component: () => import('@/views/EntryPrincipalView.vue'),
    props: true,
  },
  {
    path: '/create-entry/:id?',
    name: 'create-entry',
    component: () => import('@/views/EntryFormView.vue'),
    props: true,
    meta: { requiresAuth: true },
  },
  {
    path: '/entry-feed/',
    name: 'entry-feed',
    component: () => import('@/views/EntryFeedView.vue'),
    props: true,
  },
  {
    path: '/films',
    name: 'FilmsFeed',
    component: () => import('@/views/FilmsFeedView.vue'),
  },
  {
    path: '/search',
    name: 'search',
    component: () => import('@/views/SearchView.vue'),
  },
  {
  path: '/post-editor/:id?',
  name: 'post-editor',
  component: () => import('@/views/PostEditorView.vue'),
  props: true,
  meta: { requiresAuth: true, requiresEditor: true },
  },

  {
    path: '/community',
    name: 'community',
    component: () => import('@/views/CommunityView.vue'),
    meta: { requiresAuth: true },
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

  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('@/views/ResetPasswordView.vue'),
  },
  {
    path: '/verify-email/:token',
    name: 'verify-email',
    component: () => import('@/views/VerifyEmailView.vue'),
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

  // Sin sesión → redirige a home con ?redirect= para que LoginModal retome el destino
  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next({ name: 'home', query: { redirect: to.fullPath } })
  }

  // Solo admins (idRol 1)
  if (to.meta.requiresAdmin && auth.user?.idRol != 1) {
    return next({ name: 'home' })
  }

  // Solo editors/admins (idRol 1 o 2)
  if (to.meta.requiresEditor && auth.user?.idRol != 1 && auth.user?.idRol != 2) {
    return next({ name: 'home' })
  }

  return next()
})

export default router

