<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\User;
use App\Models\TenantDomain;
use App\Models\UserInvitation;
use App\Exceptions\ValidationException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;

final class RegistrationService
{
    private Config $config;
    private Database $database;
    private User $userModel;
    private TenantDomain $tenantDomainModel;
    private UserInvitation $invitationModel;
    private JwtService $jwtService;

    public function __construct(
        Config $config,
        Database $database,
        User $userModel,
        TenantDomain $tenantDomainModel,
        UserInvitation $invitationModel,
        JwtService $jwtService
    ) {
        $this->config = $config;
        $this->database = $database;
        $this->userModel = $userModel;
        $this->tenantDomainModel = $tenantDomainModel;
        $this->invitationModel = $invitationModel;
        $this->jwtService = $jwtService;
    }

    /**
     * Register user via email domain mapping
     */
    public function registerByDomain(string $email, string $password, string $firstName, string $lastName): array
    {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::forField('email', ['Invalid email format']);
        }

        // Check password strength
        if (!$this->isValidPassword($password)) {
            throw ValidationException::forField('password',
                ['Password must be at least 12 characters long and contain uppercase, lowercase, numbers, and special characters']
            );
        }

        // Check if user already exists
        $existingUser = $this->userModel->findByEmail($email);
        if ($existingUser) {
            throw ConflictException::duplicateResource('User', 'email', $email);
        }

        // Extract domain from email
        $domain = $this->extractDomainFromEmail($email);

        // Validate domain and get tenant information
        $tenantId = $this->validateDomain($domain);
        if ($tenantId === null) {
            throw ValidationException::forField('email', ['Email domain is not registered for any organization']);
        }

        // Get tenant domain configuration
        $domainConfig = $this->tenantDomainModel->findByDomain($domain);
        if (!$domainConfig) {
            throw NotFoundException::forResource('Domain configuration', $domain);
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
            'cost' => $this->config->get('security.bcrypt_cost', 12)
        ]);

        // Generate email verification token
        $emailVerificationToken = $this->jwtService->generateEmailVerificationToken(0, $email); // Will update with real user ID

        // Determine account status
        $accountStatus = $domainConfig['auto_approve'] ? 'active' : 'pending_approval';

        // Create user
        $userData = [
            'tenant_id' => $tenantId,
            'role_id' => $domainConfig['default_role_id'],
            'email' => $email,
            'password_hash' => $passwordHash,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'account_status' => $accountStatus,
            'email_verification_token' => $emailVerificationToken
        ];

        try {
            $this->database->beginTransaction();

            $userId = $this->userModel->create($userData);

            // Update email verification token with real user ID
            $emailVerificationToken = $this->jwtService->generateEmailVerificationToken($userId, $email);
            $this->userModel->setEmailVerificationToken(
                $userId,
                $emailVerificationToken,
                new \DateTime('+24 hours')
            );

            $this->database->commit();

            // TODO: Send verification email
            error_log("Email verification token for {$email}: {$emailVerificationToken}");

            return [
                'user_id' => $userId,
                'email' => $email,
                'status' => $accountStatus,
                'requires_approval' => !$domainConfig['auto_approve'],
                'verification_token' => $emailVerificationToken
            ];
        } catch (\Exception $e) {
            $this->database->rollback();
            throw $e;
        }
    }

    /**
     * Register user via invitation token
     */
    public function registerByInvite(string $token, string $password, string $firstName, string $lastName): array
    {
        // Check password strength
        if (!$this->isValidPassword($password)) {
            throw ValidationException::forField('password',
                ['Password must be at least 12 characters long and contain uppercase, lowercase, numbers, and special characters']
            );
        }

        // Find and validate invitation
        $invitation = $this->invitationModel->findByToken($token);
        if (!$invitation) {
            throw ValidationException::forField('token', ['Invalid or expired invitation token']);
        }

        if ($invitation['status'] !== 'pending') {
            throw ValidationException::forField('token', ['Invitation has already been used or revoked']);
        }

        if (new \DateTime($invitation['expires_at']) < new \DateTime()) {
            throw ValidationException::forField('token', ['Invitation has expired']);
        }

        // Check if user already exists
        $existingUser = $this->userModel->findByEmail($invitation['email']);
        if ($existingUser) {
            throw ConflictException::duplicateResource('User', 'email', $invitation['email']);
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
            'cost' => $this->config->get('security.bcrypt_cost', 12)
        ]);

        // Create user with active status (invites are pre-approved)
        $userData = [
            'tenant_id' => $invitation['tenant_id'],
            'role_id' => $invitation['role_id'],
            'email' => $invitation['email'],
            'password_hash' => $passwordHash,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'account_status' => 'active',
            'email_verified_at' => date('Y-m-d H:i:s') // Mark as verified via invitation
        ];

        try {
            $this->database->beginTransaction();

            $userId = $this->userModel->create($userData);

            // Mark invitation as accepted
            $this->invitationModel->markAsAccepted($invitation['id'], $userId);

            $this->database->commit();

            return [
                'user_id' => $userId,
                'email' => $invitation['email'],
                'status' => 'active',
                'requires_approval' => false,
                'invited_by' => $invitation['invited_by_user_id']
            ];
        } catch (\Exception $e) {
            $this->database->rollback();
            throw $e;
        }
    }

    /**
     * Validate domain and return tenant ID
     */
    public function validateDomain(string $domain): ?int
    {
        // Check freemail blocklist first
        if ($this->isFreemailDomain($domain)) {
            return null;
        }

        // Check if domain is registered for a tenant
        $tenantDomain = $this->tenantDomainModel->findByDomain($domain);

        if (!$tenantDomain || !$tenantDomain['verified']) {
            return null;
        }

        return $tenantDomain['tenant_id'];
    }

    /**
     * Create invitation for new user
     */
    public function createInvitation(
        int $tenantId,
        int $roleId,
        string $email,
        int $invitedByUserId,
        ?\DateTime $expiresAt = null
    ): array {
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::forField('email', ['Invalid email format']);
        }

        // Check if user already exists
        $existingUser = $this->userModel->findByEmail($email, $tenantId);
        if ($existingUser) {
            throw ConflictException::duplicateResource('User', 'email', $email);
        }

        // Check for existing pending invitation
        $existingInvitation = $this->invitationModel->findByEmail($email, $tenantId, 'pending');
        if ($existingInvitation) {
            throw ConflictException::duplicateResource('Invitation', 'email', $email);
        }

        // Set default expiry (7 days)
        $expiresAt = $expiresAt ?? new \DateTime('+7 days');

        // Generate unique token
        $token = bin2hex(random_bytes(32));

        $invitationData = [
            'tenant_id' => $tenantId,
            'role_id' => $roleId,
            'email' => $email,
            'invited_by_user_id' => $invitedByUserId,
            'token' => $token,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'status' => 'pending'
        ];

        $invitationId = $this->invitationModel->create($invitationData);

        // TODO: Send invitation email
        $inviteLink = $this->config->get('app.frontend_url') . "/register/invite/{$token}";
        error_log("Invitation link for {$email}: {$inviteLink}");

        return [
            'invitation_id' => $invitationId,
            'email' => $email,
            'token' => $token,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'invite_link' => $inviteLink
        ];
    }

    /**
     * Extract domain from email address
     */
    private function extractDomainFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        return strtolower(end($parts));
    }

    /**
     * Check if domain is in freemail blocklist
     */
    private function isFreemailDomain(string $domain): bool
    {
        // This would normally query the freemail_blocklist table
        // For now, use a basic list
        $freemailDomains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'aol.com', '10minutemail.com', 'guerrillamail.com'
        ];

        return in_array(strtolower($domain), $freemailDomains, true);
    }

    /**
     * Validate password strength
     */
    private function isValidPassword(string $password): bool
    {
        // At least 12 characters, must contain:
        // - uppercase letter
        // - lowercase letter
        // - number
        // - special character
        return strlen($password) >= 12
            && preg_match('/[A-Z]/', $password)
            && preg_match('/[a-z]/', $password)
            && preg_match('/[0-9]/', $password)
            && preg_match('/[^A-Za-z0-9]/', $password);
    }

    /**
     * Resend invitation
     */
    public function resendInvitation(int $invitationId, int $tenantId): array
    {
        $invitation = $this->invitationModel->findById($invitationId, $tenantId);

        if (!$invitation) {
            throw NotFoundException::forResource('Invitation', $invitationId);
        }

        if ($invitation['status'] !== 'pending') {
            throw ValidationException::forField('invitation', ['Can only resend pending invitations']);
        }

        // Extend expiry by 7 days
        $newExpiry = new \DateTime('+7 days');
        $this->invitationModel->updateExpiry($invitationId, $newExpiry);

        // TODO: Resend invitation email
        $inviteLink = $this->config->get('app.frontend_url') . "/register/invite/{$invitation['token']}";
        error_log("Resent invitation link for {$invitation['email']}: {$inviteLink}");

        return [
            'invitation_id' => $invitationId,
            'email' => $invitation['email'],
            'expires_at' => $newExpiry->format('Y-m-d H:i:s'),
            'resent_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Revoke invitation
     */
    public function revokeInvitation(int $invitationId, int $tenantId): bool
    {
        return $this->invitationModel->updateStatus($invitationId, $tenantId, 'revoked');
    }
}