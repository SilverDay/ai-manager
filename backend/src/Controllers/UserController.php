<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\RegistrationService;
use App\Services\JwtService;
use App\Models\User;
use App\Models\TenantDomain;
use App\Models\UserInvitation;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\AuthorizationException;
use App\Exceptions\ConflictException;

final class UserController
{
    private Config $config;
    private Database $database;
    private RegistrationService $registrationService;
    private User $userModel;
    private UserInvitation $invitationModel;

    public function __construct(
        Config $config,
        Database $database,
        RegistrationService $registrationService,
        User $userModel,
        UserInvitation $invitationModel
    ) {
        $this->config = $config;
        $this->database = $database;
        $this->registrationService = $registrationService;
        $this->userModel = $userModel;
        $this->invitationModel = $invitationModel;
    }

    /**
     * Register new user via domain-based registration
     */
    public function register(Request $request): Response
    {
        $email = $request->getBodyParamString('email');
        $password = $request->getBodyParamString('password');
        $firstName = $request->getBodyParamString('first_name');
        $lastName = $request->getBodyParamString('last_name');

        // Validate required fields
        $errors = [];
        if (!$email) $errors['email'] = ['Email is required'];
        if (!$password) $errors['password'] = ['Password is required'];
        if (!$firstName) $errors['first_name'] = ['First name is required'];
        if (!$lastName) $errors['last_name'] = ['Last name is required'];

        if (!empty($errors)) {
            throw ValidationException::fromFieldErrors($errors);
        }

        $result = $this->registrationService->registerByDomain($email, $password, $firstName, $lastName);

        return Response::created([
            'user_id' => $result['user_id'],
            'email' => $result['email'],
            'status' => $result['status'],
            'requires_approval' => $result['requires_approval']
        ], [
            'message' => $result['requires_approval']
                ? 'Registration successful. Your account is pending approval.'
                : 'Registration successful. You can now log in.'
        ]);
    }

    /**
     * Register user via invitation token
     */
    public function registerInvite(Request $request): Response
    {
        $token = $request->getRouteParameter('token');
        $password = $request->getBodyParamString('password');
        $firstName = $request->getBodyParamString('first_name');
        $lastName = $request->getBodyParamString('last_name');

        if (!$token) {
            throw ValidationException::forField('token', 'Invitation token is required');
        }

        // Validate required fields
        $errors = [];
        if (!$password) $errors['password'] = ['Password is required'];
        if (!$firstName) $errors['first_name'] = ['First name is required'];
        if (!$lastName) $errors['last_name'] = ['Last name is required'];

        if (!empty($errors)) {
            throw ValidationException::fromFieldErrors($errors);
        }

        $result = $this->registrationService->registerByInvite($token, $password, $firstName, $lastName);

        return Response::created([
            'user_id' => $result['user_id'],
            'email' => $result['email'],
            'status' => $result['status']
        ], [
            'message' => 'Registration successful via invitation. You can now log in.',
            'invited_by' => $result['invited_by']
        ]);
    }

    /**
     * List pending user approvals (Admin only)
     */
    public function pending(Request $request): Response
    {
        $tenantId = $request->getTenantId();
        $page = $request->getQueryParamInt('page', 1);
        $perPage = min($request->getQueryParamInt('per_page', 20), 100);
        $offset = ($page - 1) * $perPage;

        // Get pending users
        $users = $this->userModel->listUsers($tenantId, 'pending_approval', $offset, $perPage);
        $totalCount = $this->userModel->countUsers($tenantId, 'pending_approval');
        $totalPages = (int) ceil($totalCount / $perPage);

        return Response::paginated($users, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalCount,
            'total_pages' => $totalPages
        ], [
            'status_filter' => 'pending_approval'
        ]);
    }

    /**
     * Approve a pending user (Admin only)
     */
    public function approve(Request $request): Response
    {
        $userId = $request->getRouteParameter('id');
        $tenantId = $request->getTenantId();

        if (!$userId) {
            throw ValidationException::forField('id', 'User ID is required');
        }

        // Verify user exists and is pending
        $user = $this->userModel->findById((int) $userId, $tenantId);

        if (!$user) {
            throw NotFoundException::forResource('User', $userId);
        }

        if ($user['account_status'] !== 'pending_approval') {
            throw ValidationException::forField('status', 'User is not pending approval');
        }

        // Approve the user
        $success = $this->userModel->updateAccountStatus((int) $userId, 'active', $tenantId);

        if (!$success) {
            throw new \RuntimeException('Failed to approve user');
        }

        // TODO: Send approval notification email
        error_log("User {$user['email']} has been approved");

        return Response::success([
            'user_id' => $userId,
            'email' => $user['email'],
            'status' => 'active',
            'approved_at' => date('c')
        ], [
            'message' => 'User has been approved successfully'
        ]);
    }

    /**
     * Reject a pending user (Admin only)
     */
    public function reject(Request $request): Response
    {
        $userId = $request->getRouteParameter('id');
        $tenantId = $request->getTenantId();
        $reason = $request->getBodyParamString('reason');

        if (!$userId) {
            throw ValidationException::forField('id', 'User ID is required');
        }

        // Verify user exists and is pending
        $user = $this->userModel->findById((int) $userId, $tenantId);

        if (!$user) {
            throw NotFoundException::forResource('User', $userId);
        }

        if ($user['account_status'] !== 'pending_approval') {
            throw ValidationException::forField('status', 'User is not pending approval');
        }

        // Reject the user
        $success = $this->userModel->updateAccountStatus((int) $userId, 'rejected', $tenantId);

        if (!$success) {
            throw new \RuntimeException('Failed to reject user');
        }

        // TODO: Send rejection notification email with reason
        error_log("User {$user['email']} has been rejected. Reason: {$reason}");

        return Response::success([
            'user_id' => $userId,
            'email' => $user['email'],
            'status' => 'rejected',
            'rejected_at' => date('c'),
            'reason' => $reason
        ], [
            'message' => 'User has been rejected'
        ]);
    }

    /**
     * Create invitation for new user (Admin only)
     */
    public function createInvitation(Request $request): Response
    {
        $tenantId = $request->getTenantId();
        $user = $request->getUser();

        $email = $request->getBodyParamString('email');
        $roleId = $request->getBodyParamInt('role_id');

        // Validate required fields
        $errors = [];
        if (!$email) $errors['email'] = ['Email is required'];
        if (!$roleId) $errors['role_id'] = ['Role ID is required'];

        if (!empty($errors)) {
            throw ValidationException::fromFieldErrors($errors);
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::forField('email', 'Invalid email format');
        }

        $result = $this->registrationService->createInvitation(
            $tenantId,
            $roleId,
            $email,
            $user['id']
        );

        return Response::created([
            'invitation_id' => $result['invitation_id'],
            'email' => $result['email'],
            'expires_at' => $result['expires_at'],
            'invite_link' => $result['invite_link']
        ], [
            'message' => 'Invitation created successfully'
        ]);
    }

    /**
     * List invitations (Admin only)
     */
    public function listInvitations(Request $request): Response
    {
        $tenantId = $request->getTenantId();
        $status = $request->getQueryParamString('status');
        $page = $request->getQueryParamInt('page', 1);
        $perPage = min($request->getQueryParamInt('per_page', 20), 100);
        $offset = ($page - 1) * $perPage;

        // Get invitations
        $invitations = $this->invitationModel->listByTenant($tenantId, $status, $offset, $perPage);
        $totalCount = $this->invitationModel->countByTenant($tenantId, $status);
        $totalPages = (int) ceil($totalCount / $perPage);

        return Response::paginated($invitations, [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $totalCount,
            'total_pages' => $totalPages
        ], [
            'status_filter' => $status,
            'available_statuses' => ['pending', 'accepted', 'expired', 'revoked']
        ]);
    }

    /**
     * Resend invitation (Admin only)
     */
    public function resendInvitation(Request $request): Response
    {
        $invitationId = $request->getRouteParameter('id');
        $tenantId = $request->getTenantId();

        if (!$invitationId) {
            throw ValidationException::forField('id', 'Invitation ID is required');
        }

        $result = $this->registrationService->resendInvitation((int) $invitationId, $tenantId);

        return Response::success([
            'invitation_id' => $result['invitation_id'],
            'email' => $result['email'],
            'expires_at' => $result['expires_at'],
            'resent_at' => $result['resent_at']
        ], [
            'message' => 'Invitation resent successfully'
        ]);
    }

    /**
     * Revoke invitation (Admin only)
     */
    public function revokeInvitation(Request $request): Response
    {
        $invitationId = $request->getRouteParameter('id');
        $tenantId = $request->getTenantId();

        if (!$invitationId) {
            throw ValidationException::forField('id', 'Invitation ID is required');
        }

        $success = $this->registrationService->revokeInvitation((int) $invitationId, $tenantId);

        if (!$success) {
            throw NotFoundException::forResource('Invitation', $invitationId);
        }

        return Response::success([
            'invitation_id' => $invitationId,
            'status' => 'revoked',
            'revoked_at' => date('c')
        ], [
            'message' => 'Invitation revoked successfully'
        ]);
    }

    /**
     * Get user management statistics (Admin only)
     */
    public function getStats(Request $request): Response
    {
        $tenantId = $request->getTenantId();

        // Get user statistics
        $totalUsers = $this->userModel->countUsers($tenantId);
        $pendingUsers = $this->userModel->countUsers($tenantId, 'pending_approval');
        $activeUsers = $this->userModel->countUsers($tenantId, 'active');

        // Get invitation statistics
        $invitationStats = $this->invitationModel->getStats($tenantId);

        return Response::success([
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers,
                'pending_approval' => $pendingUsers,
                'deactivated' => $this->userModel->countUsers($tenantId, 'deactivated'),
                'rejected' => $this->userModel->countUsers($tenantId, 'rejected')
            ],
            'invitations' => $invitationStats
        ]);
    }
}