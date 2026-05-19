import { createRouter, createWebHistory } from 'vue-router'
import Home from '../views/Home.vue'

const routes = [
  {
    path: '/',
    name: 'home',
    component: Home,
    meta: {
      title: 'common.home',
      requiresAuth: false,
    },
  },
  {
    path: '/login',
    name: 'login',
    component: () => import('../views/auth/Login.vue'),
    meta: {
      title: 'auth.login',
      requiresAuth: false,
    },
  },
  {
    path: '/dashboard',
    name: 'dashboard',
    component: () => import('../views/Dashboard.vue'),
    meta: {
      title: 'dashboard.title',
      requiresAuth: true,
    },
  },
]

const router = createRouter({
  history: createWebHistory('/'),
  routes,
})

// Navigation guards will be implemented in Sprint 1
router.beforeEach((to, from, next) => {
  // TODO: Implement authentication guard in Sprint 1
  // For now, allow all routes
  next()
})

export default router
