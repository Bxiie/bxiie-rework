<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Tenant/Sales/StripeConnectService.php' => [
        'createExpressAccount',
        'createOnboardingLink',
        'retrieveAccount',
        'capabilities[card_payments][requested]',
        'account_onboarding',
    ],
    'app/Http/Controllers/Tenant/Admin/SettingsController.php' => [
        'connectStripe(Request $request, TenantContext $tenant, ?array $currentUser): Response',
        'stripeConnectReturn(Request $request, TenantContext $tenant, ?array $currentUser): Response',
        'stripeConnectRefresh(Request $request, TenantContext $tenant, ?array $currentUser): Response',
        'data-stripe-connect-panel',
        'Connect Stripe',
        'stripe_connect_charges_enabled',
        'stripe_connect_payouts_enabled',
        'stripe_connect_details_submitted',
    ],
    'app/Http/Controllers/Tenant/SalesController.php' => [
        'tenantStripeConnectedAccountId',
        'tenantStripeAccountReady',
        'Checkout is not ready yet',
        '$connectedAccountId,',
    ],
    'app/Http/Routes/tenant.php' => [
        "/admin/settings/stripe/connect",
        "/admin/settings/stripe/return",
        "/admin/settings/stripe/refresh",
    ],
    'docs/dev/stripe-connect.md' => [
        'Stripe Connect commerce flow',
        'Public checkout is allowed only when all readiness flags are true',
    ],
];

$failures = [];
foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    $source = is_file($path) ? (string) file_get_contents($path) : '';
    if ($source === '') {
        $failures[] = "Missing or empty {$relative}";
        continue;
    }
    foreach ($needles as $needle) {
        if (!str_contains($source, $needle)) {
            $failures[] = "{$relative} missing {$needle}";
        }
    }
}

$checkout = (string) file_get_contents($root . '/app/Tenant/Sales/StripeCheckoutService.php');
if (!str_contains($checkout, '?string $idempotencyKey = null')) {
    $failures[] = 'StripeCheckoutService::stripePost does not accept an optional idempotency key.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Stripe Connect onboarding static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "[PASS] Stripe Connect onboarding static check passed.\n";

// End of file.
