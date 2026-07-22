import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../store/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('../views/LoginView.vue'),
    meta: { guest: true }
  },
  {
    path: '/',
    name: 'Dashboard',
    component: () => import('../views/DashboardView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/books',
    name: 'Books',
    component: () => import('../views/BooksView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/books/add',
    name: 'AddBook',
    component: () => import('../views/BookFormView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/books/scan',
    name: 'ScanBook',
    component: () => import('../views/ScanBookView.vue'),
    meta: { requiresAuth: true, requiresUser: true }
  },
  {
    path: '/books/:id',
    name: 'BookDetail',
    component: () => import('../views/BookDetailView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/books/:id/edit',
    name: 'EditBook',
    component: () => import('../views/BookFormView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/writers',
    name: 'Writers',
    component: () => import('../views/WritersView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/genres',
    name: 'Genres',
    component: () => import('../views/GenresView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/publishers',
    name: 'Publishers',
    component: () => import('../views/PublishersView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/locations',
    name: 'Locations',
    component: () => import('../views/LocationsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/lending',
    name: 'Lending',
    component: () => import('../views/LendingView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('../views/ReportsView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/users',
    name: 'Users',
    component: () => import('../views/UsersView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/backup',
    name: 'Backup',
    component: () => import('../views/BackupView.vue'),
    meta: { requiresAuth: true, requiresAdmin: true }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: () => import('../views/SettingsView.vue'),
    meta: { requiresAuth: true }
  },
  // E-Book Plugin routes
  {
    path: '/ebooks',
    name: 'Ebooks',
    component: () => import('../views/EbooksView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/ebooks/:id',
    name: 'EbookDetail',
    component: () => import('../views/EbookDetailView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/ebooks/:id/read',
    name: 'EbookReader',
    component: () => import('../views/EbookReaderView.vue'),
    meta: { requiresAuth: true }
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

// Navigation guards
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.meta.guest && authStore.isAuthenticated) {
    next('/')
  } else if (to.meta.requiresAdmin && authStore.user?.role !== 'admin') {
    next('/')
  } else if (to.meta.requiresUser && !['admin', 'user'].includes(authStore.user?.role)) {
    next('/')
  } else {
    next()
  }
})

export default router
