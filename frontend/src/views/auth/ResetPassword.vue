<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { authApi, getApiErrorMessage, getValidationErrors, isValidationError } from '../../api/auth.js'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

// Form state
const password = ref('')
const confirmPassword = ref('')
const isSubmitting = ref(false)

// UI state
const error = ref(null)
const validationErrors = ref({})
const showSuccess = ref(false)
const token = ref(null)
const tokenError = ref(null)

onMounted(() => {
  // Get token from query parameter
  token.value = route.query.token

  if (!token.value) {
    tokenError.value = t('auth.missingResetToken')
  }
})

const handleResetPassword = async () => {
  if (!validateForm()) {
    return
  }

  if (!token.value) {
    tokenError.value = t('auth.missingResetToken')
    return
  }

  isSubmitting.value = true
  error.value = null
  validationErrors.value = {}

  try {
    await authApi.resetPassword(token.value, password.value)
    showSuccess.value = true
  } catch (err) {
    if (isValidationError(err)) {
      validationErrors.value = getValidationErrors(err)
    } else {
      error.value = getApiErrorMessage(err)
    }
  } finally {
    isSubmitting.value = false
  }
}

const validateForm = () => {
  const errors = {}

  if (!password.value) {
    errors.password = ['Password is required']
  } else if (password.value.length < 8) {
    errors.password = ['Password must be at least 8 characters long']
  } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])/.test(password.value)) {
    errors.password = ['Password must contain uppercase, lowercase, number and special character']
  }

  if (password.value !== confirmPassword.value) {
    errors.password_confirmation = ['Passwords do not match']
  }

  validationErrors.value = errors
  return Object.keys(errors).length === 0
}

const navigateToLogin = () => {
  router.push('/login')
}

const navigateHome = () => {
  router.push('/')
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
            {{ t('auth.resetPasswordTitle') }}
          </h2>
          <p class="mt-2 text-sm text-surface-600">
            {{ t('auth.resetPasswordSubtitle') }}
          </p>
        </div>
      </div>

      <!-- Token Error -->
      <div v-if="tokenError" class="card p-8 bg-danger-50 border-danger-200">
        <div class="text-center">
          <div class="text-danger-600 text-4xl mb-4">⚠️</div>
          <h3 class="text-lg font-medium text-danger-800">
            {{ t('auth.invalidResetLinkTitle') }}
          </h3>
          <p class="mt-2 text-sm text-danger-700">
            {{ tokenError }}
          </p>
          <div class="mt-6">
            <button
              @click="router.push('/forgot-password')"
              class="btn btn-primary"
            >
              {{ t('auth.requestNewLink') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Success Message -->
      <div v-if="showSuccess" class="card p-8 bg-success-50 border-success-200">
        <div class="text-center">
          <div class="text-success-600 text-4xl mb-4">✓</div>
          <h3 class="text-lg font-medium text-success-800">
            {{ t('auth.passwordResetSuccessTitle') }}
          </h3>
          <p class="mt-2 text-sm text-success-700">
            {{ t('auth.passwordResetSuccessMessage') }}
          </p>
          <div class="mt-6">
            <button
              @click="navigateToLogin"
              class="btn btn-primary"
            >
              {{ t('auth.proceedToLogin') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Reset Password Form -->
      <div v-if="!showSuccess && !tokenError" class="card p-8">
        <form @submit.prevent="handleResetPassword" class="space-y-6">
          <!-- New Password Field -->
          <div>
            <label for="password" class="form-label">
              {{ t('auth.newPassword') }}
            </label>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="new-password"
              required
              class="form-input"
              :class="{ 'border-danger-300': validationErrors.password }"
              :disabled="isSubmitting"
            />
            <p v-if="validationErrors.password" class="mt-1 text-sm text-danger-600">
              {{ validationErrors.password[0] }}
            </p>
            <p class="mt-1 text-xs text-surface-500">
              {{ t('auth.passwordRequirements') }}
            </p>
          </div>

          <!-- Confirm Password Field -->
          <div>
            <label for="confirmPassword" class="form-label">
              {{ t('auth.confirmNewPassword') }}
            </label>
            <input
              id="confirmPassword"
              v-model="confirmPassword"
              type="password"
              autocomplete="new-password"
              required
              class="form-input"
              :class="{ 'border-danger-300': validationErrors.password_confirmation }"
              :disabled="isSubmitting"
            />
            <p v-if="validationErrors.password_confirmation" class="mt-1 text-sm text-danger-600">
              {{ validationErrors.password_confirmation[0] }}
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
              :disabled="isSubmitting || !password || !confirmPassword"
              class="w-full btn btn-primary py-3"
            >
              <span v-if="isSubmitting">
                {{ t('auth.resettingPassword') }}
              </span>
              <span v-else>
                {{ t('auth.resetPassword') }}
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