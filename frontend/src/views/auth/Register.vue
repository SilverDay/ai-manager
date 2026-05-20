<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { authApi, getApiErrorMessage, getValidationErrors, isValidationError } from '../../api/auth.js'

const { t } = useI18n()
const router = useRouter()

// Form state
const firstName = ref('')
const lastName = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const isSubmitting = ref(false)

// UI state
const error = ref(null)
const validationErrors = ref({})
const showSuccess = ref(false)
const requiresApproval = ref(false)

const handleRegister = async () => {
  if (!validateForm()) {
    return
  }

  isSubmitting.value = true
  error.value = null
  validationErrors.value = {}

  try {
    const response = await authApi.register({
      first_name: firstName.value,
      last_name: lastName.value,
      email: email.value,
      password: password.value
    })

    showSuccess.value = true
    requiresApproval.value = response.data.requires_approval

    // Clear form
    firstName.value = ''
    lastName.value = ''
    email.value = ''
    password.value = ''
    confirmPassword.value = ''

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

  if (!firstName.value.trim()) {
    errors.first_name = ['First name is required']
  }

  if (!lastName.value.trim()) {
    errors.last_name = ['Last name is required']
  }

  if (!email.value.trim()) {
    errors.email = ['Email is required']
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value)) {
    errors.email = ['Please enter a valid email address']
  }

  if (!password.value) {
    errors.password = ['Password is required']
  } else if (password.value.length < 8) {
    errors.password = ['Password must be at least 8 characters long']
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
            {{ t('auth.registerTitle') }}
          </h2>
          <p class="mt-2 text-sm text-surface-600">
            {{ t('auth.registerSubtitle') }}
          </p>
        </div>
      </div>

      <!-- Success Message -->
      <div v-if="showSuccess" class="card p-8 bg-success-50 border-success-200">
        <div class="text-center">
          <div class="text-success-600 text-4xl mb-4">✓</div>
          <h3 class="text-lg font-medium text-success-800">
            {{ t('auth.registrationSuccessTitle') }}
          </h3>
          <p class="mt-2 text-sm text-success-700">
            {{ requiresApproval
                ? t('auth.registrationPendingApproval')
                : t('auth.registrationSuccess')
            }}
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

      <!-- Registration Form -->
      <div v-if="!showSuccess" class="card p-8">
        <form @submit.prevent="handleRegister" class="space-y-6">
          <!-- Name Fields -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="firstName" class="form-label">
                {{ t('auth.firstName') }}
              </label>
              <input
                id="firstName"
                v-model="firstName"
                type="text"
                autocomplete="given-name"
                required
                class="form-input"
                :class="{ 'border-danger-300': validationErrors.first_name }"
                :disabled="isSubmitting"
              />
              <p v-if="validationErrors.first_name" class="mt-1 text-sm text-danger-600">
                {{ validationErrors.first_name[0] }}
              </p>
            </div>
            <div>
              <label for="lastName" class="form-label">
                {{ t('auth.lastName') }}
              </label>
              <input
                id="lastName"
                v-model="lastName"
                type="text"
                autocomplete="family-name"
                required
                class="form-input"
                :class="{ 'border-danger-300': validationErrors.last_name }"
                :disabled="isSubmitting"
              />
              <p v-if="validationErrors.last_name" class="mt-1 text-sm text-danger-600">
                {{ validationErrors.last_name[0] }}
              </p>
            </div>
          </div>

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
              :class="{ 'border-danger-300': validationErrors.email }"
              :disabled="isSubmitting"
            />
            <p v-if="validationErrors.email" class="mt-1 text-sm text-danger-600">
              {{ validationErrors.email[0] }}
            </p>
            <p class="mt-1 text-xs text-surface-500">
              {{ t('auth.domainBasedRegistrationHint') }}
            </p>
          </div>

          <!-- Password Fields -->
          <div>
            <label for="password" class="form-label">
              {{ t('auth.password') }}
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
          </div>

          <div>
            <label for="confirmPassword" class="form-label">
              {{ t('auth.confirmPassword') }}
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

          <!-- General Error Display -->
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
              :disabled="isSubmitting"
              class="w-full btn btn-primary py-3"
            >
              <span v-if="isSubmitting">
                {{ t('auth.registering') }}
              </span>
              <span v-else>
                {{ t('auth.register') }}
              </span>
            </button>
          </div>
        </form>

        <!-- Navigation Links -->
        <div class="mt-6 text-center space-y-2">
          <div>
            <span class="text-surface-600">{{ t('auth.alreadyHaveAccount') }}</span>
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