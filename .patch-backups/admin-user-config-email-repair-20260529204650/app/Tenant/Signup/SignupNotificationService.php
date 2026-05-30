<?php

declare(strict_types=1);

namespace App\Tenant\Signup;

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Queues tenant admin notifications for public email-list signups.
 */
final class SignupNotificationService
{
    public function __construct(
        private readonly EmailOutboxRepository $outbox,
        private readonly TenantSettingsRepository $settings,
    ) {
    }

    public function queueSignupNotification(
        TenantContext $tenant,
        string $signupEmail,
        ?string $signupName = null,
    ): ?int {
        $adminEmail = $this->settings->get($tenant, 'site_admin_email');

        if (!$adminEmail) {
            return null;
        }

        $displayName = $signupName ?: 'Unknown';
        $subject = "New email signup: {$signupEmail}";
        $body = implode("\n", [
            "Tenant: {$tenant->name} ({$tenant->slug})",
            "Signup email: {$signupEmail}",
            "Signup name: {$displayName}",
            "",
        ]);

        return $this->outbox->queue(
            recipientEmail: $adminEmail,
            subject: $subject,
            bodyText: $body,
            tenantId: $tenant->tenantId,
            templateKey: 'tenant.signup_notification',
        );
    }
}

// End of file.
