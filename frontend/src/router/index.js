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
    path: '/register',
    name: 'register',
    component: () => import('../views/auth/Register.vue'),
    meta: {
      title: 'auth.register',
      requiresAuth: false,
    },
  },
  {
    path: '/register/invite/:token',
    name: 'register-invite',
    component: () => import('../views/auth/RegisterInvite.vue'),
    meta: {
      title: 'auth.inviteRegisterTitle',
      requiresAuth: false,
    },
  },
  {
    path: '/forgot-password',
    name: 'forgot-password',
    component: () => import('../views/auth/ForgotPassword.vue'),
    meta: {
      title: 'auth.forgotPasswordTitle',
      requiresAuth: false,
    },
  },
  {
    path: '/reset-password',
    name: 'reset-password',
    component: () => import('../views/auth/ResetPassword.vue'),
    meta: {
      title: 'auth.resetPasswordTitle',
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
  {
    path: '/admin/pending-users',
    name: 'admin-pending-users',
    component: () => import('../views/admin/PendingUsers.vue'),
    meta: {
      title: 'admin.pendingUsersTitle',
      requiresAuth: true,
      requiredRole: 'Admin',
    },
  },
  {
    path: '/admin/invitations',
    name: 'admin-invitations',
    component: () => import('../views/admin/Invitations.vue'),
    meta: {
      title: 'admin.invitationsTitle',
      requiresAuth: true,
      requiredRole: 'Admin',
    },
  },
]

const router = createRouter({
  history: createWebHistory('/'),
  routes,
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  const { useAuthStore } = await import('../stores/auth.js')
  const authStore = useAuthStore()

  // Check if route requires authentication
  if (to.meta.requiresAuth) {
    if (!authStore.isAuthenticated) {
      // Redirect to login page
      return next('/login')
    }

    // Check if route requires specific role
    if (to.meta.requiredRole) {
      const userRole = authStore.user?.role?.name
      const requiredRole = to.meta.requiredRole

      // Allow Superadmin to access any Admin route
      const hasPermission = userRole === requiredRole ||
                           (requiredRole === 'Admin' && userRole === 'Superadmin')

      if (!hasPermission) {
        // Redirect to dashboard if user doesn't have required role
        return next('/dashboard')
      }
    }
  }

  // If user is authenticated and trying to access auth pages, redirect to dashboard
  if (authStore.isAuthenticated && ['login', 'register', 'forgot-password', 'reset-password', 'register-invite'].includes(to.name)) {
    return next('/dashboard')
  }

  next()
})

export default router
