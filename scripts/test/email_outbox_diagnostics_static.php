<?php

/**
 * Static regression checks for platform email outbox diagnostics.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Platform/Admin/EmailOutboxController.php');
$factory = file_get_contents($root . '/app/Platform/Email/EmailSenderFactory.php');

$requiredControllerFragments = [
    "email_outbox.last_error",
    "last_error",
    "admin-error-details",
    "Diagnostic",
];

foreach ($requiredControllerFragments as $fragment) {
    if (!str_contains($controller, $fragment)) {
        fwrite(STDERR, "Email outbox diagnostics missing expected fragment: {$fragment}\n");
        exit(1);
    }
}

$requiredFactoryFragments = [
    "getenv('EMAIL_DRIVER') ?: getenv('MAIL_TRANSPORT')",
    "'log' ? 'dry_run'",
];

foreach ($requiredFactoryFragments as $fragment) {
    if (!str_contains($factory, $fragment)) {
        fwrite(STDERR, "Email sender factory does not honor expected mail transport fragment: {$fragment}\n");
        exit(1);
    }
}

echo "Email outbox diagnostics and mail transport aliases are wired.\n";

// End of file.
