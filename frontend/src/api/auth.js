import { apiClient } from './client.js'

/**
 * Authentication API endpoints
 */
export const authApi = {
  /**
   * Login with email and password
   */
  async login(email, password) {
    return await apiClient.post('/v1/auth/login', {
      email,
      password
    })
  },

  /**
   * Logout current user
   */
  async logout() {
    return await apiClient.post('/v1/auth/logout')
  },

  /**
   * Refresh access token using refresh token cookie
   */
  async refresh() {
    return await apiClient.post('/v1/auth/refresh')
  },

  /**
   * Register new user via domain-based registration
   */
  async register(userData) {
    return await apiClient.post('/v1/users/register', userData)
  },

  /**
   * Register user via invitation token
   */
  async registerWithInvite(token, userData) {
    return await apiClient.post(`/v1/users/register/invite/${token}`, userData)
  },

  /**
   * Initiate password reset flow
   */
  async forgotPassword(email) {
    return await apiClient.post('/v1/auth/forgot-password', {
      email
    })
  },

  /**
   * Complete password reset with token and new password
   */
  async resetPassword(token, password) {
    return await apiClient.post('/v1/auth/reset-password', {
      token,
      password
    })
  },

  /**
   * Verify MFA code for superadmin accounts
   */
  async verifyMfa(challengeToken, code) {
    return await apiClient.post('/v1/auth/mfa-verify', {
      challenge_token: challengeToken,
      code
    })
  }
}

/**
 * User management API endpoints (Admin only)
 */
export const userManagementApi = {
  /**
   * Get list of pending user approvals
   */
  async getPendingUsers(page = 1, perPage = 20) {
    return await apiClient.get('/v1/users/pending', {
      params: { page, per_page: perPage }
    })
  },

  /**
   * Approve a pending user
   */
  async approveUser(userId) {
    return await apiClient.patch(`/v1/users/${userId}/approve`)
  },

  /**
   * Reject a pending user
   */
  async rejectUser(userId, reason = '') {
    return await apiClient.patch(`/v1/users/${userId}/reject`, {
      reason
    })
  },

  /**
   * Get user management statistics
   */
  async getUserStats() {
    return await apiClient.get('/v1/users/stats')
  }
}

/**
 * Invitation management API endpoints (Admin only)
 */
export const invitationApi = {
  /**
   * Create new invitation
   */
  async createInvitation(email, roleId) {
    return await apiClient.post('/v1/invitations', {
      email,
      role_id: roleId
    })
  },

  /**
   * Get list of invitations
   */
  async getInvitations(status = null, page = 1, perPage = 20) {
    const params = { page, per_page: perPage }
    if (status) params.status = status

    return await apiClient.get('/v1/invitations', { params })
  },

  /**
   * Resend invitation
   */
  async resendInvitation(invitationId) {
    return await apiClient.post(`/v1/invitations/${invitationId}/resend`)
  },

  /**
   * Revoke invitation
   */
  async revokeInvitation(invitationId) {
    return await apiClient.delete(`/v1/invitations/${invitationId}`)
  }
}

/**
 * Tenant domain management API endpoints (Admin only)
 */
export const domainApi = {
  /**
   * Get list of tenant domains
   */
  async getDomains() {
    return await apiClient.get('/v1/domains')
  },

  /**
   * Create new domain
   */
  async createDomain(domain, defaultRoleId, autoApprove = false) {
    return await apiClient.post('/v1/domains', {
      domain,
      default_role_id: defaultRoleId,
      auto_approve: autoApprove
    })
  },

  /**
   * Update domain configuration
   */
  async updateDomain(domainId, updates) {
    return await apiClient.put(`/v1/domains/${domainId}`, updates)
  },

  /**
   * Delete domain
   */
  async deleteDomain(domainId) {
    return await apiClient.delete(`/v1/domains/${domainId}`)
  },

  /**
   * Verify domain ownership
   */
  async verifyDomain(domainId) {
    return await apiClient.post(`/v1/domains/${domainId}/verify`)
  },

  /**
   * Generate new verification token
   */
  async generateVerificationToken(domainId, method = 'dns') {
    return await apiClient.post(`/v1/domains/${domainId}/verification-token`, {
      method
    })
  },

  /**
   * Get domain statistics
   */
  async getDomainStats() {
    return await apiClient.get('/v1/domains/stats')
  }
}

/**
 * Utility function to extract error messages from API responses
 */
export function getApiErrorMessage(error) {
  if (error.response?.data?.errors?.length > 0) {
    return error.response.data.errors[0]
  }

  if (error.response?.data?.message) {
    return error.response.data.message
  }

  if (error.message) {
    return error.message
  }

  return 'An unexpected error occurred'
}

/**
 * Utility function to check if error is authentication related
 */
export function isAuthenticationError(error) {
  return error.response?.status === 401
}

/**
 * Utility function to check if error is authorization related
 */
export function isAuthorizationError(error) {
  return error.response?.status === 403
}

/**
 * Utility function to check if error is validation related
 */
export function isValidationError(error) {
  return error.response?.status === 422
}

/**
 * Extract validation errors from API response
 */
export function getValidationErrors(error) {
  if (error.response?.status === 422 && error.response?.data?.meta?.validation_errors) {
    return error.response.data.meta.validation_errors
  }
  return {}
}