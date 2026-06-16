<?php

declare(strict_types=1);

namespace App\Tenant\Contact;

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Queues tenant admin notifications for public contact form messages.
 */
final class ContactNotificationService
{
    private const FALLBACK_NOTIFICATION_EMAIL = 'info@artsfol.io';

    public function __construct(
        private readonly EmailOutboxRepository $outbox,
        private readonly TenantSettingsRepository $settings,
    ) {
    }

    public function queueContactNotification(
        TenantContext $tenant,
        string $senderName,
        string $senderEmail,
        string $message,
    ): ?int {
        $adminEmail = $this->notificationEmail($tenant);

        if ($adminEmail === null) {
            return null;
        }

        $subject = "New contact message from {$senderName}";
        $body = implode("\n", [
            "Tenant: {$tenant->name} ({$tenant->slug})",
            "From: {$senderName} <{$senderEmail}>",
            "Reply to the sender manually at: {$senderEmail}",
            "",
            "Message:",
            $message,
            "",
        ]);

        return $this->outbox->queue(
            recipientEmail: $adminEmail,
            subject: $subject,
            bodyText: $body,
            tenantId: $tenant->tenantId,
            templateKey: 'tenant.contact_notification',
        );
    }

    /**
     * Resolve the notification destination without silently dropping contacts.
     *
     * Tenant settings win. ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL allows staging
     * and production to override the hard fallback. The hard fallback keeps the
     * public contact form from accepting a message and then notifying nobody.
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
