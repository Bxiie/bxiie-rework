<?php

declare(strict_types=1);

/**
 * Static coverage for the subscription billing hardening pass.
 */

$root = dirname(__DIR__, 2);

$files = [
    'migration' => $root . '/database/migrations/0050_subscription_billing_hardening.sql',
    'webhook' => $root . '/app/Http/Controllers/Platform/StripeWebhookController.php',
    'applicator' => $root . '/scripts/billing/apply_pending_subscription_changes.php',
    'admin_docs' => $root . '/docs/admin/subscription_billing_hardening.md',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}
");
        exit(1);
    }
}

$migration = file_get_contents($files['migration']);
$webhook = file_get_contents($files['webhook']);
$applicator = file_get_contents($files['applicator']);
$adminDocs = file_get_contents($files['admin_docs']);
$state = file_get_contents($files['state']);

$required = [
    [$migration, 'last_payment_failed_at', 'migration must store failed-payment timestamp'],
    [$migration, 'billing_action_required_at', 'migration must store billing-action timestamp'],
    [$migration, 'pending_change_applied_at', 'migration must track applied scheduled plan changes'],
    [$webhook, 'invoice.payment_failed', 'webhook must handle failed invoices'],
    [$webhook, 'customer.subscription.updated', 'webhook must sync subscription updates'],
    [$webhook, 'customer.subscription.deleted', 'webhook must handle deleted subscriptions'],
    [$webhook, 'markBillingInvoicePaymentFailed', 'webhook must include failed-payment helper'],
    [$webhook, 'syncBillingSubscriptionUpdated', 'webhook must include subscription sync helper'],
    [$webhook, 'markBillingSubscriptionDeleted', 'webhook must include subscription delete helper'],
    [$applicator, 'pending_change_type IN ("downgrade", "cancel")', 'applicator must process scheduled downgrade/cancel changes'],
    [$applicator, '--dry-run', 'applicator must have dry-run mode'],
    [$applicator, 'Scheduled cancellation applied locally', 'applicator must apply cancellations'],
    [$adminDocs, 'invoice.payment_failed', 'admin docs must describe failed-payment webhook event'],
    [$state, 'subscription billing hardening', 'PROJECT_STATE must record the hardening pass'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}
");
        exit(1);
    }
}

echo "Subscription billing hardening static checks passed.
";

// End of file.
