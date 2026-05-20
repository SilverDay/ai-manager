<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { userManagementApi, getApiErrorMessage } from '../../api/auth.js'

const { t } = useI18n()

// State
const pendingUsers = ref([])
const loading = ref(false)
const error = ref(null)
const selectedUser = ref(null)
const showApprovalModal = ref(false)
const showRejectionModal = ref(false)
const rejectionReason = ref('')
const actionLoading = ref(false)

// Pagination
const currentPage = ref(1)
const totalPages = ref(1)
const totalUsers = ref(0)
const perPage = 20

onMounted(() => {
  fetchPendingUsers()
})

const fetchPendingUsers = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await userManagementApi.getPendingUsers(currentPage.value, perPage)
    pendingUsers.value = response.data.data
    currentPage.value = response.data.meta.current_page
    totalPages.value = response.data.meta.last_page
    totalUsers.value = response.data.meta.total
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    loading.value = false
  }
}

const openApprovalModal = (user) => {
  selectedUser.value = user
  showApprovalModal.value = true
}

const openRejectionModal = (user) => {
  selectedUser.value = user
  rejectionReason.value = ''
  showRejectionModal.value = true
}

const closeModals = () => {
  showApprovalModal.value = false
  showRejectionModal.value = false
  selectedUser.value = null
  rejectionReason.value = ''
}

const approveUser = async () => {
  if (!selectedUser.value) return

  actionLoading.value = true
  try {
    await userManagementApi.approveUser(selectedUser.value.id)

    // Remove user from pending list
    pendingUsers.value = pendingUsers.value.filter(
      user => user.id !== selectedUser.value.id
    )
    totalUsers.value--

    closeModals()
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    actionLoading.value = false
  }
}

const rejectUser = async () => {
  if (!selectedUser.value) return

  actionLoading.value = true
  try {
    await userManagementApi.rejectUser(selectedUser.value.id, rejectionReason.value)

    // Remove user from pending list
    pendingUsers.value = pendingUsers.value.filter(
      user => user.id !== selectedUser.value.id
    )
    totalUsers.value--

    closeModals()
  } catch (err) {
    error.value = getApiErrorMessage(err)
  } finally {
    actionLoading.value = false
  }
}

const changePage = (page) => {
  if (page !== currentPage.value && page >= 1 && page <= totalPages.value) {
    currentPage.value = page
    fetchPendingUsers()
  }
}

const formatDate = (dateString) => {
  return new Date(dateString).toLocaleDateString()
}
</script>

<template>
  <div class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
      <h1 class="text-3xl font-bold text-surface-900">
        {{ t('admin.pendingUsersTitle') }}
      </h1>
      <p class="mt-2 text-surface-600">
        {{ t('admin.pendingUsersSubtitle') }}
      </p>
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
    <div v-else-if="!loading && pendingUsers.length === 0" class="text-center py-12">
      <div class="text-surface-400 text-4xl mb-4">✓</div>
      <h3 class="text-lg font-medium text-surface-900 mb-2">
        {{ t('admin.noPendingUsers') }}
      </h3>
      <p class="text-surface-600">
        {{ t('admin.noPendingUsersMessage') }}
      </p>
    </div>

    <!-- Users Table -->
    <div v-else class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-surface-200">
          <thead class="bg-surface-50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.userInfo') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.requestDate') }}
              </th>
              <th class="px-6 py-3 text-left text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.domain') }}
              </th>
              <th class="px-6 py-3 text-right text-xs font-medium text-surface-500 uppercase tracking-wider">
                {{ t('admin.actions') }}
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-surface-200">
            <tr v-for="user in pendingUsers" :key="user.id" class="hover:bg-surface-50">
              <td class="px-6 py-4 whitespace-nowrap">
                <div>
                  <div class="text-sm font-medium text-surface-900">
                    {{ user.first_name }} {{ user.last_name }}
                  </div>
                  <div class="text-sm text-surface-500">
                    {{ user.email }}
                  </div>
                </div>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-sm text-surface-500">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-6 py-4 whitespace-nowrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary-100 text-primary-800">
                  {{ user.email.split('@')[1] }}
                </span>
              </td>
              <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                <button
                  @click="openApprovalModal(user)"
                  class="text-success-600 hover:text-success-900 mr-4"
                >
                  {{ t('admin.approve') }}
                </button>
                <button
                  @click="openRejectionModal(user)"
                  class="text-danger-600 hover:text-danger-900"
                >
                  {{ t('admin.reject') }}
                </button>
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
                to: Math.min(currentPage * perPage, totalUsers),
                total: totalUsers
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

    <!-- Approval Modal -->
    <div v-if="showApprovalModal" class="fixed inset-0 bg-surface-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="p-6">
          <h3 class="text-lg font-medium text-surface-900 mb-4">
            {{ t('admin.confirmApproval') }}
          </h3>
          <p class="text-surface-600 mb-6">
            {{ t('admin.approvalConfirmMessage', {
              name: selectedUser?.first_name + ' ' + selectedUser?.last_name,
              email: selectedUser?.email
            }) }}
          </p>
          <div class="flex justify-end space-x-3">
            <button
              @click="closeModals"
              :disabled="actionLoading"
              class="btn btn-outline"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              @click="approveUser"
              :disabled="actionLoading"
              class="btn btn-success"
            >
              <span v-if="actionLoading">
                {{ t('admin.approving') }}
              </span>
              <span v-else>
                {{ t('admin.approve') }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Rejection Modal -->
    <div v-if="showRejectionModal" class="fixed inset-0 bg-surface-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
        <div class="p-6">
          <h3 class="text-lg font-medium text-surface-900 mb-4">
            {{ t('admin.confirmRejection') }}
          </h3>
          <p class="text-surface-600 mb-4">
            {{ t('admin.rejectionConfirmMessage', {
              name: selectedUser?.first_name + ' ' + selectedUser?.last_name,
              email: selectedUser?.email
            }) }}
          </p>
          <div class="mb-4">
            <label for="rejectionReason" class="form-label">
              {{ t('admin.rejectionReason') }} ({{ t('common.optional') }})
            </label>
            <textarea
              id="rejectionReason"
              v-model="rejectionReason"
              rows="3"
              class="form-input"
              :placeholder="t('admin.rejectionReasonPlaceholder')"
              :disabled="actionLoading"
            />
          </div>
          <div class="flex justify-end space-x-3">
            <button
              @click="closeModals"
              :disabled="actionLoading"
              class="btn btn-outline"
            >
              {{ t('common.cancel') }}
            </button>
            <button
              @click="rejectUser"
              :disabled="actionLoading"
              class="btn btn-danger"
            >
              <span v-if="actionLoading">
                {{ t('admin.rejecting') }}
              </span>
              <span v-else>
                {{ t('admin.reject') }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>