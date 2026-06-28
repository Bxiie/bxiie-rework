<?php

declare(strict_types=1);

/**
 * Static coverage for daily platform billing delinquency report emails.
 */

$root = dirname(__DIR__, 2);

$files = [
    'command' => $root . '/scripts/billing/send_billing_delinquency_report.php',
    'service' => $root . '/scripts/systemd/artsfolio-billing-delinquency-report.service',
    'timer' => $root . '/scripts/systemd/artsfolio-billing-delinquency-report.timer',
    'template' => $root . '/template/email/billing/platform-delinquency-report.txt',
    'admin_docs' => $root . '/docs/admin/subscription_billing_delinquency.md',
    'dev_docs' => $root . '/docs/dev/subscription_billing.md',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$command = file_get_contents($files['command']);
$service = file_get_contents($files['service']);
$timer = file_get_contents($files['timer']);
$template = file_get_contents($files['template']);
$adminDocs = file_get_contents($files['admin_docs']);
$devDocs = file_get_contents($files['dev_docs']);
$state = file_get_contents($files['state']);

$required = [
    [$command, 'send_billing_delinquency_report.php', 'report command must identify itself'],
    [$command, 'BillingDelinquencyPolicy::stateFor', 'report command must classify delinquency state'],
    [$command, 'EmailOutboxRepository', 'report command must queue through email_outbox'],
    [$command, "billing.delinquency_daily_report", 'report command must use report template key'],
    [$command, 'platform_admin_recipients', 'report command must resolve platform admin recipients'],
    [$command, "r.slug IN ('owner', 'admin', 'platform_admin')", 'report command must target platform owner/admin roles'],
    [$command, 'report_already_queued_today', 'report command must suppress same-day duplicates'],
    [$command, '--force', 'report command must support forced resend'],
    [$command, '--dry-run', 'report command must support dry run'],
    [$service, 'send_billing_delinquency_report.php --quiet', 'systemd service must run report command'],
    [$service, 'Environment=ARTSFOLIO_ENV_FILE=/etc/artsfolio/artsfolio.env', 'systemd service must use production env file'],
    [$timer, 'OnCalendar=*-*-* 08:15:00', 'systemd timer must run daily'],
    [$timer, 'Persistent=true', 'systemd timer must catch missed runs'],
    [$template, 'ArtsFolio daily billing delinquency report', 'email template must describe report'],
    [$adminDocs, 'artsfolio-billing-delinquency-report.timer', 'admin docs must describe timer'],
    [$devDocs, 'Daily billing delinquency report', 'dev docs must describe report command'],
    [$state, 'daily billing delinquency report', 'PROJECT_STATE must record daily report job'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Subscription billing delinquency report static checks passed.\n";

// End of file.
