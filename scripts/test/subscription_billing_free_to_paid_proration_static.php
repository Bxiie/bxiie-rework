<?php

declare(strict_types=1);

/**
 * Static coverage for free/no-subscription to paid checkout proration repair.
 */

$root = dirname(__DIR__, 2);

$files = [
    'billing_controller' => $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php',
    'stripe_service' => $root . '/app/Platform/Billing/StripeSubscriptionCheckoutService.php',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$billing = file_get_contents($files['billing_controller']);
$stripe = file_get_contents($files['stripe_service']);
$state = file_get_contents($files['state']);

$required = [
    [$billing, 'billablePaidUpgradeForProration', 'billing controller must have a paid-upgrade proration gate'],
    [$billing, 'stripe_subscription_id', 'proration gate must require an existing Stripe subscription'],
    [$billing, '[\'active\', \'past_due\', \'unpaid\']', 'proration gate must limit existing paid billing states'],
    [$billing, '$billablePaidUpgrade ? $this->prorationCents', 'proration must only be calculated for billable paid upgrades'],
    [$billing, 'if ($billablePaidUpgrade && $this->canUpdateStripeSubscriptionPrice', 'Stripe subscription update path must use the same paid-upgrade gate'],
    [$billing, '$this->recordPendingPaidPlanChange($tenant, $currentPlan, $targetPlan, $billablePaidUpgrade ? \'upgrade\' : \'paid_start\', $prorationCents);', 'Checkout fallback must keep current plan and mark free/no-subscription starts as paid_start'],
    [$billing, '\'plan_id\' => (int) ($currentPlan[\'id\'] ?? 0)', 'Pending checkout insert must preserve current/free plan as active entitlement'],
    [$stripe, 'Immediate prorated ArtsFolio plan change', 'Stripe service still supports proration for true paid upgrades'],
    [$state, 'Free-to-paid proration repair', 'PROJECT_STATE must record the free-to-paid repair'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Free-to-paid billing proration static checks passed.\n";

// End of file.
