import axios from 'axios'

// Create axios instance
const apiClient = axios.create({
  baseURL: '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// Store a reference to the auth store that will be set during app initialization
let authStore = null

/**
 * Set the auth store reference for use in interceptors
 * This is called during app initialization to avoid circular dependency issues
 */
export function setAuthStore(store) {
  authStore = store
}

// Request interceptor to add auth token
apiClient.interceptors.request.use(
  (config) => {
    // Add authorization header if we have a token
    if (authStore?.accessToken) {
      config.headers.Authorization = `Bearer ${authStore.accessToken}`
    }

    // Add request timestamp for debugging
    config.metadata = { startTime: Date.now() }

    return config
  },
  (error) => {
    console.error('Request interceptor error:', error)
    return Promise.reject(error)
  },
)

// Response interceptor for error handling and token refresh
apiClient.interceptors.response.use(
  (response) => {
    // Add response time for performance monitoring
    if (response.config.metadata?.startTime) {
      const responseTime = Date.now() - response.config.metadata.startTime
      if (responseTime > 2000) { // Log slow requests
        console.warn(`Slow API request: ${response.config.method?.toUpperCase()} ${response.config.url} took ${responseTime}ms`)
      }
    }

    return response
  },
  async (error) => {
    const originalRequest = error.config

    // Handle 401 errors - attempt token refresh (but avoid infinite loops)
    if (error.response?.status === 401 && !originalRequest._retry && authStore && authStore.accessToken) {
      originalRequest._retry = true

      // Don't retry if this was already a refresh request (prevent infinite loop)
      if (originalRequest.url?.includes('/auth/refresh')) {
        console.warn('Refresh token failed - clearing auth state')
        authStore.clearAuthState()
        if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
          window.location.href = '/login'
        }
        return Promise.reject(error)
      }

      try {
        // Attempt to refresh the token
        const refreshResult = await authStore.refreshToken()

        if (refreshResult.success) {
          // Update the Authorization header with the new token
          originalRequest.headers.Authorization = `Bearer ${authStore.accessToken}`

          // Retry the original request
          return apiClient(originalRequest)
        } else {
          // Refresh failed - clear auth state and redirect
          authStore.clearAuthState()
          if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
            window.location.href = '/login'
          }
        }
      } catch (refreshError) {
        console.error('Token refresh failed:', refreshError)
        authStore.clearAuthState()

        // Redirect to login on refresh failure
        if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
          window.location.href = '/login'
        }
      }
    }

    // Handle other error types
    if (error.response?.status === 403) {
      console.warn('Access forbidden:', error.response.data)
    } else if (error.response?.status === 422) {
      console.warn('Validation error:', error.response.data)
    } else if (error.response?.status >= 500) {
      console.error('Server error:', error.response.data)
    }

    return Promise.reject(error)
  },
)

// Add request/response logging in development
if (import.meta.env.DEV) {
  apiClient.interceptors.request.use((config) => {
    console.log(`🚀 API Request: ${config.method?.toUpperCase()} ${config.url}`, config.data || config.params)
    return config
  })

  apiClient.interceptors.response.use(
    (response) => {
      console.log(`✅ API Response: ${response.status} ${response.config.method?.toUpperCase()} ${response.config.url}`, response.data)
      return response
    },
    (error) => {
      console.error(`❌ API Error: ${error.response?.status} ${error.config?.method?.toUpperCase()} ${error.config?.url}`, error.response?.data)
      return Promise.reject(error)
    }
  )
}

export { apiClient }
