<?php

declare(strict_types=1);

/**
 * Static regression checks for email outbox UTC scheduling.
 *
 * The operations monitor evaluates email_outbox.available_at against
 * UTC_TIMESTAMP(). These checks keep queue writers and worker claim logic on
 * the same clock so queued lifecycle emails do not appear falsely stale before
 * the worker considers them ready.
 */

$root = dirname(__DIR__, 2);
$checks = [
    'app/Platform/Email/EmailOutboxRepository.php' => [
        'DATE_ADD(UTC_TIMESTAMP(), INTERVAL :available_after SECOND)',
        'available_at <= UTC_TIMESTAMP()',
        'sent_at = UTC_TIMESTAMP()',
        'failed_at = UTC_TIMESTAMP()',
    ],
    'app/Platform/Signup/TenantSignupService.php' => [
        <<<'NEEDLE'
'available_at' => gmdate('Y-m-d H:i:s', time() + $delaySeconds),
NEEDLE,
        <<<'NEEDLE'
return gmdate('Y-m-d H:i:s');
NEEDLE,
    ],
    'scripts/email/queue_lifecycle_emails.php' => [
        <<<'NEEDLE'
$availableAt = gmdate('Y-m-d H:i:s', time() + (int) $message['delay_seconds']);
NEEDLE,
        'UTC_TIMESTAMP(),',
    ],
    'scripts/email/reconcile_tenant_lifecycle_emails.php' => [
        'UTC_TIMESTAMP(),',
    ],
];

$problems = [];

foreach ($checks as $relativePath => $needles) {
    $path = $root . '/' . $relativePath;
    if (!is_file($path)) {
        $problems[] = "Missing file: {$relativePath}";
        continue;
    }

    $source = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $problems[] = "Missing {$needle} in {$relativePath}";
        }
    }
}

if ($problems !== []) {
    fwrite(STDERR, "Email outbox UTC static check failed:
");
    foreach ($problems as $problem) {
        fwrite(STDERR, " - {$problem}
");
    }
    exit(1);
}

echo "Email outbox UTC static checks passed.
";

// End of file.
