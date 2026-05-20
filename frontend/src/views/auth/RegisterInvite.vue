<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { authApi, getApiErrorMessage, getValidationErrors, isValidationError } from '../../api/auth.js'

const { t } = useI18n()
const router = useRouter()
const route = useRoute()

// Form state
const firstName = ref('')
const lastName = ref('')
const password = ref('')
const confirmPassword = ref('')
const isSubmitting = ref(false)

// UI state
const error = ref(null)
const validationErrors = ref({})
const showSuccess = ref(false)
const token = ref(null)
const tokenError = ref(null)
const invitationInfo = ref(null)

onMounted(() => {
  // Get token from route parameter
  token.value = route.params.token

  if (!token.value) {
    tokenError.value = t('auth.missingInvitationToken')
  }

  // TODO: In a real implementation, you would validate the token
  // and fetch invitation details to show the user what they're accepting
  if (token.value) {
    invitationInfo.value = {
      email: 'user@company.com', // This would come from API
      organization: 'Company Name', // This would come from API
      role: 'Admin' // This would come from API
    }
  }
})

const handleRegisterWithInvite = async () => {
  if (!validateForm()) {
    return
  }

  if (!token.value) {
    tokenError.value = t('auth.missingInvitationToken')
    return
  }

  isSubmitting.value = true
  error.value = null
  validationErrors.value = {}

  try {
    await authApi.registerWithInvite(token.value, {
      first_name: firstName.value,
      last_name: lastName.value,
      password: password.value
    })

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

  if (!firstName.value.trim()) {
    errors.first_name = ['First name is required']
  }

  if (!lastName.value.trim()) {
    errors.last_name = ['Last name is required']
  }

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
            {{ t('auth.inviteRegisterTitle') }}
          </h2>
          <p class="mt-2 text-sm text-surface-600">
            {{ t('auth.inviteRegisterSubtitle') }}
          </p>
        </div>
      </div>

      <!-- Token Error -->
      <div v-if="tokenError" class="card p-8 bg-danger-50 border-danger-200">
        <div class="text-center">
          <div class="text-danger-600 text-4xl mb-4">⚠️</div>
          <h3 class="text-lg font-medium text-danger-800">
            {{ t('auth.invalidInvitationTitle') }}
          </h3>
          <p class="mt-2 text-sm text-danger-700">
            {{ tokenError }}
          </p>
          <div class="mt-6 space-y-3">
            <button
              @click="navigateToLogin"
              class="w-full btn btn-primary"
            >
              {{ t('auth.proceedToLogin') }}
            </button>
            <button
              @click="router.push('/register')"
              class="w-full btn btn-outline"
            >
              {{ t('auth.registerNormally') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Success Message -->
      <div v-if="showSuccess" class="card p-8 bg-success-50 border-success-200">
        <div class="text-center">
          <div class="text-success-600 text-4xl mb-4">✓</div>
          <h3 class="text-lg font-medium text-success-800">
            {{ t('auth.inviteRegistrationSuccessTitle') }}
          </h3>
          <p class="mt-2 text-sm text-success-700">
            {{ t('auth.inviteRegistrationSuccessMessage') }}
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

      <!-- Invitation Info -->
      <div v-if="invitationInfo && !showSuccess && !tokenError" class="card p-6 bg-primary-50 border-primary-200">
        <h3 class="text-lg font-medium text-primary-800 mb-3">
          {{ t('auth.invitationDetails') }}
        </h3>
        <div class="space-y-2 text-sm">
          <div>
            <span class="text-primary-600 font-medium">{{ t('auth.email') }}:</span>
            <span class="text-primary-800 ml-2">{{ invitationInfo.email }}</span>
          </div>
          <div>
            <span class="text-primary-600 font-medium">{{ t('auth.organization') }}:</span>
            <span class="text-primary-800 ml-2">{{ invitationInfo.organization }}</span>
          </div>
          <div>
            <span class="text-primary-600 font-medium">{{ t('auth.role') }}:</span>
            <span class="text-primary-800 ml-2">{{ invitationInfo.role }}</span>
          </div>
        </div>
      </div>

      <!-- Registration Form -->
      <div v-if="!showSuccess && !tokenError" class="card p-8">
        <form @submit.prevent="handleRegisterWithInvite" class="space-y-6">
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
            <p class="mt-1 text-xs text-surface-500">
              {{ t('auth.passwordRequirements') }}
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
              :disabled="isSubmitting"
              class="w-full btn btn-primary py-3"
            >
              <span v-if="isSubmitting">
                {{ t('auth.acceptingInvitation') }}
              </span>
              <span v-else>
                {{ t('auth.acceptInvitation') }}
              </span>
            </button>
          </div>
        </form>

        <!-- Navigation Links -->
        <div class="mt-6 text-center">
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
</template>