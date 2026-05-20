<script setup>
import { ref, onMounted, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { invitationApi, getApiErrorMessage, getValidationErrors, isValidationError } from '../../api/auth.js'

const { t } = useI18n()

// State
const invitations = ref([])
const loading = ref(false)
const error = ref(null)
const showCreateModal = ref(false)
const actionLoading = ref(false)

// Form state
const newInvitationEmail = ref('')
const newInvitationRole = ref(2) // Default to Admin role
const validationErrors = ref({})

// Available roles (this would normally come from an API)
const availableRoles = ref([
  { id: 2, name: 'Admin' },
  { id: 3, name: 'AI Owner' },
  { id: 4, name: 'Compliance Officer' },
  { id: 5, name: 'Auditor' },
  { id: 6, name: 'Data Protection Officer' },
  { id: 7, name: 'Technical Staff' }
])

// Pagination
const currentPage = ref(1)
const totalPages = ref(1)
const totalInvitations = ref(0)
const perPage = 20

// Filters
const selectedStatus = ref('')
const statusOptions = [
  { value: '', label: 'All Invitations' },
  { value: 'pending', label: 'Pending' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'expired', label: 'Expired' },
  { value: 'revoked', label: 'Revoked' }
]

const filteredInvitations = computed(() => {
  if (!selectedStatus.value) {
    return invitations.value
  }
  return invitations.value.filter(inv => inv.status === selectedStatus.value)
})

onMounted(() => {
  fetchInvitations()
})

const fetchInvitations = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await invitationApi.getInvitations(
      selectedStatus.value || null,
      currentPage.value,
      perPage
    )
    invitations.value = response.data.data
    currentPage.value = response.data.meta.current_page
    totalPages.value = response.data.meta.last_page
    totalInvitations.value = response.data.meta.total
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    loading.value = false
  }
}

const openCreateModal = () => {
  newInvitationEmail.value = ''
  newInvitationRole.value = 2
  validationErrors.value = {}
  showCreateModal.value = true
}

const closeCreateModal = () => {
  showCreateModal.value = false
  newInvitationEmail.value = ''
  newInvitationRole.value = 2
  validationErrors.value = {}
}

const createInvitation = async () => {
  actionLoading.value = true
  error.value = null
  validationErrors.value = {}

  try {
    await invitationApi.createInvitation(newInvitationEmail.value, newInvitationRole.value)
    closeCreateModal()
    fetchInvitations() // Refresh the list
  } catch (err) {
    if (isValidationError(err)) {
      validationErrors.value = getValidationErrors(err)
    } else {
      error.value = getApiErrorMessage(err)
    }
  } finally {
    actionLoading.value = false
  }
}

const resendInvitation = async (invitationId) => {
  try {
    await invitationApi.resendInvitation(invitationId)
    fetchInvitations() // Refresh to show updated sent_at timestamp
  } catch (err) {
    error.value = getApiErrorMessage(err)
  }
}

const revokeInvitation = async (invitationId) => {
  if (!confirm(t('admin.confirmRevokeInvitation'))) {
    return
  }

  try {
    await invitationApi.revokeInvitation(invitationId)
    fetchInvitations() // Refresh the list
  } catch (err) {
    error.value = getApiErrorMessage(err)
  }
}

const changePage = (page) => {
  if (page !== currentPage.value && page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    fetchInvitations()
  }
}

const onStatusFilterChange = () => {
  currentPage.value = 1
  fetchInvitations()
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString()
}

const getRoleNameById = (roleId) => {
  const role = availableRoles.value.find(r => r.id === roleId)
  return role ? role.name : 'Unknown'
}

const getStatusBadgeClass = (status) => {
  const classes = {
    pending: 'bg-warning-100 text-warning-800',
    accepted: 'bg-success-100 text-success-800',
    expired: 'bg-surface-100 text-surface-800',
    revoked: 'bg-danger-100 text-danger-800'
  }
  return classes[status] || 'bg-surface-100 text-surface-800'
}
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
      <div>
        <h1 class="text-3xl font-bold text-surface-900">
          {{ t('admin.invitationsTitle') }}
        </h1>
        <p class="mt-2 text-surface-600">
          {{ t('admin.invitationsSubtitle') }}
        </p>
      </div>
      <button
        @click="openCreateModal"
        class="btn btn-primary"
      >
        {{ t('admin.sendInvitation') }}
      </button>
    </div>

    <!-- Filters -->
    <div class="mb-6 card p-4">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <label for="statusFilter" class="form-label">
            {{ t('admin.filterByStatus') }}
          </label>
          <select
            id="statusFilter"
            v-model="selectedStatus"
            @change="onStatusFilterChange"
            class="form-input"
          >
            <option v-for="option in statusOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div
      v-if="error"
      class="mb-6 bg-danger-50 border border-danger-200 text-danger-700 px-4 py-3 rounded"
    >
      {{ error }}
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <div class="text-surface-600">{{ t('common.loading') }}</div>
    </div>

    <!-- Empty State -->
    <div v-else-if="!loading && filteredInvitations.length === 0" class="text-center py-12">
      <div class="text-surface-400 text-4xl mb-4">📧</div>
      <h3 class="text-lg font-medium text-surface-900 mb-2">
        {{ t('admin.noInvitations') }}
      </h3>
      <p class="text-surface-600 mb-6">
        {{ t('admin.noInvitationsMessage') }}
      </p>
      <button
        @click="openCreateModal"
        class="btn btn-primary"
      >
        {{ t('admin.sendFirstInvitation') }}
      </button>
    </div>

    <!-- Invitations Table -->
    <div v-else class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-surface-200">
          <thead class="bg-surface-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.email') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.role') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.status') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.sentDate') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-surface-200">
            <tr v-for="invitation in filteredInvitations" :key="invitation.id" class="hover:bg-surface-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm font-medium text-surface-900">
                  {{ invitation.email }}
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                  {{ getRoleNameById(invitation.role_id) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span
                  class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                  :class="getStatusBadgeClass(invitation.status)"
                >
                  {{ t(`admin.invitationStatus.${invitation.status}`) }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-surface-500">
                {{ formatDate(invitation.sent_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <div class="flex justify-end space-x-2">
                  <button
                    v-if="invitation.status === 'pending'"
                    @click="resendInvitation(invitation.id)"
                    class="text-primary-600 hover:text-primary-900"
                  >
                    {{ t('admin.resend') }}
                  </button>
                  <button
                    v-if="invitation.status === 'pending'"
                    @click="revokeInvitation(invitation.id)"
                    class="text-danger-600 hover:text-danger-900"
                  >
                    {{ t('admin.revoke') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="bg-white px-4 py-3 flex items-center justify-between border-t border-surface-200 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            @click="changePage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-surface-300 text-sm font-medium rounded-md text-surface-700 bg-white hover:bg-surface-50"
          >
            {{ t('common.previous') }}
          </button>
          <button
            @click="changePage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-surface-300 text-sm font-medium rounded-md text-surface-700 bg-white hover:bg-surface-50"
          >
            {{ t('common.next') }}
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-surface-700">
              {{ t('admin.showingResults', {
                from: (currentPage - 1) * perPage + 1,
                to: Math.min(currentPage * perPage, totalInvitations),
                total: totalInvitations
              }) }}
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
              <button
                v-for="page in totalPages"
                :key="page"
                @click="changePage(page)"
                :class="{
                  'bg-primary-50 border-primary-500 text-primary-600': page === currentPage,
                  'bg-white border-surface-300 text-surface-500 hover:bg-surface-50': page !== currentPage
                }"
                class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
              >
                {{ page }}
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Invitation Modal -->
    <div v-if="showCreateModal" class="fixed inset-0 bg-surface-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="p-6">
          <h3 class="text-lg font-medium text-surface-900 mb-4">
            {{ t('admin.sendInvitation') }}
          </h3>

          <form @submit.prevent="createInvitation" class="space-y-4">
            <!-- Email Field -->
            <div>
              <label for="invitationEmail" class="form-label">
                {{ t('admin.email') }}
              </label>
              <input
                id="invitationEmail"
                v-model="newInvitationEmail"
                type="email"
                required
                class="form-input"
                :class="{ 'border-danger-300': validationErrors.email }"
                :placeholder="t('admin.invitationEmailPlaceholder')"
                :disabled="actionLoading"
              />
              <p v-if="validationErrors.email" class="mt-1 text-sm text-danger-600">
                {{ validationErrors.email[0] }}
              </p>
            </div>

            <!-- Role Field -->
            <div>
              <label for="invitationRole" class="form-label">
                {{ t('admin.role') }}
              </label>
              <select
                id="invitationRole"
                v-model="newInvitationRole"
                required
                class="form-input"
                :disabled="actionLoading"
              >
                <option v-for="role in availableRoles" :key="role.id" :value="role.id">
                  {{ role.name }}
                </option>
              </select>
            </div>

            <!-- Error Display -->
            <div
              v-if="error"
              class="bg-danger-50 border border-danger-200 text-danger-700 px-3 py-2 rounded text-sm"
            >
              {{ error }}
            </div>

            <!-- Modal Actions -->
            <div class="flex justify-end space-x-3 pt-4">
              <button
                type="button"
                @click="closeCreateModal"
                :disabled="actionLoading"
                class="btn btn-outline"
              >
                {{ t('common.cancel') }}
              </button>
              <button
                type="submit"
                :disabled="actionLoading || !newInvitationEmail"
                class="btn btn-primary"
              >
                <span v-if="actionLoading">
                  {{ t('admin.sendingInvitation') }}
                </span>
                <span v-else>
                  {{ t('admin.sendInvitation') }}
                </span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>