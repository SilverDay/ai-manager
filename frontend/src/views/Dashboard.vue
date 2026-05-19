<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

const handleLogout = async () => {
  const result = await authStore.logout()
  if (result.success) {
    router.push('/')
  }
}

const navigateHome = () => {
  router.push('/')
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
            <span v-if="authStore.user" class="text-sm text-surface-600">
              Welcome, {{ authStore.user.name }}
            </span>
            <button @click="handleLogout" class="btn btn-secondary">
              {{ t('auth.logout') }}
            </button>
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
