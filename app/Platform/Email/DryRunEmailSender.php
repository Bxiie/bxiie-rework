<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Dry-run email sender used for local development.
 *
 * This sender does not contact SMTP or external email providers.
 */
final class DryRunEmailSender implements EmailSenderInterface
{
    public function send(array $email): string
    {
        return json_encode([
            'dry_run' => true,
            'id' => isset($email['id']) ? (int) $email['id'] : null,
            'to' => $email['recipient_email'],
            'subject' => $email['subject'],
            'template_key' => $email['template_key'],
            'body_preview' => mb_substr((string) $email['body_text'], 0, 240),
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}

// End of file.
