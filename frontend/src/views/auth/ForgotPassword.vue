<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { authApi, getApiErrorMessage } from '../../api/auth.js'

const { t } = useI18n()
const router = useRouter()

// Form state
const email = ref('')
const isSubmitting = ref(false)

// UI state
const error = ref(null)
const showSuccess = ref(false)

const handleForgotPassword = async () => {
  if (!email.value.trim()) {
    error.value = t('auth.emailRequired')
    return
  }

  isSubmitting.value = true
  error.value = null

  try {
    await authApi.forgotPassword(email.value)
    showSuccess.value = true
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    isSubmitting.value = false
  }
}

const navigateToLogin = () => {
  router.push('/login')
}

const navigateHome = () => {
  router.push('/')
}

const sendAnother = () => {
  showSuccess.value = false
  email.value = ''
  error.value = null
}
</script>

<template>
  <div
    class="min-h-screen flex items-center justify-center bg-surface-50 py-12 px-4 sm:px-6 lg:px-8"
  >
    <div class="max-w-md w-full space-y-8">
      <!-- Header -->
      <div>
        <div class="text-center">
          <h2 class="text-3xl font-bold text-surface-900">
            {{ t('auth.forgotPasswordTitle') }}
          </h2>
          <p class="mt-2 text-sm text-surface-600">
            {{ t('auth.forgotPasswordSubtitle') }}
          </p>
        </div>
      </div>

      <!-- Success Message -->
      <div v-if="showSuccess" class="card p-8 bg-primary-50 border-primary-200">
        <div class="text-center">
          <div class="text-primary-600 text-4xl mb-4">📧</div>
          <h3 class="text-lg font-medium text-primary-800">
            {{ t('auth.resetEmailSentTitle') }}
          </h3>
          <p class="mt-2 text-sm text-primary-700">
            {{ t('auth.resetEmailSentMessage') }}
          </p>
          <div class="mt-6 space-y-3">
            <button
              @click="navigateToLogin"
              class="w-full btn btn-primary"
            >
              {{ t('auth.backToLogin') }}
            </button>
            <button
              @click="sendAnother"
              class="w-full btn btn-outline"
            >
              {{ t('auth.sendAnotherEmail') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Forgot Password Form -->
      <div v-if="!showSuccess" class="card p-8">
        <form @submit.prevent="handleForgotPassword" class="space-y-6">
          <!-- Email Field -->
          <div>
            <label for="email" class="form-label">
              {{ t('auth.email') }}
            </label>
            <input
              id="email"
              v-model="email"
              type="email"
              autocomplete="email"
              required
              class="form-input"
              :disabled="isSubmitting"
              :placeholder="t('auth.emailPlaceholder')"
            />
            <p class="mt-1 text-sm text-surface-600">
              {{ t('auth.resetEmailHint') }}
            </p>
          </div>

          <!-- Error Display -->
          <div
            v-if="error"
            class="bg-danger-50 border border-danger-200 text-danger-700 px-4 py-3 rounded"
          >
            {{ error }}
          </div>

          <!-- Submit Button -->
          <div>
            <button
              type="submit"
              :disabled="isSubmitting || !email"
              class="w-full btn btn-primary py-3"
            >
              <span v-if="isSubmitting">
                {{ t('auth.sendingResetEmail') }}
              </span>
              <span v-else>
                {{ t('auth.sendResetEmail') }}
              </span>
            </button>
          </div>
        </form>

        <!-- Navigation Links -->
        <div class="mt-6 text-center space-y-2">
          <div>
            <span class="text-surface-600">{{ t('auth.rememberPassword') }}</span>
            <button
              @click="navigateToLogin"
              class="text-primary-600 hover:text-primary-500 ml-1"
            >
              {{ t('auth.login') }}
            </button>
          </div>
          <div>
            <button
              @click="navigateHome"
              class="text-surface-500 hover:text-surface-400"
            >
              ← {{ t('common.back') }} to {{ t('common.home') }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>