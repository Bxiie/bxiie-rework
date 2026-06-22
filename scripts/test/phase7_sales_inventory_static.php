<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$requiredFiles = [
    'database/migrations/0043_sales_inventory_reservations.sql',
    'app/Platform/Jobs/Handlers/ReleaseExpiredSalesReservationsJobHandler.php',
    'scripts/maintenance/release_expired_sales_reservations.php',
    'docs/dev/sales-inventory-reservations.md',
    'docs/admin/sales-inventory.md',
];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Missing {$file}";
    }
}

$repo = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php') ?: '';
foreach ([
    'sales_inventory_reservations',
    'FOR UPDATE',
    'status = "reserved"',
    'inventory_quantity >= :quantity',
    'releaseReservationsForOrder',
    'releaseExpiredReservations',
    'payment_status = "checkout_expired"',
    'Repeated Stripe webhooks are idempotent',
] as $needle) {
    if (!str_contains($repo, $needle)) {
        $failures[] = "SalesRepository missing {$needle}";
    }
}
if (str_contains($repo, 'GREATEST(0, inventory_quantity - :quantity)')) {
    $failures[] = 'Unsafe post-payment GREATEST inventory decrement remains.';
}

$stripe = file_get_contents($root . '/app/Tenant/Sales/StripeCheckoutService.php') ?: '';
if (!str_contains($stripe, "'expires_at' => (string) (time() + 1800)")) {
    $failures[] = 'Stripe Checkout session is not capped at 30 minutes.';
}

$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';
foreach (['available_quantity', 'releaseReservationsForOrder', 'Quantity unavailable'] as $needle) {
    if (!str_contains($controller, $needle)) {
        $failures[] = "SalesController missing {$needle}";
    }
}

$worker = file_get_contents($root . '/scripts/workers/run_once.php') ?: '';
foreach (['sales.inventory.release_expired', 'ReleaseExpiredSalesReservationsJobHandler', "['interval_seconds' => \$interval]"] as $needle) {
    if (!str_contains($worker, $needle)) {
        $failures[] = "Worker missing {$needle}";
    }
}

$migration = file_get_contents($root . '/database/migrations/0043_sales_inventory_reservations.sql') ?: '';
foreach (['CREATE TABLE IF NOT EXISTS sales_inventory_reservations', 'uq_sales_inventory_reservation_order_artwork', 'idx_sales_inventory_reservation_expiry', "'sales.inventory.release_expired'"] as $needle) {
    if (!str_contains($migration, $needle)) {
        $failures[] = "Migration missing {$needle}";
    }
}

$maintenance = file_get_contents($root . '/scripts/maintenance/release_expired_sales_reservations.php') ?: '';
foreach (['require $root . \'/bootstrap/app.php\'', 'Database::connect($root)', 'releaseExpiredReservations'] as $needle) {
    if (!str_contains($maintenance, $needle)) {
        $failures[] = "Maintenance command missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Phase 7 sales inventory static checks passed.\n";

// End of file.
