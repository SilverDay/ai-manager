<script setup>
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

// Password change form
const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const isSubmitting = ref(false)
const message = ref('')

const handlePasswordChange = async () => {
  if (newPassword.value !== confirmPassword.value) {
    message.value = 'New passwords do not match.'
    return
  }

  if (newPassword.value.length < 8) {
    message.value = 'Password must be at least 8 characters long.'
    return
  }

  isSubmitting.value = true
  message.value = ''

  try {
    // TODO: Implement password change API call
    await new Promise(resolve => setTimeout(resolve, 1500)) // Placeholder
    message.value = 'Password changed successfully!'

    // Clear form
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
  } catch (error) {
    message.value = 'Failed to change password. Please check your current password and try again.'
  } finally {
    isSubmitting.value = false
  }
}

const goBack = () => {
  router.push('/dashboard')
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
              @click="goBack"
              class="text-xl font-semibold text-primary-600"
            >
              ← AI Governance Platform
            </button>
          </div>
          <div class="flex items-center">
            <span class="text-sm text-surface-600">
              {{ authStore.user?.first_name }} {{ authStore.user?.last_name }}
            </span>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-surface-900">Account Settings</h1>
        <p class="mt-2 text-surface-600">
          Manage your security settings and account preferences.
        </p>
      </div>

      <!-- Security Settings -->
      <div class="space-y-8">
        <!-- Password Change -->
        <div class="card">
          <div class="p-6 border-b border-surface-200">
            <h2 class="text-lg font-semibold text-surface-900">Change Password</h2>
            <p class="text-sm text-surface-600">Update your password to keep your account secure.</p>
          </div>

          <form @submit.prevent="handlePasswordChange" class="p-6 space-y-6">
            <!-- Current Password -->
            <div>
              <label for="currentPassword" class="form-label">Current Password</label>
              <input
                id="currentPassword"
                v-model="currentPassword"
                type="password"
                required
                class="form-input"
                :disabled="isSubmitting"
                autocomplete="current-password"
              />
            </div>

            <!-- New Password -->
            <div>
              <label for="newPassword" class="form-label">New Password</label>
              <input
                id="newPassword"
                v-model="newPassword"
                type="password"
                required
                class="form-input"
                :disabled="isSubmitting"
                autocomplete="new-password"
              />
              <p class="mt-1 text-sm text-surface-500">
                Must be at least 8 characters with uppercase, lowercase, numbers, and special characters.
              </p>
            </div>

            <!-- Confirm New Password -->
            <div>
              <label for="confirmPassword" class="form-label">Confirm New Password</label>
              <input
                id="confirmPassword"
                v-model="confirmPassword"
                type="password"
                required
                class="form-input"
                :disabled="isSubmitting"
                autocomplete="new-password"
              />
            </div>

            <!-- Success/Error Message -->
            <div v-if="message" class="p-4 rounded-md" :class="{
              'bg-success-50 text-success-700 border border-success-200': message.includes('successfully'),
              'bg-danger-50 text-danger-700 border border-danger-200': !message.includes('successfully')
            }">
              {{ message }}
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end">
              <button
                type="submit"
                class="btn btn-primary"
                :disabled="isSubmitting || !currentPassword || !newPassword || !confirmPassword"
              >
                <span v-if="isSubmitting">Changing Password...</span>
                <span v-else>Change Password</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Account Information -->
        <div class="card">
          <div class="p-6 border-b border-surface-200">
            <h2 class="text-lg font-semibold text-surface-900">Account Information</h2>
            <p class="text-sm text-surface-600">View your account details and role information.</p>
          </div>

          <div class="p-6 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="form-label">Email Address</label>
                <div class="p-3 bg-surface-50 rounded border">
                  {{ authStore.user?.email }}
                </div>
              </div>
              <div>
                <label class="form-label">Role</label>
                <div class="p-3 bg-surface-50 rounded border">
                  {{ authStore.user?.role_name }}
                </div>
              </div>
            </div>

            <div v-if="authStore.user?.tenant_name" class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="form-label">Organization</label>
                <div class="p-3 bg-surface-50 rounded border">
                  {{ authStore.user.tenant_name }}
                </div>
              </div>
              <div>
                <label class="form-label">Account Status</label>
                <div class="p-3 bg-surface-50 rounded border">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-success-100 text-success-800">
                    Active
                  </span>
                </div>
              </div>
            </div>

            <div>
              <label class="form-label">Last Login</label>
              <div class="p-3 bg-surface-50 rounded border">
                {{ authStore.user?.last_login_at || 'First time login' }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>