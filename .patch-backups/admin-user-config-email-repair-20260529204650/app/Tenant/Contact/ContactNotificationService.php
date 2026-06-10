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
        $adminEmail = $this->settings->get($tenant, 'site_admin_email');

        if (!$adminEmail) {
            return null;
        }

        $subject = "New contact message from {$senderName}";
        $body = implode("\n", [
            "Tenant: {$tenant->name} ({$tenant->slug})",
            "From: {$senderName} <{$senderEmail}>",
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
}

// End of file.
