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

// State
const tenants = ref([])
const loading = ref(true)
const showCreateModal = ref(false)
const newTenant = ref({
  name: '',
  subdomain: '',
  admin_email: '',
  admin_first_name: '',
  admin_last_name: ''
})

onMounted(async () => {
  await loadTenants()
})

const loadTenants = async () => {
  loading.value = true
  try {
    // TODO: Implement API call to load tenants
    await new Promise(resolve => setTimeout(resolve, 1000)) // Placeholder

    // Mock data for now
    tenants.value = [
      {
        id: 1,
        name: 'SilverDay Media',
        subdomain: 'silverday',
        status: 'active',
        admin_count: 2,
        user_count: 15,
        created_at: '2026-01-15'
      },
      {
        id: 2,
        name: 'Tech Corp Inc',
        subdomain: 'techcorp',
        status: 'active',
        admin_count: 1,
        user_count: 8,
        created_at: '2026-02-10'
      }
    ]
  } catch (error) {
    console.error('Failed to load tenants:', error)
  } finally {
    loading.value = false
  }
}

const createTenant = async () => {
  try {
    // TODO: Implement tenant creation API call
    await new Promise(resolve => setTimeout(resolve, 1500)) // Placeholder

    showCreateModal.value = false
    newTenant.value = {
      name: '',
      subdomain: '',
      admin_email: '',
      admin_first_name: '',
      admin_last_name: ''
    }
    await loadTenants()
  } catch (error) {
    console.error('Failed to create tenant:', error)
  }
}

const goBack = () => {
  router.push('/dashboard')
}

const viewTenant = (tenant) => {
  console.log('View tenant:', tenant)
  // TODO: Navigate to tenant detail view
}

const deactivateTenant = async (tenant) => {
  if (confirm(`Are you sure you want to deactivate ${tenant.name}?`)) {
    // TODO: Implement tenant deactivation
    console.log('Deactivate tenant:', tenant)
  }
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
      <div class="mb-8 flex justify-between items-center">
        <div>
          <h1 class="text-3xl font-bold text-surface-900">Tenant Management</h1>
          <p class="mt-2 text-surface-600">
            Manage organizations and their access to the AI Governance Platform.
          </p>
        </div>
        <button
          @click="showCreateModal = true"
          class="btn btn-primary"
        >
          Create New Tenant
        </button>
      </div>

      <!-- Tenants List -->
      <div class="card">
        <div class="p-6 border-b border-surface-200">
          <h2 class="text-lg font-semibold text-surface-900">All Tenants</h2>
        </div>

        <div v-if="loading" class="p-8 text-center">
          <div class="text-surface-500">Loading tenants...</div>
        </div>

        <div v-else-if="tenants.length === 0" class="p-8 text-center">
          <div class="text-surface-500">No tenants found.</div>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-surface-50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                  Organization
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                  Subdomain
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                  Status
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                  Users
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                  Created
                </th>
                <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-surface-200">
              <tr v-for="tenant in tenants" :key="tenant.id" class="hover:bg-surface-50">
                <td class="px-6 py-4">
                  <div class="font-medium text-surface-900">{{ tenant.name }}</div>
                </td>
                <td class="px-6 py-4 text-sm text-surface-600">
                  {{ tenant.subdomain }}.example.com
                </td>
                <td class="px-6 py-4">
                  <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" :class="{
                    'bg-success-100 text-success-800': tenant.status === 'active',
                    'bg-danger-100 text-danger-800': tenant.status === 'inactive'
                  }">
                    {{ tenant.status }}
                  </span>
                </td>
                <td class="px-6 py-4 text-sm text-surface-600">
                  {{ tenant.user_count }} users ({{ tenant.admin_count }} admins)
                </td>
                <td class="px-6 py-4 text-sm text-surface-600">
                  {{ tenant.created_at }}
                </td>
                <td class="px-6 py-4 text-sm space-x-2">
                  <button
                    @click="viewTenant(tenant)"
                    class="text-primary-600 hover:text-primary-700"
                  >
                    View
                  </button>
                  <button
                    @click="deactivateTenant(tenant)"
                    class="text-danger-600 hover:text-danger-700"
                  >
                    Deactivate
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Create Tenant Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center">
      <div class="fixed inset-0 bg-surface-500 bg-opacity-50" @click="showCreateModal = false"></div>
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4 z-10">
        <div class="p-6">
          <h3 class="text-lg font-semibold text-surface-900 mb-4">Create New Tenant</h3>

          <form @submit.prevent="createTenant" class="space-y-4">
            <div>
              <label for="tenantName" class="form-label">Organization Name</label>
              <input
                id="tenantName"
                v-model="newTenant.name"
                type="text"
                required
                class="form-input"
              />
            </div>

            <div>
              <label for="subdomain" class="form-label">Subdomain</label>
              <input
                id="subdomain"
                v-model="newTenant.subdomain"
                type="text"
                required
                class="form-input"
                placeholder="mycompany"
              />
              <p class="mt-1 text-sm text-surface-500">Will be: {{ newTenant.subdomain }}.example.com</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label for="adminFirstName" class="form-label">Admin First Name</label>
                <input
                  id="adminFirstName"
                  v-model="newTenant.admin_first_name"
                  type="text"
                  required
                  class="form-input"
                />
              </div>
              <div>
                <label for="adminLastName" class="form-label">Admin Last Name</label>
                <input
                  id="adminLastName"
                  v-model="newTenant.admin_last_name"
                  type="text"
                  required
                  class="form-input"
                />
              </div>
            </div>

            <div>
              <label for="adminEmail" class="form-label">Admin Email</label>
              <input
                id="adminEmail"
                v-model="newTenant.admin_email"
                type="email"
                required
                class="form-input"
              />
            </div>

            <div class="flex justify-end space-x-4 pt-4">
              <button
                type="button"
                @click="showCreateModal = false"
                class="btn btn-secondary"
              >
                Cancel
              </button>
              <button
                type="submit"
                class="btn btn-primary"
              >
                Create Tenant
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>