<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()

// Check if user is superadmin
if (authStore.user?.role_name !== 'Superadmin') {
  router.push('/dashboard')
}

// System settings state
const settings = ref({
  platform_name: 'AI Governance Platform',
  registration_enabled: true,
  email_verification_required: true,
  password_min_length: 12,
  session_timeout: 900,
  max_file_size: 50,
  allowed_file_types: 'pdf,docx,xlsx,png,jpg,zip',
  backup_enabled: true,
  backup_retention_days: 30,
  rate_limit_enabled: true,
  maintenance_mode: false
})

const isSubmitting = ref(false)
const message = ref('')

onMounted(async () => {
  await loadSettings()
})

const loadSettings = async () => {
  try {
    // TODO: Load actual system settings from API
    console.log('Loading system settings...')
  } catch (error) {
    console.error('Failed to load settings:', error)
  }
}

const saveSettings = async () => {
  isSubmitting.value = true
  message.value = ''

  try {
    // TODO: Save settings via API
    await new Promise(resolve => setTimeout(resolve, 1500)) // Placeholder
    message.value = 'Settings saved successfully!'
  } catch (error) {
    message.value = 'Failed to save settings. Please try again.'
    console.error('Failed to save settings:', error)
  } finally {
    isSubmitting.value = false
  }
}

const goBack = () => {
  router.push('/dashboard')
}

const testEmailSettings = async () => {
  console.log('Testing email settings...')
  // TODO: Implement email test
}

const runSystemDiagnostics = async () => {
  console.log('Running system diagnostics...')
  // TODO: Implement system diagnostics
}

const downloadBackup = async () => {
  console.log('Downloading backup...')
  // TODO: Implement backup download
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
              Superadmin: {{ authStore.user?.first_name }} {{ authStore.user?.last_name }}
            </span>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-surface-900">System Settings</h1>
        <p class="mt-2 text-surface-600">
          Configure platform-wide settings and system behavior.
        </p>
      </div>

      <!-- Settings Form -->
      <form @submit.prevent="saveSettings" class="space-y-8">
        <!-- General Settings -->
        <div class="card">
          <div class="p-6 border-b border-surface-200">
            <h2 class="text-lg font-semibold text-surface-900">General Settings</h2>
            <p class="text-sm text-surface-600">Basic platform configuration.</p>
          </div>

          <div class="p-6 space-y-6">
            <div>
              <label for="platformName" class="form-label">Platform Name</label>
              <input
                id="platformName"
                v-model="settings.platform_name"
                type="text"
                required
                class="form-input"
                :disabled="isSubmitting"
              />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="flex items-center">
                <input
                  id="registrationEnabled"
                  v-model="settings.registration_enabled"
                  type="checkbox"
                  class="form-checkbox h-4 w-4"
                  :disabled="isSubmitting"
                />
                <label for="registrationEnabled" class="ml-2 form-label mb-0">
                  Allow new user registration
                </label>
              </div>

              <div class="flex items-center">
                <input
                  id="emailVerificationRequired"
                  v-model="settings.email_verification_required"
                  type="checkbox"
                  class="form-checkbox h-4 w-4"
                  :disabled="isSubmitting"
                />
                <label for="emailVerificationRequired" class="ml-2 form-label mb-0">
                  Require email verification
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- Security Settings -->
        <div class="card">
          <div class="p-6 border-b border-surface-200">
            <h2 class="text-lg font-semibold text-surface-900">Security Settings</h2>
            <p class="text-sm text-surface-600">Password and session security configuration.</p>
          </div>

          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="passwordMinLength" class="form-label">Minimum Password Length</label>
                <input
                  id="passwordMinLength"
                  v-model.number="settings.password_min_length"
                  type="number"
                  min="8"
                  max="50"
                  required
                  class="form-input"
                  :disabled="isSubmitting"
                />
              </div>

              <div>
                <label for="sessionTimeout" class="form-label">Session Timeout (seconds)</label>
                <input
                  id="sessionTimeout"
                  v-model.number="settings.session_timeout"
                  type="number"
                  min="300"
                  max="86400"
                  required
                  class="form-input"
                  :disabled="isSubmitting"
                />
              </div>
            </div>

            <div class="flex items-center">
              <input
                id="rateLimitEnabled"
                v-model="settings.rate_limit_enabled"
                type="checkbox"
                class="form-checkbox h-4 w-4"
                :disabled="isSubmitting"
              />
              <label for="rateLimitEnabled" class="ml-2 form-label mb-0">
                Enable rate limiting
              </label>
            </div>
          </div>
        </div>

        <!-- File Upload Settings -->
        <div class="card">
          <div class="p-6 border-b border-surface-200">
            <h2 class="text-lg font-semibold text-surface-900">File Upload Settings</h2>
            <p class="text-sm text-surface-600">Configure file upload limits and restrictions.</p>
          </div>

          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label for="maxFileSize" class="form-label">Maximum File Size (MB)</label>
                <input
                  id="maxFileSize"
                  v-model.number="settings.max_file_size"
                  type="number"
                  min="1"
                  max="500"
                  required
                  class="form-input"
                  :disabled="isSubmitting"
                />
              </div>

              <div>
                <label for="allowedFileTypes" class="form-label">Allowed File Types</label>
                <input
                  id="allowedFileTypes"
                  v-model="settings.allowed_file_types"
                  type="text"
                  required
                  class="form-input"
                  :disabled="isSubmitting"
                  placeholder="pdf,docx,xlsx,png,jpg"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Backup Settings -->
        <div class="card">
          <div class="p-6 border-b border-surface-200">
            <h2 class="text-lg font-semibold text-surface-900">Backup & Maintenance</h2>
            <p class="text-sm text-surface-600">System backup and maintenance configuration.</p>
          </div>

          <div class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="flex items-center">
                <input
                  id="backupEnabled"
                  v-model="settings.backup_enabled"
                  type="checkbox"
                  class="form-checkbox h-4 w-4"
                  :disabled="isSubmitting"
                />
                <label for="backupEnabled" class="ml-2 form-label mb-0">
                  Enable automatic backups
                </label>
              </div>

              <div>
                <label for="backupRetentionDays" class="form-label">Backup Retention (days)</label>
                <input
                  id="backupRetentionDays"
                  v-model.number="settings.backup_retention_days"
                  type="number"
                  min="1"
                  max="365"
                  required
                  class="form-input"
                  :disabled="isSubmitting"
                />
              </div>
            </div>

            <div class="flex items-center">
              <input
                id="maintenanceMode"
                v-model="settings.maintenance_mode"
                type="checkbox"
                class="form-checkbox h-4 w-4"
                :disabled="isSubmitting"
              />
              <label for="maintenanceMode" class="ml-2 form-label mb-0">
                <span class="text-danger-600">Enable maintenance mode</span>
              </label>
            </div>

            <!-- System Actions -->
            <div class="border-t border-surface-200 pt-6">
              <h3 class="text-sm font-medium text-surface-900 mb-4">System Actions</h3>
              <div class="flex space-x-4">
                <button
                  type="button"
                  @click="testEmailSettings"
                  class="btn btn-secondary"
                >
                  Test Email
                </button>
                <button
                  type="button"
                  @click="runSystemDiagnostics"
                  class="btn btn-secondary"
                >
                  Run Diagnostics
                </button>
                <button
                  type="button"
                  @click="downloadBackup"
                  class="btn btn-secondary"
                >
                  Download Backup
                </button>
              </div>
            </div>
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
            <span v-else>Save Settings</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>