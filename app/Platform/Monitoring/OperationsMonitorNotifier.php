<?php

declare(strict_types=1);

namespace App\Platform\Monitoring;

use App\Platform\Email\BrandedEmail;
use App\Platform\Email\DryRunEmailSender;
use App\Platform\Email\EmailSenderFactory;
use App\Platform\Settings\PlatformSettingsRepository;
use PDO;
use Throwable;

/**
 * Sends health reports directly through configured SMTP so worker failure does
 * not prevent the monitor from reporting worker failure.
 */
final class OperationsMonitorNotifier
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly OperationsMonitorRepository $repository,
    ) {
    }

    public function send(HealthReport $report, string $kind): array
    {
        $recipients = $this->repository->platformAdminRecipients();
        if ($recipients === []) {
            throw new \RuntimeException('No active platform owner/admin email recipients were found.');
        }

        $subjectPrefix = match (true) {
            $kind === 'recovery' => '[ArtsFolio RECOVERY]',
            $report->overallStatus() === HealthMetric::CRIT => '[ArtsFolio CRITICAL]',
            $report->overallStatus() === HealthMetric::WARN => '[ArtsFolio WARNING]',
            $kind === 'alert' => '[ArtsFolio ALERT]',
            default => '[ArtsFolio Daily Health OK]',
        };
        $subject = $subjectPrefix . ' ' . $report->hostName . ' ' . gmdate('Y-m-d H:i T');
        $bodyText = $report->toText(false);
        $bodies = BrandedEmail::render($subject, $bodyText);
        $sender = EmailSenderFactory::fromPlatformSettings(new PlatformSettingsRepository($this->pdo));
        $results = [];

        foreach ($recipients as $recipient) {
            $email = [
                'recipient_email' => (string) $recipient['email'],
                'recipient_name' => $recipient['display_name'] ?? null,
                'subject' => $subject,
                'body_text' => $bodies['body_text'],
                'body_html' => $bodies['body_html'],
                'template_key' => 'operations.health.' . $kind,
            ];
            try {
                $results[] = $sender->send($email);
            } catch (Throwable $e) {
                throw new \RuntimeException('Failed to send health report to ' . $recipient['email'] . ': ' . $e->getMessage(), 0, $e);
            }
        }

        return [
            'results' => $results,
            'dry_run' => $sender instanceof DryRunEmailSender,
        ];
    }
}

// End of file.
