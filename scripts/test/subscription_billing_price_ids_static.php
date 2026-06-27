<?php

declare(strict_types=1);

/**
 * Static checks for stable Stripe Price-ID subscription billing.
 */

$root = dirname(__DIR__, 2);
$files = [
    'migration' => $root . '/database/migrations/0051_stable_stripe_plan_price_ids.sql',
    'service' => $root . '/app/Platform/Billing/StripeSubscriptionCheckoutService.php',
    'webhook' => $root . '/app/Http/Controllers/Platform/StripeWebhookController.php',
    'pricing' => $root . '/app/Http/Controllers/Platform/Admin/PricingController.php',
    'billing' => $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php',
    'signup' => $root . '/app/Platform/Signup/TenantSignupService.php',
    'applicator' => $root . '/scripts/billing/apply_pending_subscription_changes.php',
    'state' => $root . '/PROJECT_STATE.md',
];
foreach ($files as $label => $path) { if (!is_file($path)) { fwrite(STDERR, "Missing {$label}: {$path}
"); exit(1); } }
$checks = [
    [file_get_contents($files['migration']), 'stripe_monthly_price_id', 'migration must add plan monthly Price ID'],
    [file_get_contents($files['migration']), 'stripe_subscription_item_id', 'migration must add subscription item ID'],
    [file_get_contents($files['service']), "'line_items[0][price]'", 'Checkout must use stable Stripe Price ID'],
    [file_get_contents($files['service']), 'updateSubscriptionPrice', 'service must update subscription item price'],
    [file_get_contents($files['service']), 'cancelSubscriptionNow', 'service must cancel subscriptions'],
    [file_get_contents($files['webhook']), 'customer.subscription.created', 'webhook must capture subscription creation'],
    [file_get_contents($files['webhook']), 'stripe_subscription_item_id', 'webhook must store subscription item ID'],
    [file_get_contents($files['pricing']), 'stripe_monthly_price_id', 'platform pricing must edit Price IDs'],
    [file_get_contents($files['billing']), 'updateStripeSubscriptionPrice', 'tenant billing must support paid-to-paid Price ID updates'],
    [file_get_contents($files['signup']), 'stripe_monthly_price_id', 'signup plan data must include Price IDs'],
    [file_get_contents($files['applicator']), 'target_stripe_monthly_price_id', 'scheduled applicator must use target Price IDs'],
    [file_get_contents($files['state']), 'stable Stripe Price IDs', 'PROJECT_STATE must record stable Price IDs'],
];
foreach ($checks as [$haystack, $needle, $message]) { if (!str_contains($haystack, $needle)) { fwrite(STDERR, "Missing {$message}: {$needle}
"); exit(1); } }
if (str_contains(file_get_contents($files['service']), 'line_items[0][price_data][recurring][interval]')) { fwrite(STDERR, "Recurring subscription checkout still uses dynamic price_data.
"); exit(1); }
echo "Subscription billing stable Stripe Price ID static checks passed.
";

// End of file.
