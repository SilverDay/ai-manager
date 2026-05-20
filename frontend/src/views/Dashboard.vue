<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

// User dropdown state
const showUserDropdown = ref(false)

const handleLogout = async () => {
  const result = await authStore.logout()
  if (result.success) {
    router.push('/')
  }
}

const navigateHome = () => {
  router.push('/')
}

const navigateToProfile = () => {
  showUserDropdown.value = false
  router.push('/profile')
}

const navigateToSettings = () => {
  showUserDropdown.value = false
  router.push('/settings')
}

const navigateToTenantManagement = () => {
  showUserDropdown.value = false
  router.push('/superadmin/tenants')
}

const navigateToSystemSettings = () => {
  showUserDropdown.value = false
  router.push('/superadmin/system')
}

// Close dropdown when clicking outside
const closeDropdown = () => {
  showUserDropdown.value = false
}
</script>

<template>
  <div class="min-h-screen bg-surface-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <button
              @click="navigateHome"
              class="text-xl font-semibold text-primary-600"
            >
              AI Governance Platform
            </button>
          </div>
          <div class="flex items-center space-x-4">
            <!-- Superadmin Links -->
            <div v-if="authStore.user?.role_name === 'Superadmin'" class="flex items-center space-x-2">
              <router-link
                to="/superadmin/tenants"
                class="text-sm font-medium text-primary-600 hover:text-primary-700"
              >
                Tenant Management
              </router-link>
              <span class="text-surface-300">|</span>
              <router-link
                to="/superadmin/system"
                class="text-sm font-medium text-primary-600 hover:text-primary-700"
              >
                System Settings
              </router-link>
              <span class="text-surface-300">|</span>
            </div>

            <!-- Admin Links -->
            <div v-if="authStore.user?.role_name === 'Admin' || authStore.user?.role_name === 'Superadmin'" class="flex items-center space-x-2">
              <router-link
                to="/admin/pending-users"
                class="text-sm font-medium text-primary-600 hover:text-primary-700"
              >
                Pending Users
              </router-link>
              <span class="text-surface-300">|</span>
              <router-link
                to="/admin/invitations"
                class="text-sm font-medium text-primary-600 hover:text-primary-700"
              >
                Invitations
              </router-link>
            </div>

            <!-- User Dropdown -->
            <div v-if="authStore.user" class="relative">
              <button
                @click="showUserDropdown = !showUserDropdown"
                class="flex items-center space-x-1 text-sm text-surface-700 hover:text-surface-900 focus:outline-none"
              >
                <span>{{ authStore.user.first_name }} {{ authStore.user.last_name }}</span>
                <span class="text-xs">▼</span>
              </button>

              <!-- Dropdown Menu -->
              <div v-if="showUserDropdown">
                <!-- Click overlay to close dropdown -->
                <div
                  @click="closeDropdown"
                  class="fixed inset-0 z-10"
                ></div>
                <!-- Dropdown content -->
                <div class="absolute right-0 top-full mt-1 w-48 bg-white rounded-md shadow-lg border border-surface-200 py-1 z-20">
                  <div class="px-4 py-2 text-xs text-surface-500 border-b border-surface-100">
                    {{ authStore.user.email }}<br>
                    <span class="font-medium">{{ authStore.user.role_name }}</span>
                  </div>
                  <button
                    @click="navigateToProfile"
                    class="block w-full text-left px-4 py-2 text-sm text-surface-700 hover:bg-surface-50"
                  >
                    Profile Settings
                  </button>
                  <button
                    @click="navigateToSettings"
                    class="block w-full text-left px-4 py-2 text-sm text-surface-700 hover:bg-surface-50"
                  >
                    Change Password
                  </button>
                  <div class="border-t border-surface-100 mt-1 pt-1">
                    <button
                      @click="handleLogout"
                      class="block w-full text-left px-4 py-2 text-sm text-danger-600 hover:bg-danger-50"
                    >
                      {{ t('auth.logout') }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Page Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-surface-900">
          {{ t('dashboard.title') }}
        </h1>
        <p class="mt-2 text-surface-600">
          {{ t('dashboard.welcome') }}
        </p>
      </div>

      <!-- Dashboard Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- AI Systems Card -->
        <div class="card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-surface-600">
                {{ t('dashboard.aiSystems') }}
              </p>
              <p class="text-3xl font-bold text-surface-900">12</p>
            </div>
            <div
              class="w-12 h-12 bg-primary-100 rounded-lg flex items-center justify-center"
            >
              <span class="text-primary-600 text-xl">🤖</span>
            </div>
          </div>
        </div>

        <!-- Risk Assessments Card -->
        <div class="card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-surface-600">
                {{ t('dashboard.riskAssessments') }}
              </p>
              <p class="text-3xl font-bold text-surface-900">8</p>
            </div>
            <div
              class="w-12 h-12 bg-warning-100 rounded-lg flex items-center justify-center"
            >
              <span class="text-warning-600 text-xl">⚠️</span>
            </div>
          </div>
        </div>

        <!-- Pending Approvals Card -->
        <div class="card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-surface-600">
                {{ t('dashboard.approvalsPending') }}
              </p>
              <p class="text-3xl font-bold text-surface-900">3</p>
            </div>
            <div
              class="w-12 h-12 bg-danger-100 rounded-lg flex items-center justify-center"
            >
              <span class="text-danger-600 text-xl">⏳</span>
            </div>
          </div>
        </div>

        <!-- Recent Activity Card -->
        <div class="card p-6">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-medium text-surface-600">
                {{ t('dashboard.recentActivity') }}
              </p>
              <p class="text-3xl font-bold text-surface-900">24</p>
            </div>
            <div
              class="w-12 h-12 bg-success-100 rounded-lg flex items-center justify-center"
            >
              <span class="text-success-600 text-xl">📊</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Area (placeholder) -->
      <div class="card p-8 text-center">
        <h3 class="text-lg font-semibold text-surface-900 mb-3">
          Dashboard Content
        </h3>
        <p class="text-surface-600">
          Full dashboard functionality will be implemented in subsequent
          sprints. This is the baseline Vue 3 + Vite + Pinia + Router + Tailwind
          + i18n setup.
        </p>
      </div>
    </div>
  </div>
</template>
