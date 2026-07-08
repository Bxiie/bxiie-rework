<?php

declare(strict_types=1);

/**
 * Static regression checks for sales workflow fulfillment emails, refunds, and
 * paid-sale default filters. These checks intentionally look for concrete
 * feature markers because the app does not have a lightweight HTTP test harness
 * for authenticated tenant-admin sales flows.
 */
$root = dirname(__DIR__, 2);
$files = [
    'tenant sales controller' => $root . '/app/Http/Controllers/Tenant/Admin/SalesController.php',
    'tenant analytics controller' => $root . '/app/Http/Controllers/Tenant/Admin/SalesAnalyticsController.php',
    'platform sales controller' => $root . '/app/Http/Controllers/Platform/Admin/SalesController.php',
    'platform analytics controller' => $root . '/app/Http/Controllers/Platform/Admin/SalesAnalyticsController.php',
    'sales repository' => $root . '/app/Tenant/Sales/SalesRepository.php',
    'shipping email migration' => $root . '/database/migrations/0063_sales_workflow_shipping_email.sql',
    'tenant routes' => $root . '/app/Http/Routes/tenant.php',
    'admin docs' => $root . '/docs/admin/sales-cart-products.md',
    'user docs' => $root . '/docs/user/sales-orders.md',
    'dev docs' => $root . '/docs/dev/sales-workflow.md',
    'project state' => $root . '/PROJECT_STATE.md',
];

$contents = [];
foreach ($files as $label => $path) {
    $body = is_file($path) ? file_get_contents($path) : false;
    if ($body === false) {
        fwrite(STDERR, "[FAIL] Missing {$label}: {$path}\n");
        exit(1);
    }
    $contents[$label] = $body;
}

$checks = [
    'tenant sales controller' => [
        'EmailOutboxRepository',
        'send_shipping_email',
        'queueShippingNotification',
        'sales.shipping_notification',
        'Show no-sale checkout rows',
        'Create Stripe refund',
    ],
    'sales repository' => [
        'public function orders(TenantContext $tenant, int $limit = 100, bool $includeNoSales = false): array',
        'public function platformOrders(int $limit = 200, bool $includeNoSales = false): array',
        'public function markShippingEmailQueued',
        '"partially_refunded", "refunded"',
        "'refunded'",
    ],
    'shipping email migration' => [
        'shipping_email_sent_at',
        'shipping_email_outbox_id',
        'idx_sales_orders_shipping_email',
    ],
    'tenant routes' => [
        'new EmailOutboxRepository($pdo)))->update',
        "post('/admin/sales/refund'",
    ],
    'tenant analytics controller' => [
        'Include no-sale workflow rows',
        'tenantSalesSummary($tenant, $includeNoSales)',
    ],
    'platform sales controller' => [
        'Show no-sale checkout rows',
        'platformOrders(200, $includeNoSales)',
    ],
    'platform analytics controller' => [
        'Include no-sale workflow rows',
        'platformSalesSummary($includeNoSales)',
    ],
    'admin docs' => [
        'Email shipping details to buyer',
        'Show no-sale checkout rows',
        'Create Stripe refund',
    ],
    'user docs' => [
        'shipping details',
        'tracking number',
    ],
    'dev docs' => [
        'sales.shipping_notification',
        'shipping_email_sent_at',
    ],
    'project state' => [
        'Sales workflow fulfillment',
    ],
];

$missing = [];
foreach ($checks as $label => $needles) {
    foreach ($needles as $needle) {
        if (!str_contains($contents[$label], $needle)) {
            $missing[] = "{$label}: {$needle}";
        }
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Sales workflow shipping email static check failed:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo "[PASS] Sales workflow shipping email static check passed.\n";

// End of file.
