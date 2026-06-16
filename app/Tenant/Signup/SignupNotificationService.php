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
    private const FALLBACK_NOTIFICATION_EMAIL = 'info@artsfol.io';

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
        $adminEmail = $this->notificationEmail($tenant);

        if ($adminEmail === null) {
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

    /**
     * Resolve a notification destination without silently losing public signups.
     */
    private function notificationEmail(TenantContext $tenant): ?string
    {
        $candidates = [
            $this->settings->get($tenant, 'site_admin_email'),
            getenv('ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL') ?: '',
            self::FALLBACK_NOTIFICATION_EMAIL,
        ];

        foreach ($candidates as $candidate) {
            $email = strtolower(trim((string) $candidate));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return null;
    }
}

// End of file.
