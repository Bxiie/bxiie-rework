<?php

declare(strict_types=1);

/**
 * Static coverage for unpaid Stripe Checkout entitlement activation repair.
 */

$root = dirname(__DIR__, 2);

$files = [
    'billing_controller' => $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php',
    'webhook' => $root . '/app/Http/Controllers/Platform/StripeWebhookController.php',
    'repair_command' => $root . '/scripts/billing/repair_unpaid_paid_start_entitlements.php',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$billing = file_get_contents($files['billing_controller']);
$webhook = file_get_contents($files['webhook']);
$repair = file_get_contents($files['repair_command']);
$state = file_get_contents($files['state']);

$required = [
    [$billing, <<<'NEEDLE'
recordPendingPaidPlanChange(TenantContext $tenant, array $currentPlan, array $targetPlan
NEEDLE, 'pending checkout recorder must receive current plan'],
    [$billing, <<<'NEEDLE'
'plan_id' => (int) ($currentPlan['id'] ?? 0)
NEEDLE, 'pending checkout insert must keep current plan as plan_id'],
    [$billing, <<<'NEEDLE'
paid entitlement is activated only after Stripe confirms payment
NEEDLE, 'pending checkout recorder must document checkout entitlement guard'],
    [$billing, <<<'NEEDLE'
$this->recordPendingPaidPlanChange($tenant, $currentPlan, $targetPlan
NEEDLE, 'caller must pass current plan into pending checkout recorder'],
    [$webhook, <<<'NEEDLE'
markBillingCheckoutCompleted
NEEDLE, 'Stripe webhook must retain checkout-completed activation path'],
    [$webhook, <<<'NEEDLE'
billing_status = "active"
NEEDLE, 'Stripe webhook must activate billing only after checkout completion'],
    [$repair, <<<'NEEDLE'
repair_unpaid_paid_start_entitlements.php
NEEDLE, 'repair command must identify itself'],
    [$repair, <<<'NEEDLE'
payment_pending
NEEDLE, 'repair command must target payment_pending rows'],
    [$repair, <<<'NEEDLE'
pending_change_type = "paid_start"
NEEDLE, 'repair command must target paid_start rows'],
    [$repair, <<<'NEEDLE'
plan_id = pending_plan_id
NEEDLE, 'repair command must target accidentally activated plan rows'],
    [$repair, <<<'NEEDLE'
--dry-run
NEEDLE, 'repair command must support dry run'],
    [$repair, <<<'NEEDLE'
--apply
NEEDLE, 'repair command must require explicit apply'],
    [$state, <<<'NEEDLE'
Unpaid Stripe Checkout entitlement repair
NEEDLE, 'PROJECT_STATE must record unpaid checkout entitlement repair'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Stripe Checkout entitlement activation static checks passed.\n";

// End of file.
