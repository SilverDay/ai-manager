import axios from 'axios'

// Create axios instance
const apiClient = axios.create({
  baseURL: '/api',
  timeout: 10000,
  headers: {
    'Content-Type': 'application/json',
  },
})

// Request interceptor to add auth token
apiClient.interceptors.request.use(
  (config) => {
    // TODO: Get token from auth store in Sprint 1
    // const authStore = useAuthStore()
    // if (authStore.accessToken) {
    //   config.headers.Authorization = `Bearer ${authStore.accessToken}`
    // }
    return config
  },
  (error) => {
    return Promise.reject(error)
  },
)

// Response interceptor for error handling
apiClient.interceptors.response.use(
  (response) => {
    return response
  },
  async (error) => {
    const originalRequest = error.config

    // Handle 401 errors (token refresh will be implemented in Sprint 1)
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true
      // TODO: Implement token refresh logic in Sprint 1
    }

    return Promise.reject(error)
  },
)

export { apiClient }
