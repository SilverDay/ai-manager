<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

// Form state
const firstName = ref('')
const lastName = ref('')
const email = ref('')
const jobTitle = ref('')
const phone = ref('')
const language = ref('en')
const timezone = ref('UTC')
const isSubmitting = ref(false)
const message = ref('')

onMounted(() => {
  if (authStore.user) {
    firstName.value = authStore.user.first_name || ''
    lastName.value = authStore.user.last_name || ''
    email.value = authStore.user.email || ''
    jobTitle.value = authStore.user.job_title || ''
    phone.value = authStore.user.phone || ''
    language.value = authStore.user.language || 'en'
    timezone.value = authStore.user.timezone || 'UTC'
  }
})

const handleSubmit = async () => {
  isSubmitting.value = true
  message.value = ''

  try {
    // TODO: Implement profile update API call
    await new Promise(resolve => setTimeout(resolve, 1000)) // Placeholder
    message.value = 'Profile updated successfully!'
  } catch (error) {
    message.value = 'Failed to update profile. Please try again.'
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
        <h1 class="text-3xl font-bold text-surface-900">Profile Settings</h1>
        <p class="mt-2 text-surface-600">
          Manage your personal information and preferences.
        </p>
      </div>

      <!-- Profile Form -->
      <div class="card">
        <div class="p-6 border-b border-surface-200">
          <h2 class="text-lg font-semibold text-surface-900">Personal Information</h2>
          <p class="text-sm text-surface-600">Update your profile details.</p>
        </div>

        <form @submit.prevent="handleSubmit" class="p-6 space-y-6">
          <!-- Name Fields -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="firstName" class="form-label">First Name</label>
              <input
                id="firstName"
                v-model="firstName"
                type="text"
                required
                class="form-input"
                :disabled="isSubmitting"
              />
            </div>
            <div>
              <label for="lastName" class="form-label">Last Name</label>
              <input
                id="lastName"
                v-model="lastName"
                type="text"
                required
                class="form-input"
                :disabled="isSubmitting"
              />
            </div>
          </div>

          <!-- Email -->
          <div>
            <label for="email" class="form-label">Email Address</label>
            <input
              id="email"
              v-model="email"
              type="email"
              required
              class="form-input"
              :disabled="isSubmitting"
            />
            <p class="mt-1 text-sm text-surface-500">
              This is your login email address.
            </p>
          </div>

          <!-- Job Title and Phone -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="jobTitle" class="form-label">Job Title</label>
              <input
                id="jobTitle"
                v-model="jobTitle"
                type="text"
                class="form-input"
                :disabled="isSubmitting"
              />
            </div>
            <div>
              <label for="phone" class="form-label">Phone Number</label>
              <input
                id="phone"
                v-model="phone"
                type="tel"
                class="form-input"
                :disabled="isSubmitting"
              />
            </div>
          </div>

          <!-- Language and Timezone -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label for="language" class="form-label">Language</label>
              <select
                id="language"
                v-model="language"
                class="form-input"
                :disabled="isSubmitting"
              >
                <option value="en">English</option>
                <option value="de">Deutsch</option>
                <option value="fr">Français</option>
              </select>
            </div>
            <div>
              <label for="timezone" class="form-label">Timezone</label>
              <select
                id="timezone"
                v-model="timezone"
                class="form-input"
                :disabled="isSubmitting"
              >
                <option value="UTC">UTC</option>
                <option value="Europe/London">Europe/London</option>
                <option value="Europe/Berlin">Europe/Berlin</option>
                <option value="Europe/Paris">Europe/Paris</option>
                <option value="America/New_York">America/New_York</option>
                <option value="America/Los_Angeles">America/Los_Angeles</option>
              </select>
            </div>
          </div>

          <!-- Success/Error Message -->
          <div v-if="message" class="p-4 rounded-md" :class="{
            'bg-success-50 text-success-700 border border-success-200': message.includes('successfully'),
            'bg-danger-50 text-danger-700 border border-danger-200': !message.includes('successfully')
          }">
            {{ message }}
          </div>

          <!-- Submit Button -->
          <div class="flex justify-end space-x-4">
            <button
              type="button"
              @click="goBack"
              class="btn btn-secondary"
              :disabled="isSubmitting"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn btn-primary"
              :disabled="isSubmitting"
            >
              <span v-if="isSubmitting">Saving...</span>
              <span v-else>Save Changes</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>