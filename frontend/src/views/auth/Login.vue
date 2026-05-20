<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

// Login form state
const email = ref('')
const password = ref('')
const isSubmitting = ref(false)

// MFA state
const mfaCode = ref('')
const showMfaForm = ref(false)
const mfaChallenge = ref(null)

const handleLogin = async () => {
  if (!email.value || !password.value) {
    return
  }

  console.log('🚀 Login form: Starting login process')
  isSubmitting.value = true
  authStore.clearError()

  try {
    const result = await authStore.login(email.value, password.value)
    console.log('🚀 Login form: Auth store result:', result)

    if (result.success) {
      console.log('🚀 Login form: Direct login success, navigating to dashboard')
      // Direct login success (tenant users)
      router.push('/dashboard')
    } else if (result.mfa_required) {
      console.log('🚀 Login form: MFA required, showing MFA form')
      // MFA required (superadmin users)
      showMfaForm.value = true
      mfaChallenge.value = result.challenge
    } else {
      console.log('🚀 Login form: Login failed with error:', result.error)
    }
  } catch (err) {
    console.error('🚀 Login form: Unexpected error:', err)
  }

  isSubmitting.value = false
}

const handleMfaVerification = async () => {
  if (!mfaCode.value || !mfaChallenge.value) {
    return
  }

  isSubmitting.value = true
  authStore.clearError()

  const result = await authStore.verifyMfa(mfaCode.value)

  if (result.success) {
    router.push('/dashboard')
  }

  isSubmitting.value = false
}

const backToLogin = () => {
  showMfaForm.value = false
  mfaChallenge.value = null
  mfaCode.value = ''
  authStore.clearError()
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
            {{ showMfaForm ? t('auth.mfaTitle') : t('auth.loginTitle') }}
          </h2>
          <p class="mt-2 text-sm text-surface-600">
            {{ showMfaForm ? t('auth.mfaSubtitle') : t('auth.loginSubtitle') }}
          </p>
        </div>
      </div>

      <!-- Login Form -->
      <div class="card p-8" v-if="!showMfaForm">
        <form @submit.prevent="handleLogin" class="space-y-6">
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
            />
          </div>

          <!-- Password Field -->
          <div>
            <label for="password" class="form-label">
              {{ t('auth.password') }}
            </label>
            <input
              id="password"
              v-model="password"
              type="password"
              autocomplete="current-password"
              required
              class="form-input"
              :disabled="isSubmitting"
            />
          </div>

          <!-- Error Display -->
          <div
            v-if="authStore.error"
            class="bg-danger-50 border border-danger-200 text-danger-700 px-4 py-3 rounded"
          >
            {{ authStore.error }}
          </div>

          <!-- Submit Button -->
          <div>
            <button
              type="submit"
              :disabled="isSubmitting || !email || !password"
              class="w-full btn btn-primary py-3"
            >
              <span v-if="isSubmitting">
                {{ t('common.loading') }}
              </span>
              <span v-else>
                {{ t('auth.login') }}
              </span>
            </button>
          </div>
        </form>

        <!-- Back to Home -->
        <div class="mt-6 text-center">
          <button
            @click="navigateHome"
            class="text-primary-600 hover:text-primary-500"
          >
            ← {{ t('common.back') }} to {{ t('common.home') }}
          </button>
        </div>
      </div>

      <!-- MFA Verification Form -->
      <div class="card p-8" v-if="showMfaForm">
        <form @submit.prevent="handleMfaVerification" class="space-y-6">
          <!-- MFA Code Field -->
          <div>
            <label for="mfaCode" class="form-label">
              {{ t('auth.mfaCode') }}
            </label>
            <input
              id="mfaCode"
              v-model="mfaCode"
              type="text"
              autocomplete="one-time-code"
              required
              maxlength="6"
              class="form-input text-center text-lg tracking-wider"
              placeholder="000000"
              :disabled="isSubmitting"
            />
            <p class="mt-2 text-sm text-surface-600">
              {{ t('auth.mfaCodeHint') }}
            </p>
          </div>

          <!-- Error Display -->
          <div
            v-if="authStore.error"
            class="bg-danger-50 border border-danger-200 text-danger-700 px-4 py-3 rounded"
          >
            {{ authStore.error }}
          </div>

          <!-- Submit Button -->
          <div>
            <button
              type="submit"
              :disabled="isSubmitting || !mfaCode || mfaCode.length !== 6"
              class="w-full btn btn-primary py-3"
            >
              <span v-if="isSubmitting">
                {{ t('auth.verifying') }}
              </span>
              <span v-else>
                {{ t('auth.verifyMfa') }}
              </span>
            </button>
          </div>
        </form>

        <!-- Back to Login -->
        <div class="mt-6 text-center">
          <button
            @click="backToLogin"
            class="text-primary-600 hover:text-primary-500"
          >
            ← {{ t('auth.backToLogin') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
