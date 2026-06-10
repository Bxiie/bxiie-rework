<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Queues lifecycle and account emails.
 */
final class LifecycleEmailService
{
    public function __construct(
        private readonly EmailOutboxRepository $outbox,
        private readonly TemplateRenderer $renderer,
        private readonly string $templateRoot,
    ) {
    }

    public function queuePasswordReset(
        string $recipientEmail,
        string $resetUrl,
        ?int $userId = null,
    ): int {
        $body = $this->renderer->renderFile(
            $this->templateRoot . '/auth/password-reset-request.md',
            ['reset_url' => $resetUrl],
        );

        return $this->outbox->queue(
            recipientEmail: $recipientEmail,
            subject: 'Reset your ArtsFolio password',
            bodyText: $body,
            userId: $userId,
            templateKey: 'auth.password_reset_request',
        );
    }

    public function queueEmailVerification(
        string $recipientEmail,
        string $verificationUrl,
        ?int $userId = null,
    ): int {
        $body = $this->renderer->renderFile(
            $this->templateRoot . '/auth/email-verification-request.md',
            ['verification_url' => $verificationUrl],
        );

        return $this->outbox->queue(
            recipientEmail: $recipientEmail,
            subject: 'Verify your ArtsFolio email',
            bodyText: $body,
            userId: $userId,
            templateKey: 'auth.email_verification_request',
        );
    }

    public function queueWelcome(
        string $recipientEmail,
        ?string $recipientName = null,
        ?int $tenantId = null,
        ?int $userId = null,
        int $delaySeconds = 21600,
    ): int {
        $body = $this->renderer->renderFile(
            $this->templateRoot . '/lifecycle/welcome.md',
            ['recipient_name' => $recipientName ?: 'there'],
        );

        return $this->outbox->queue(
            recipientEmail: $recipientEmail,
            subject: 'Welcome to ArtsFolio',
            bodyText: $body,
            recipientName: $recipientName,
            tenantId: $tenantId,
            userId: $userId,
            templateKey: 'lifecycle.welcome',
            availableAfterSeconds: $delaySeconds,
        );
    }
}

// End of file.
