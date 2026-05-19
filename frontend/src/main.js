import { createApp } from 'vue'
import { createPinia } from 'pinia'
import { createI18n } from 'vue-i18n'
import router from './router'
import App from './App.vue'
import './style.css'

// Import locale messages
import en from './i18n/locales/en.json'

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

// Mount app
app.mount('#app')
