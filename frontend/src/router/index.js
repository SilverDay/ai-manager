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
  {
    path: '/profile',
    name: 'profile',
    component: () => import('../views/user/Profile.vue'),
    meta: {
      title: 'Profile Settings',
      requiresAuth: true,
    },
  },
  {
    path: '/settings',
    name: 'settings',
    component: () => import('../views/user/Settings.vue'),
    meta: {
      title: 'Account Settings',
      requiresAuth: true,
    },
  },
  {
    path: '/superadmin/tenants',
    name: 'superadmin-tenants',
    component: () => import('../views/superadmin/TenantManagement.vue'),
    meta: {
      title: 'Tenant Management',
      requiresAuth: true,
      requiredRole: 'Superadmin',
    },
  },
  {
    path: '/superadmin/system',
    name: 'superadmin-system',
    component: () => import('../views/superadmin/SystemSettings.vue'),
    meta: {
      title: 'System Settings',
      requiresAuth: true,
      requiredRole: 'Superadmin',
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

  console.log('🛣️ Router guard: Navigating to', to.name, 'from', from.name)
  console.log('🛣️ Router guard: isAuthenticated?', authStore.isAuthenticated)
  console.log('🛣️ Router guard: accessToken?', !!authStore.accessToken)
  console.log('🛣️ Router guard: user?', !!authStore.user)

  // Check if route requires authentication
  if (to.meta.requiresAuth) {
    console.log('🛣️ Router guard: Route requires auth')
    if (!authStore.isAuthenticated) {
      console.log('🛣️ Router guard: Not authenticated, redirecting to login')
      // Redirect to login page
      return next('/login')
    }

    // Check if route requires specific role
    if (to.meta.requiredRole) {
      const userRole = authStore.user?.role_name
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
