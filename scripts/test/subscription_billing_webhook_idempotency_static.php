<?php

declare(strict_types=1);

/**
 * Static coverage for Stripe webhook event logging and idempotency.
 */

$root = dirname(__DIR__, 2);

$files = [
    'migration' => $root . '/database/migrations/0053_stripe_webhook_event_log.sql',
    'webhook' => $root . '/app/Http/Controllers/Platform/StripeWebhookController.php',
    'admin_docs' => $root . '/docs/admin/subscription_billing_webhook_events.md',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$migration = file_get_contents($files['migration']);
$webhook = file_get_contents($files['webhook']);
$adminDocs = file_get_contents($files['admin_docs']);
$state = file_get_contents($files['state']);

$required = [
    [$migration, 'CREATE TABLE IF NOT EXISTS stripe_webhook_events', 'migration must create webhook event log table'],
    [$migration, 'UNIQUE KEY uniq_stripe_webhook_events_event_id', 'migration must make event_id unique'],
    [$migration, "ENUM('processing', 'processed', 'failed', 'ignored')", 'migration must track processing status'],
    [$migration, 'payload_hash CHAR(64)', 'migration must store payload hash'],
    [$webhook, 'beginWebhookEvent', 'webhook must begin idempotent event record'],
    [$webhook, 'finishWebhookEvent', 'webhook must mark events processed'],
    [$webhook, 'failWebhookEvent', 'webhook must mark events failed'],
    [$webhook, "'duplicate' => true", 'webhook must ignore duplicate events'],
    [$webhook, 'missing_event_id', 'webhook must reject events without IDs'],
    [$webhook, 'stripe_webhook_events', 'webhook must write to event log table'],
    [$webhook, 'catch (\\Throwable $e)', 'webhook must record processing errors'],
    [$adminDocs, 'stripe_webhook_events', 'admin docs must describe webhook event table'],
    [$state, 'Stripe webhook event logging', 'PROJECT_STATE must record webhook logging pass'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Subscription billing webhook idempotency static checks passed.\n";

// End of file.
