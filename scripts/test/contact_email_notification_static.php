<?php

declare(strict_types=1);

/**
 * Static regression checks for public contact/signup notification routing.
 */

$root = dirname(__DIR__, 2);

$contactService = file_get_contents($root . '/app/Tenant/Contact/ContactNotificationService.php');
$signupService = file_get_contents($root . '/app/Tenant/Signup/SignupNotificationService.php');
$emailOutbox = file_get_contents($root . '/scripts/test/email_outbox.php');
$tenantNotifications = file_get_contents($root . '/scripts/test/tenant_notifications.php');
$contactSignupRecords = file_get_contents($root . '/scripts/test/contact_signup_records.php');

$checks = [
    'contact notification fallback' => $contactService !== false && str_contains($contactService, "FALLBACK_NOTIFICATION_EMAIL = 'info@artsfol.io'"),
    'contact notification queues when fallback is configured' => $contactService !== false && str_contains($contactService, 'ARTSFOLIO_DEFAULT_NOTIFICATION_EMAIL') && !str_contains($contactService, 'if (!$adminEmail)'),
    'signup notification fallback' => $signupService !== false && str_contains($signupService, "FALLBACK_NOTIFICATION_EMAIL = 'info@artsfol.io'"),
    'outbox smoke queues to real mailbox' => $emailOutbox !== false && substr_count($emailOutbox, "recipientEmail: 'info@artsfol.io'") >= 3,
    'tenant notification smoke recipient is real mailbox' => $tenantNotifications !== false && str_contains($tenantNotifications, "site_admin_email', 'info@artsfol.io'"),
    'contact/signup record smoke recipient is real mailbox' => $contactSignupRecords !== false && str_contains($contactSignupRecords, "site_admin_email', 'info@artsfol.io'"),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "Failed contact email notification static check: {$label}\n");
        exit(1);
    }
}

echo "Contact email notification static checks passed.\n";

// End of file.
