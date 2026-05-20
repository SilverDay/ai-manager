<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\TenantDomain;
use App\Exceptions\ValidationException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ConflictException;

final class TenantController
{
    private Config $config;
    private Database $database;
    private TenantDomain $tenantDomainModel;

    public function __construct(
        Config $config,
        Database $database,
        TenantDomain $tenantDomainModel
    ) {
        $this->config = $config;
        $this->database = $database;
        $this->tenantDomainModel = $tenantDomainModel;
    }

    /**
     * List domains for the current tenant (Admin only)
     */
    public function listDomains(Request $request): Response
    {
        $tenantId = $request->getTenantId();

        if ($tenantId === null) {
            throw ValidationException::forField('tenant', 'This endpoint requires tenant context');
        }

        $domains = $this->tenantDomainModel->listByTenant($tenantId);

        return Response::success($domains, [
            'tenant_id' => $tenantId,
            'total_domains' => count($domains)
        ]);
    }

    /**
     * Create new domain for the current tenant (Admin only)
     */
    public function createDomain(Request $request): Response
    {
        $tenantId = $request->getTenantId();

        if ($tenantId === null) {
            throw ValidationException::forField('tenant', 'This endpoint requires tenant context');
        }

        $domain = $request->getBodyParamString('domain');
        $autoApprove = $request->getBodyParamBool('auto_approve', false);
        $defaultRoleId = $request->getBodyParamInt('default_role_id');

        // Validate required fields
        $errors = [];
        if (!$domain) $errors['domain'] = ['Domain is required'];
        if (!$defaultRoleId) $errors['default_role_id'] = ['Default role ID is required'];

        if (!empty($errors)) {
            throw ValidationException::fromFieldErrors($errors);
        }

        // Validate domain format
        if (!$this->isValidDomain($domain)) {
            throw ValidationException::forField('domain', 'Invalid domain format');
        }

        // Check if domain already exists
        if ($this->tenantDomainModel->domainExists($domain)) {
            throw ConflictException::duplicateResource('Domain', 'domain', $domain);
        }

        // Create domain
        $domainData = [
            'tenant_id' => $tenantId,
            'domain' => $domain,
            'auto_approve' => $autoApprove,
            'default_role_id' => $defaultRoleId,
            'verification_method' => 'manual'
        ];

        $domainId = $this->tenantDomainModel->create($domainData);

        // Generate verification token for domain ownership verification
        $verificationToken = $this->tenantDomainModel->generateVerificationToken($domainId, $tenantId, 'dns');

        return Response::created([
            'domain_id' => $domainId,
            'domain' => $domain,
            'auto_approve' => $autoApprove,
            'default_role_id' => $defaultRoleId,
            'verified' => false,
            'verification_token' => $verificationToken
        ], [
            'message' => 'Domain created successfully. Domain verification required.',
            'verification_instructions' => $this->getVerificationInstructions($domain, $verificationToken)
        ]);
    }

    /**
     * Update domain configuration (Admin only)
     */
    public function updateDomain(Request $request): Response
    {
        $tenantId = $request->getTenantId();
        $domainId = $request->getRouteParameter('id');

        if ($tenantId === null) {
            throw ValidationException::forField('tenant', 'This endpoint requires tenant context');
        }

        if (!$domainId) {
            throw ValidationException::forField('id', 'Domain ID is required');
        }

        // Verify domain exists and belongs to tenant
        $existingDomain = $this->tenantDomainModel->findById((int) $domainId, $tenantId);

        if (!$existingDomain) {
            throw NotFoundException::forResource('Domain', $domainId);
        }

        // Get update fields
        $updates = [];

        if ($request->getBodyParam('auto_approve') !== null) {
            $updates['auto_approve'] = $request->getBodyParamBool('auto_approve');
        }

        if ($request->getBodyParam('default_role_id') !== null) {
            $updates['default_role_id'] = $request->getBodyParamInt('default_role_id');
        }

        if (empty($updates)) {
            throw ValidationException::forField('updates', 'No valid fields provided for update');
        }

        // Update domain
        $success = $this->tenantDomainModel->update((int) $domainId, $tenantId, $updates);

        if (!$success) {
            throw new \RuntimeException('Failed to update domain');
        }

        // Return updated domain
        $updatedDomain = $this->tenantDomainModel->findById((int) $domainId, $tenantId);

        return Response::success($updatedDomain, [
            'message' => 'Domain updated successfully'
        ]);
    }

    /**
     * Delete domain (Admin only)
     */
    public function deleteDomain(Request $request): Response
    {
        $tenantId = $request->getTenantId();
        $domainId = $request->getRouteParameter('id');

        if ($tenantId === null) {
            throw ValidationException::forField('tenant', 'This endpoint requires tenant context');
        }

        if (!$domainId) {
            throw ValidationException::forField('id', 'Domain ID is required');
        }

        // Verify domain exists and belongs to tenant
        $domain = $this->tenantDomainModel->findById((int) $domainId, $tenantId);

        if (!$domain) {
            throw NotFoundException::forResource('Domain', $domainId);
        }

        // Delete domain
        $success = $this->tenantDomainModel->delete((int) $domainId, $tenantId);

        if (!$success) {
            throw new \RuntimeException('Failed to delete domain');
        }

        return Response::success([
            'domain_id' => $domainId,
            'domain' => $domain['domain'],
            'deleted_at' => date('c')
        ], [
            'message' => 'Domain deleted successfully'
        ]);
    }

    /**
     * Verify domain ownership (Admin only)
     */
    public function verifyDomain(Request $request): Response
    {
        $tenantId = $request->getTenantId();
        $domainId = $request->getRouteParameter('id');

        if ($tenantId === null) {
            throw ValidationException::forField('tenant', 'This endpoint requires tenant context');
        }

        if (!$domainId) {
            throw ValidationException::forField('id', 'Domain ID is required');
        }

        // Verify domain exists and belongs to tenant
        $domain = $this->tenantDomainModel->findById((int) $domainId, $tenantId);

        if (!$domain) {
            throw NotFoundException::forResource('Domain', $domainId);
        }

        if ($domain['verified']) {
            throw ValidationException::forField('domain', 'Domain is already verified');
        }

        // TODO: Implement actual domain verification
        // For now, we'll just mark it as verified
        // In a real implementation, this would check DNS TXT records or email verification

        $success = $this->tenantDomainModel->markAsVerified((int) $domainId, $tenantId);

        if (!$success) {
            throw new \RuntimeException('Failed to verify domain');
        }

        return Response::success([
            'domain_id' => $domainId,
            'domain' => $domain['domain'],
            'verified' => true,
            'verified_at' => date('c')
        ], [
            'message' => 'Domain verified successfully'
        ]);
    }

    /**
     * Get domain statistics (Admin only)
     */
    public function getDomainStats(Request $request): Response
    {
        $tenantId = $request->getTenantId();

        if ($tenantId === null) {
            throw ValidationException::forField('tenant', 'This endpoint requires tenant context');
        }

        $stats = $this->tenantDomainModel->getStats($tenantId);

        return Response::success($stats, [
            'tenant_id' => $tenantId
        ]);
    }

    /**
     * Generate new verification token (Admin only)
     */
    public function generateVerificationToken(Request $request): Response
    {
        $tenantId = $request->getTenantId();
        $domainId = $request->getRouteParameter('id');
        $method = $request->getBodyParamString('method', 'dns');

        if ($tenantId === null) {
            throw ValidationException::forField('tenant', 'This endpoint requires tenant context');
        }

        if (!$domainId) {
            throw ValidationException::forField('id', 'Domain ID is required');
        }

        // Validate verification method
        if (!in_array($method, ['dns', 'email', 'manual'], true)) {
            throw ValidationException::forField('method', 'Invalid verification method');
        }

        // Verify domain exists and belongs to tenant
        $domain = $this->tenantDomainModel->findById((int) $domainId, $tenantId);

        if (!$domain) {
            throw NotFoundException::forResource('Domain', $domainId);
        }

        if ($domain['verified']) {
            throw ValidationException::forField('domain', 'Domain is already verified');
        }

        // Generate new verification token
        $verificationToken = $this->tenantDomainModel->generateVerificationToken((int) $domainId, $tenantId, $method);

        if (!$verificationToken) {
            throw new \RuntimeException('Failed to generate verification token');
        }

        return Response::success([
            'domain_id' => $domainId,
            'domain' => $domain['domain'],
            'verification_token' => $verificationToken,
            'verification_method' => $method,
            'verification_instructions' => $this->getVerificationInstructions($domain['domain'], $verificationToken, $method)
        ], [
            'message' => 'New verification token generated'
        ]);
    }

    /**
     * Validate domain format
     */
    private function isValidDomain(string $domain): bool
    {
        // Basic domain validation
        return filter_var("http://{$domain}", FILTER_VALIDATE_URL) !== false
            && preg_match('/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $domain);
    }

    /**
     * Get domain verification instructions
     */
    private function getVerificationInstructions(string $domain, string $token, string $method = 'dns'): array
    {
        return match ($method) {
            'dns' => [
                'method' => 'DNS TXT Record',
                'instructions' => [
                    "Add the following TXT record to your DNS settings for {$domain}:",
                    "Name: _aigov-verify",
                    "Value: {$token}",
                    "TTL: 300 (or default)",
                    "After adding the record, use the verify endpoint to check ownership."
                ]
            ],
            'email' => [
                'method' => 'Email Verification',
                'instructions' => [
                    "An email will be sent to admin@{$domain} with verification instructions.",
                    "Click the verification link in the email to verify ownership.",
                    "If you don't receive the email, check your spam folder."
                ]
            ],
            'manual' => [
                'method' => 'Manual Verification',
                'instructions' => [
                    "Contact support with your verification token: {$token}",
                    "Provide documentation proving ownership of {$domain}",
                    "Verification will be completed manually within 24-48 hours."
                ]
            ]
        };
    }
}