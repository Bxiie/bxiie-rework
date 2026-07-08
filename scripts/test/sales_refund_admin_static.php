<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SalesController.php') ?: '';
$repo = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php') ?: '';
$stripe = file_get_contents($root . '/app/Tenant/Sales/StripeCheckoutService.php') ?: '';
$routes = file_get_contents($root . '/app/Http/Routes/tenant.php') ?: '';
$migration = file_get_contents($root . '/database/migrations/0062_sales_order_refunds.sql') ?: '';

$missing = [];
foreach ([
    'refund action route' => "post('/admin/sales/refund'",
    'controller refund method' => 'public function refund(Request $request, TenantContext $tenant, ?array $currentUser): Response',
    'admin refund section' => 'Refund from Stripe',
    'admin refund form' => 'Create Stripe refund',
    'Stripe refund API method' => 'public function refundPaymentIntent(',
    'refund persistence method' => 'public function recordStripeRefund(',
    'refund total method' => 'public function orderRefundTotal(',
    'refund history method' => 'public function orderRefunds(',
    'refund table migration' => 'CREATE TABLE IF NOT EXISTS sales_order_refunds',
    'Stripe refunds endpoint' => 'https://api.stripe.com/v1/refunds',
] as $label => $needle) {
    $haystack = str_contains($label, 'route') ? $routes : (str_contains($label, 'migration') || str_contains($label, 'table') ? $migration : ($label === 'Stripe refund API method' || $label === 'Stripe refunds endpoint' ? $stripe : ($label === 'refund persistence method' || $label === 'refund total method' || $label === 'refund history method' ? $repo : $controller)));
    if (!str_contains($haystack, $needle)) {
        $missing[] = $label . ': ' . $needle;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Sales refund admin static check failed:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo "[PASS] Sales refund admin static check passed.\n";

// End of file.
