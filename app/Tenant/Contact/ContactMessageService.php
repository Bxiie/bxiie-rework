<?php

declare(strict_types=1);

namespace App\Tenant\Contact;

use App\Platform\Tenancy\TenantContext;

/**
 * Coordinates contact message persistence and notification queueing.
 */
final class ContactMessageService
{
    public function __construct(
        private readonly ContactMessageRepository $messages,
        private readonly ContactNotificationService $notifications,
    ) {
    }

    public function receive(
        TenantContext $tenant,
        string $senderName,
        string $senderEmail,
        string $message,
        ?string $subject = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $country = null,
        ?string $region = null,
        ?string $city = null,
    ): int {
        $messageId = $this->messages->create(
            tenant: $tenant,
            senderName: $senderName,
            senderEmail: $senderEmail,
            message: $message,
            subject: $subject,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            country: $country,
            region: $region,
            city: $city,
        );

        $this->notifications->queueContactNotification(
            tenant: $tenant,
            senderName: $senderName,
            senderEmail: $senderEmail,
            message: $message,
        );

        return $messageId;
    }
}

// End of file.
