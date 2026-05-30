<?php

declare(strict_types=1);

namespace App\Platform\Email;

/**
 * Creates email sender implementations from environment variables.
 */
final class EmailSenderFactory
{
    public static function fromEnvironment(): EmailSenderInterface
    {
        $driver = getenv('EMAIL_DRIVER') ?: 'dry_run';

        if ($driver === 'smtp') {
            return new SmtpEmailSender(
                host: getenv('SMTP_HOST') ?: '127.0.0.1',
                port: (int) (getenv('SMTP_PORT') ?: '1025'),
                fromEmail: getenv('MAIL_FROM_EMAIL') ?: 'no-reply@artsfol.io',
                fromName: getenv('MAIL_FROM_NAME') ?: 'ArtsFolio',
            );
        }

        return new DryRunEmailSender();
    }
}

// End of file.
