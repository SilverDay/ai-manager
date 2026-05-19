<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const password = ref('')
const isSubmitting = ref(false)

const handleLogin = async () => {
  if (!email.value || !password.value) {
    return
  }

  isSubmitting.value = true

  const result = await authStore.login(email.value, password.value)

  if (result.success) {
    router.push('/dashboard')
  }

  isSubmitting.value = false
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
            {{ t('auth.loginTitle') }}
          </h2>
          <p class="mt-2 text-sm text-surface-600">
            {{ t('auth.loginSubtitle') }}
          </p>
        </div>
      </div>

      <!-- Login Form -->
      <div class="card p-8">
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
    </div>
  </div>
</template>
