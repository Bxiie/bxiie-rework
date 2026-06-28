<?php

declare(strict_types=1);

/**
 * Static coverage for canceled checkout entitlement repair.
 */

$root = dirname(__DIR__, 2);
$command = $root . '/scripts/billing/repair_canceled_checkout_entitlement.php';

if (!is_file($command)) {
    fwrite(STDERR, "Missing repair command: {$command}\n");
    exit(1);
}

$source = file_get_contents($command);

$required = [
    [$source, 'repair_canceled_checkout_entitlement.php', 'repair command must identify itself'],
    [$source, '--tenant:', 'repair command must support tenant filter'],
    [$source, '--dry-run', 'repair command must support dry run'],
    [$source, '--apply', 'repair command must require explicit apply'],
    [$source, 'current_plan.monthly_price_cents > 0', 'repair command must target paid current plan'],
    [$source, 'stripe_checkout_session_id IS NOT NULL', 'repair command must require checkout session evidence'],
    [$source, 'stripe_subscription_id IS NULL OR stripe_subscription_id = ""', 'repair command must require missing subscription confirmation'],
    [$source, 'pending_change_type IN ("upgrade", "paid_start")', 'repair command must catch upgrade-shaped canceled checkouts'],
    [$source, 'plan_id = :free_plan_id', 'repair command must restore Free active entitlement'],
    [$source, 'pending_change_type = "paid_start"', 'repair command must normalize pending paid start'],
    [$source, 'pending_proration_cents = 0', 'repair command must clear bad proration'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Canceled checkout entitlement repair static checks passed.\n";

// End of file.
