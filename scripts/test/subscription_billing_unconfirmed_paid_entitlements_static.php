<?php

declare(strict_types=1);

/**
 * Static coverage for broad unconfirmed paid entitlement repair.
 */

$root = dirname(__DIR__, 2);

$files = [
    'repair_command' => $root . '/scripts/billing/repair_unconfirmed_paid_entitlements.php',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$repair = file_get_contents($files['repair_command']);
$state = file_get_contents($files['state']);

$required = [
    [$repair, 'repair_unconfirmed_paid_entitlements.php', 'broad repair command must identify itself'],
    [$repair, 'current_plan.monthly_price_cents > 0', 'broad repair must target paid local entitlements'],
    [$repair, '(tpa.stripe_subscription_id IS NULL OR tpa.stripe_subscription_id = "")', 'broad repair must require missing Stripe subscription'],
    [$repair, '--include-active', 'broad repair must support explicit active-row inclusion'],
    [$repair, '--tenant:', 'broad repair must support tenant filter'],
    [$repair, '--dry-run', 'broad repair must support dry run'],
    [$repair, '--apply', 'broad repair must require explicit apply'],
    [$repair, 'plan_id = :free_plan_id', 'broad repair must restore active plan to Free'],
    [$repair, 'pending_plan_id = CASE WHEN pending_plan_id IS NULL THEN plan_id ELSE pending_plan_id END', 'broad repair must preserve pending paid target'],
    [$state, 'Broad unconfirmed paid entitlement repair', 'PROJECT_STATE must record broad entitlement repair'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Broad unconfirmed paid entitlement repair static checks passed.\n";

// End of file.
