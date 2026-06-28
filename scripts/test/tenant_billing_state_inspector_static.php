<?php

declare(strict_types=1);

/**
 * Static coverage for tenant billing state inspector.
 */

$root = dirname(__DIR__, 2);
$command = $root . '/scripts/billing/inspect_tenant_billing_state.php';

if (!is_file($command)) {
    fwrite(STDERR, "Missing inspector command: {$command}\n");
    exit(1);
}

$source = file_get_contents($command);

$required = [
    [$source, 'inspect_tenant_billing_state.php', 'inspector command must identify itself'],
    [$source, '--tenant:', 'inspector command must document tenant filter'],
    [$source, 'tenant_plan_assignments', 'inspector must read tenant plan assignments'],
    [$source, 'stripe_webhook_events', 'inspector must read webhook events when present'],
    [$source, 'email_outbox', 'inspector must read email outbox when present'],
    [$source, 'current_plan_slug', 'inspector must include current plan slug'],
    [$source, 'pending_plan_slug', 'inspector must include pending plan slug'],
    [$source, 'stripe_checkout_session_id', 'inspector must include checkout session ID'],
    [$source, 'stripe_subscription_id', 'inspector must include subscription ID'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Tenant billing state inspector static checks passed.\n";

// End of file.
