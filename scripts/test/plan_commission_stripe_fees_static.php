<?php

declare(strict_types=1);

/**
 * Regression checks for plan-specific commissions and artist-paid card fees.
 */

$root = dirname(__DIR__, 2);
$failures = [];

$checks = [
    'database/migrations/0066_plan_sales_commission.sql' => [
        'platform_commission_basis_points',
        "WHEN 'free' THEN 1000",
        "WHEN 'studio' THEN 500",
        "WHEN 'pro' THEN 300",
        "WHEN 'collective' THEN 200",
    ],
    'app/Http/Controllers/Platform/Admin/PricingController.php' => [
        'platform_commission_percent',
        'platform_commission_basis_points',
        'Commission %',
    ],
    'app/Http/Controllers/Tenant/SalesController.php' => [
        "p.platform_commission_basis_points",
        "\$commissionBasisPoints = (int) \$planFees['platform_commission_basis_points'];",
        "\$commissionCents + (int) \$fees['credit_card_fee_cents']",
    ],
    'app/Http/Controllers/Tenant/Admin/BillingController.php' => [
        "\$plan['platform_commission_basis_points']",
        'Seller receives sale amount minus platform commission',
    ],
    'app/Http/Controllers/Platform/PricingController.php' => [
        'commission varies by pricing plan',
        'Artists also pay Stripe processing charges',
        'private function commissionLabel',
    ],
    'app/Tenant/Sales/StripeCheckoutService.php' => [
        'payment_intent_data[application_fee_amount]',
        'artsfolio_commission_cents',
        'artsfolio_estimated_stripe_fee_cents',
    ],
];

foreach ($checks as $relative => $markers) {
    $source = @file_get_contents($root . '/' . $relative);

    if (!is_string($source)) {
        $failures[] = "Missing readable file: {$relative}";
        continue;
    }

    foreach ($markers as $marker) {
        if (!str_contains($source, $marker)) {
            $failures[] = "{$relative} missing marker: {$marker}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Plan commission and Stripe fee check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Plan commissions and artist-paid Stripe economics passed.\n";

// End of file.
