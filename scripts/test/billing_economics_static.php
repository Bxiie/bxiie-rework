<?php

/**
 * Static regression checks for signup route expectations and sales fee disclosures.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'scripts/test/route_inventory.php' => ['Tenant GET /signup is missing', 'Tenant POST /signup is missing'],
    'database/migrations/0029_plan_payment_fees_and_signup_route.sql' => ['credit_card_fee_basis_points', 'credit_card_fixed_fee_cents', 'seller_net_cents'],
    'app/Http/Controllers/Platform/Admin/PricingController.php' => ['credit_card_fee_percent', 'credit_card_fixed_fee_dollars', 'Complimentary tenants waive only subscription billing'],
    'app/Http/Controllers/Platform/PricingController.php' => ['How payouts work', 'credit card charges', 'Complimentary tenants do not pay subscription fees'],
    'app/Http/Controllers/Tenant/Admin/BillingController.php' => ['Estimated seller proceeds', 'credit card charges still apply'],
    'app/Http/Controllers/Tenant/SalesController.php' => ['saleEconomics', 'credit_card_fee_cents', 'seller_net_cents'],
    'app/Tenant/Sales/SalesRepository.php' => ['credit_card_fee_cents', 'seller_net_cents'],
];

foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$relative}\n");
        exit(1);
    }
    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            fwrite(STDERR, "Missing marker {$needle} in {$relative}\n");
            exit(1);
        }
    }
}

echo "Billing economics static checks passed.\n";

// End of file.
