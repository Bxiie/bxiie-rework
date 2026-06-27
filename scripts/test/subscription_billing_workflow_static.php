<?php

declare(strict_types=1);

/**
 * Static coverage for subscription billing workflow surfaces.
 */

$root = dirname(__DIR__, 2);
$files = [
    'migration' => $root . '/database/migrations/0049_subscription_billing_workflow.sql',
    'service' => $root . '/app/Platform/Billing/StripeSubscriptionCheckoutService.php',
    'tenant_billing' => $root . '/app/Http/Controllers/Tenant/Admin/BillingController.php',
    'signup' => $root . '/app/Http/Controllers/Platform/SignupController.php',
    'signup_service' => $root . '/app/Platform/Signup/TenantSignupService.php',
    'pricing' => $root . '/app/Http/Controllers/Platform/PricingController.php',
    'captcha' => $root . '/app/Services/FirstPartyCaptcha.php',
    'tenant_admin' => $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php',
    'webhook' => $root . '/app/Http/Controllers/Platform/StripeWebhookController.php',
];
foreach ($files as $label => $path) {
    if (!is_file($path)) { fwrite(STDERR, "Missing {$label}: {$path}
"); exit(1); }
    $$label = file_get_contents($path) ?: '';
}
$requirements = [
    [$migration, 'current_period_ends_at', 'migration must store recurrence date'],
    [$migration, 'pending_plan_id', 'migration must store pending plan'],
    [$migration, 'stripe_subscription_id', 'migration must store subscription id'],
    [$service, "'mode' => 'subscription'", 'Stripe checkout must use subscription mode'],
    [$service, "'payment_method_collection' => 'always'", 'Stripe checkout must collect card details'],
    [$tenant_billing, 'Type CHANGE PLAN to confirm', 'tenant plan changes must require definite confirmation'],
    [$tenant_billing, 'prorationCents', 'tenant billing must calculate immediate prorated upgrade charges'],
    [$tenant_billing, 'schedulePlanChange', 'tenant billing must schedule downgrades/cancellations'],
    [$tenant_billing, 'You keep current-plan features until', 'tenant billing must explain retained access until recurrence'],
    [$signup, 'Paid plans require card details and are billed immediately, then monthly', 'signup must warn paid plans bill immediately'],
    [$signup, 'createSubscriptionSession', 'paid signup must start Stripe checkout'],
    [$signup_service, 'payment_pending', 'paid signup must be marked payment pending'],
    [$pricing, 'cardFeesLabel($plan) :', 'pricing grid must show dash for free credit-card rates'],
    [$pricing, "? 'Included' : '-'", 'pricing grid must use dash instead of Not included'],
    [$captcha, 'I am a real person.', 'captcha text must be shortened'],
    [$tenant_admin, 'Recurring billing date', 'platform tenant admin must show recurring billing date'],
    [$tenant_admin, 'Stripe subscription', 'platform tenant admin must show billing details'],
    [$webhook, 'invoice.paid', 'webhook must recognize monthly rebilling events'],
];
foreach ($requirements as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) { fwrite(STDERR, "Missing {$message}: {$needle}
"); exit(1); }
}
if (str_contains($pricing, '0.00% + $0.00')) { fwrite(STDERR, "Pricing must not expose 0.00% + $0.00 for Free checkout.
"); exit(1); }
if (str_contains($captcha, 'not an automated form submission')) { fwrite(STDERR, "Old captcha wording is still present.
"); exit(1); }
echo "Subscription billing workflow static checks passed.
";

// End of file.
