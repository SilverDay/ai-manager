import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi, getApiErrorMessage, isAuthenticationError } from '../api/auth.js'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref(null)
  const accessToken = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const mfaRequired = ref(false)
  const mfaChallenge = ref(null)

  // Getters
  const isAuthenticated = computed(() => !!accessToken.value)
  const userRole = computed(() => user.value?.role_name || null)
  const tenantId = computed(() => user.value?.tenant_id || null)
  const isSuperadmin = computed(() => userRole.value === 'Superadmin')
  const requiresEmailVerification = computed(() =>
    user.value && !user.value.email_verified_at
  )

  // Actions
  async function login(email, password) {
    loading.value = true
    error.value = null
    mfaRequired.value = false

    try {
      const response = await authApi.login(email, password)

      // Check if MFA is required
      if (response.data.mfa_required) {
        mfaRequired.value = true
        mfaChallenge.value = response.data.challenge_token

        return {
          success: false,
          mfa_required: true,
          challenge: response.data.challenge_token
        }
      }

      // Successful login
      accessToken.value = response.data.access_token
      user.value = response.data.user

      // Store auth state in localStorage for persistence
      persistAuthState()

      return { success: true }
    } catch (err) {
      const errorMessage = getApiErrorMessage(err)
      error.value = errorMessage

      // Clear any stored auth state on login failure
      clearPersistedAuthState()

      return { success: false, error: errorMessage }
    } finally {
      loading.value = false
    }
  }

  async function verifyMfa(code) {
    if (!mfaChallenge.value) {
      throw new Error('No MFA challenge token available')
    }

    loading.value = true
    error.value = null

    try {
      const response = await authApi.verifyMfa(mfaChallenge.value, code)

      // MFA verification successful
      accessToken.value = response.data.access_token
      user.value = response.data.user
      mfaRequired.value = false
      mfaChallenge.value = null

      // Store auth state
      persistAuthState()

      return { success: true }
    } catch (err) {
      const errorMessage = getApiErrorMessage(err)
      error.value = errorMessage
      return { success: false, error: errorMessage }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    loading.value = true

    try {
      // Call logout API if we have a token
      if (accessToken.value) {
        await authApi.logout()
      }
    } catch (err) {
      // Log the error but don't fail logout
      console.warn('Logout API call failed:', getApiErrorMessage(err))
    } finally {
      // Always clear local state
      clearAuthState()
      loading.value = false
    }

    return { success: true }
  }

  async function refreshToken() {
    try {
      const response = await authApi.refresh()

      accessToken.value = response.data.access_token

      // Update stored token
      if (typeof localStorage !== 'undefined') {
        localStorage.setItem('access_token', accessToken.value)
      }

      return { success: true }
    } catch (err) {
      console.error('Token refresh failed:', getApiErrorMessage(err))

      // Clear auth state if refresh fails
      clearAuthState()

      return { success: false, error: getApiErrorMessage(err) }
    }
  }

  async function register(userData) {
    loading.value = true
    error.value = null

    try {
      const response = await authApi.register(userData)

      return {
        success: true,
        data: response.data,
        requiresApproval: response.data.requires_approval
      }
    } catch (err) {
      const errorMessage = getApiErrorMessage(err)
      error.value = errorMessage
      return { success: false, error: errorMessage }
    } finally {
      loading.value = false
    }
  }

  async function registerWithInvite(token, userData) {
    loading.value = true
    error.value = null

    try {
      const response = await authApi.registerWithInvite(token, userData)

      return { success: true, data: response.data }
    } catch (err) {
      const errorMessage = getApiErrorMessage(err)
      error.value = errorMessage
      return { success: false, error: errorMessage }
    } finally {
      loading.value = false
    }
  }

  async function forgotPassword(email) {
    loading.value = true
    error.value = null

    try {
      const response = await authApi.forgotPassword(email)
      return { success: true, message: response.data.message }
    } catch (err) {
      const errorMessage = getApiErrorMessage(err)
      error.value = errorMessage
      return { success: false, error: errorMessage }
    } finally {
      loading.value = false
    }
  }

  async function resetPassword(token, password) {
    loading.value = true
    error.value = null

    try {
      const response = await authApi.resetPassword(token, password)
      return { success: true, message: response.data.message }
    } catch (err) {
      const errorMessage = getApiErrorMessage(err)
      error.value = errorMessage
      return { success: false, error: errorMessage }
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  function clearAuthState() {
    accessToken.value = null
    user.value = null
    error.value = null
    mfaRequired.value = false
    mfaChallenge.value = null
    clearPersistedAuthState()
  }

  function persistAuthState() {
    if (typeof localStorage !== 'undefined') {
      if (accessToken.value) {
        localStorage.setItem('access_token', accessToken.value)
      }
      if (user.value) {
        localStorage.setItem('user', JSON.stringify(user.value))
      }
    }
  }

  function clearPersistedAuthState() {
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem('access_token')
      localStorage.removeItem('user')
    }
  }

  function restoreAuthState() {
    if (typeof localStorage !== 'undefined') {
      const storedToken = localStorage.getItem('access_token')
      const storedUser = localStorage.getItem('user')

      if (storedToken && storedUser) {
        try {
          accessToken.value = storedToken
          user.value = JSON.parse(storedUser)
        } catch (err) {
          console.error('Failed to restore auth state:', err)
          clearPersistedAuthState()
        }
      }
    }
  }

  // Check if user has specific permission
  function hasPermission(permission) {
    if (isSuperadmin.value) return true
    return user.value?.permissions?.includes(permission) || false
  }

  // Check if user has specific role
  function hasRole(role) {
    if (typeof role === 'string') {
      return userRole.value === role || isSuperadmin.value
    }
    if (Array.isArray(role)) {
      return role.includes(userRole.value) || isSuperadmin.value
    }
    return false
  }

  return {
    // State
    user,
    accessToken,
    loading,
    error,
    mfaRequired,
    mfaChallenge,

    // Getters
    isAuthenticated,
    userRole,
    tenantId,
    isSuperadmin,
    requiresEmailVerification,

    // Actions
    login,
    logout,
    refreshToken,
    register,
    registerWithInvite,
    forgotPassword,
    resetPassword,
    verifyMfa,
    clearError,
    clearAuthState,
    restoreAuthState,
    hasPermission,
    hasRole,
  }
})
