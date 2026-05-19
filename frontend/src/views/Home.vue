<script setup>
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

const navigateToLogin = () => {
  router.push('/login')
}

const navigateToDashboard = () => {
  router.push('/dashboard')
}
</script>

<template>
  <div class="min-h-screen bg-gradient-to-br from-primary-50 to-secondary-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-sm">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
          <div class="flex items-center">
            <h1 class="text-xl font-semibold text-primary-600">
              AI Governance Platform
            </h1>
          </div>
          <div class="flex items-center space-x-4">
            <button
              v-if="!authStore.isAuthenticated"
              @click="navigateToLogin"
              class="btn btn-primary"
            >
              {{ t('auth.login') }}
            </button>
            <button v-else @click="navigateToDashboard" class="btn btn-primary">
              {{ t('common.dashboard') }}
            </button>
          </div>
        </div>
      </div>
    </nav>

    <!-- Hero Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
      <div class="text-center">
        <h1 class="text-4xl font-bold text-surface-900 sm:text-5xl md:text-6xl">
          EU AI Act
          <span class="text-primary-600">Compliance</span>
          Platform
        </h1>
        <p
          class="mt-3 max-w-md mx-auto text-base text-surface-500 sm:text-lg md:mt-5 md:text-xl md:max-w-3xl"
        >
          Manage AI systems in accordance with the EU AI Act. Streamlined
          workflows for classification, risk assessment, and compliance
          documentation.
        </p>
        <div class="mt-5 max-w-md mx-auto sm:flex sm:justify-center md:mt-8">
          <button
            v-if="!authStore.isAuthenticated"
            @click="navigateToLogin"
            class="btn btn-primary text-lg px-8 py-3"
          >
            Get Started
          </button>
          <button
            v-else
            @click="navigateToDashboard"
            class="btn btn-primary text-lg px-8 py-3"
          >
            Go to Dashboard
          </button>
        </div>
      </div>
    </div>

    <!-- Features Section -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="card p-6">
          <h3 class="text-lg font-semibold text-surface-900 mb-3">
            AI System Registration
          </h3>
          <p class="text-surface-600">
            Register and classify AI systems according to EU AI Act
            requirements.
          </p>
        </div>
        <div class="card p-6">
          <h3 class="text-lg font-semibold text-surface-900 mb-3">
            Risk Assessment
          </h3>
          <p class="text-surface-600">
            Conduct comprehensive risk assessments with guided workflows.
          </p>
        </div>
        <div class="card p-6">
          <h3 class="text-lg font-semibold text-surface-900 mb-3">
            Compliance Tracking
          </h3>
          <p class="text-surface-600">
            Track compliance status and maintain audit-ready documentation.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
