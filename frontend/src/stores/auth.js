import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const accessToken = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const isAuthenticated = computed(() => !!accessToken.value)
  const userRole = computed(() => user.value?.role || null)
  const tenantId = computed(() => user.value?.tenant_id || null)

  // Actions
  async function login(email, password) {
    loading.value = true
    error.value = null

    try {
      // TODO: Implement actual API call in Sprint 1
      // const response = await authApi.login(email, password)
      // accessToken.value = response.data.access_token
      // user.value = response.data.user

      // Mock implementation for Sprint 0 baseline
      console.log('Login attempt:', { email, password })

      // Simulate API delay
      await new Promise((resolve) => setTimeout(resolve, 1000))

      // Mock successful login
      accessToken.value = 'mock-token'
      user.value = {
        id: 1,
        email,
        name: 'Demo User',
        role: 'Admin',
        tenant_id: 1,
      }

      return { success: true }
    } catch (err) {
      error.value = err.message || 'Login failed'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    loading.value = true

    try {
      // TODO: Implement actual API call in Sprint 1
      // await authApi.logout()

      // Clear state
      accessToken.value = null
      user.value = null
      error.value = null

      return { success: true }
    } catch (err) {
      error.value = err.message || 'Logout failed'
      return { success: false, error: error.value }
    } finally {
      loading.value = false
    }
  }

  async function refreshToken() {
    // TODO: Implement actual refresh logic in Sprint 1
    console.log('Token refresh not yet implemented')
  }

  function clearError() {
    error.value = null
  }

  return {
    // State
    user,
    accessToken,
    loading,
    error,

    // Getters
    isAuthenticated,
    userRole,
    tenantId,

    // Actions
    login,
    logout,
    refreshToken,
    clearError,
  }
})
