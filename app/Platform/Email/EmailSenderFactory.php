<?php

/**
 * Email sender factory.
 */

declare(strict_types=1);

namespace App\Platform\Email;

use App\Platform\Settings\PlatformSettingsRepository;

/**
 * Creates email sender implementations from platform settings with environment
 * fallback for development and worker bootstrap safety.
 */
final class EmailSenderFactory
{
    public static function fromPlatformSettings(PlatformSettingsRepository $settings): EmailSenderInterface
    {
        $smtpHost = trim($settings->get('smtp_host', ''));

        if ($smtpHost === '') {
            return new DryRunEmailSender();
        }

        $messageStream = trim($settings->get('smtp_x_pm_message_stream', ''));
        $headers = [];
        if ($messageStream !== '') {
            $headers['X-PM-Message-Stream'] = $messageStream;
        }

        return new SmtpEmailSender(
            host: $smtpHost,
            port: (int) $settings->get('smtp_port', '587'),
            fromEmail: $settings->get('mail_from_email', 'no-reply@artsfol.io'),
            fromName: $settings->get('mail_from_name', 'ArtsFolio'),
            headers: $headers,
        );
    }

    public static function fromEnvironment(): EmailSenderInterface
    {
        $driver = getenv('EMAIL_DRIVER') ?: getenv('MAIL_TRANSPORT') ?: 'dry_run';
        $driver = $driver === 'log' ? 'dry_run' : $driver;

        if ($driver === 'smtp') {
            $messageStream = trim((string) (getenv('SMTP_X_PM_MESSAGE_STREAM') ?: ''));
            $headers = [];
            if ($messageStream !== '') {
                $headers['X-PM-Message-Stream'] = $messageStream;
            }

            $headers = array_merge($headers, self::parseExtraHeaders((string) (getenv('SMTP_EXTRA_HEADERS') ?: '')));

            return new SmtpEmailSender(
                host: getenv('SMTP_HOST') ?: '127.0.0.1',
                port: (int) (getenv('SMTP_PORT') ?: '1025'),
                fromEmail: getenv('MAIL_FROM_EMAIL') ?: 'no-reply@artsfol.io',
                fromName: getenv('MAIL_FROM_NAME') ?: 'ArtsFolio',
                headers: $headers,
            );
        }

        return new DryRunEmailSender();
    }

    private static function parseExtraHeaders(string $rawHeaders): array
    {
        $headers = [];

        foreach (explode(';', $rawHeaders) as $headerLine) {
            $headerLine = trim($headerLine);

            if ($headerLine === '' || !str_contains($headerLine, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $headerLine, 2);

            $name = trim($name);
            $value = trim($value);

            if ($name !== '' && $value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}

// End of file.
