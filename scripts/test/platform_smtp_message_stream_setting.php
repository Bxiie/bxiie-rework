<?php

/**
 * Regression test for platform-admin Postmark message stream setting support.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$settings = file_get_contents($root . '/app/Http/Controllers/Platform/Admin/SettingsController.php') ?: '';
$factory = file_get_contents($root . '/app/Platform/Email/EmailSenderFactory.php') ?: '';
$smtp = file_get_contents($root . '/app/Platform/Email/SmtpEmailSender.php') ?: '';
$worker = file_get_contents($root . '/scripts/workers/email_run_once.php') ?: '';

foreach ([
    'settings form field' => 'name="smtp_x_pm_message_stream"',
    'settings persistence' => "set('smtp_x_pm_message_stream'",
    'factory settings reader' => "get('smtp_x_pm_message_stream'",
    'factory SMTP username reader' => "get('smtp_username'",
    'factory SMTP password reader' => "get('smtp_password'",
    'factory SMTP encryption reader' => "get('smtp_encryption'",
    'Postmark header name' => 'X-PM-Message-Stream',
    'SMTP AUTH support' => 'AUTH PLAIN',
    'SMTP STARTTLS support' => 'STARTTLS',
    'worker platform settings factory' => 'EmailSenderFactory::fromPlatformSettings',
] as $label => $needle) {
    $haystack = $label === 'settings form field' || $label === 'settings persistence' ? $settings : ($label === 'worker platform settings factory' ? $worker : $factory . $smtp);
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$label}.\n");
        exit(1);
    }
}

echo "Platform SMTP Postmark message stream setting is wired to SMTP headers.\n";

// End of file.
