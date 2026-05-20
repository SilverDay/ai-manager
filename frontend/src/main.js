import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import router from './router'
import App from './App.vue'
import './style.css'

// Import locale messages
import en from './i18n/locales/en.json'

// Import API client and auth store setup
import { setAuthStore } from './api/client.js'
import { useAuthStore } from './stores/auth.js'

// Create i18n instance
const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  messages: {
    en,
  },
})

// Create Pinia store
const pinia = createPinia()

// Create Vue app
const app = createApp(App)

// Install plugins
app.use(pinia)
app.use(router)
app.use(i18n)

// Initialize auth store and API client integration
const authStore = useAuthStore()

// Set up API client with auth store reference
setAuthStore(authStore)

// Add router reference to auth store for redirects
authStore.router = router

// Restore auth state from localStorage on app initialization
authStore.restoreAuthState()

// Navigation guards are handled in router/index.js

// Add global error handler for unhandled promise rejections
window.addEventListener('unhandledrejection', (event) => {
  console.error('Unhandled promise rejection:', event.reason)

  // If it's an authentication error, clear auth state
  if (event.reason?.response?.status === 401) {
    authStore.clearAuthState()
    if (router.currentRoute.value.path !== '/login') {
      router.push('/login')
    }
  }
})

// Mount app
app.mount('#app')

// Make authStore available globally for debugging
if (import.meta.env.DEV) {
  window.authStore = authStore
  window.router = router
  console.log('🚀 App initialized with auth store and router available globally for debugging')
}
