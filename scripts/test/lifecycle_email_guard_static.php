<?php

declare(strict_types=1);

/**
 * Static regression checks for lifecycle email safety guards.
 */

$root = dirname(__DIR__, 2);

$repository = file_get_contents($root . '/app/Platform/Email/EmailOutboxRepository.php');
$service = file_get_contents($root . '/app/Platform/Email/LifecycleEmailService.php');
$outboxSmoke = file_get_contents($root . '/scripts/test/email_outbox.php');

$checks = [
    'repository blocks lifecycle without user_id' => $repository !== false
        && str_contains($repository, 'str_starts_with($normalizedTemplateKey, \'lifecycle.\')')
        && str_contains($repository, '$userId === null'),
    'repository blocks lifecycle without tenant_id' => $repository !== false
        && str_contains($repository, '$tenantId === null'),
    'welcome service requires tenant and user ids' => $service !== false
        && str_contains($service, "Refusing to queue lifecycle.welcome without tenant_id and user_id"),
    'outbox smoke never uses platform mailbox' => $outboxSmoke !== false
        && !str_contains($outboxSmoke, "recipientEmail: 'info@artsfol.io'"),
    'outbox smoke rolls back by default' => $outboxSmoke !== false
        && str_contains($outboxSmoke, 'ARTSFOLIO_EMAIL_OUTBOX_TEST_COMMIT')
        && str_contains($outboxSmoke, '$pdo->rollBack();'),
];

foreach ($checks as $label => $ok) {
    if (!$ok) {
        fwrite(STDERR, "Failed lifecycle email guard static check: {$label}\n");
        exit(1);
    }
}

echo "Lifecycle email guard static checks passed.\n";

// End of file.
