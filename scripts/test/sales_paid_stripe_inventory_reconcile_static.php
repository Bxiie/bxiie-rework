<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php');
$failures = [];

if ($source === false) {
    fwrite(STDERR, "[FAIL] Could not read SalesRepository.php\n");
    exit(1);
}

$required = [
    'Paid order consumes active cart on idempotent retry' => 'Idempotent webhook/success retries still consume the cart.',
    'Paid order without reservations does not throw' => 'no inventory reservations were found. Inventory was not decremented automatically.',
    'Released reservation becomes manual review note' => 'was already ' . "' . " . '$reservationStatus',
    'Inventory decrement failure becomes manual review note' => 'reserved variant inventory could not be decremented',
    'Paid status still written after inventory review issue' => 'SET payment_status = "paid",',
    'Order notes record inventory review' => 'Stripe paid reconciliation inventory review:',
    'Cart is checked out after paid reconciliation' => 'UPDATE sales_carts SET status = "checked_out"',
];

foreach ($required as $label => $marker) {
    if (!str_contains($source, $marker)) {
        $failures[] = $label . ' missing marker: ' . $marker;
    }
}

$forbidden = [
    'Paid order has no inventory reservations. Manual review is required.',
    'Paid order reservation is no longer active. Manual review is required.',
    'Reserved variant inventory could not be consumed. Manual review is required.',
];
foreach ($forbidden as $marker) {
    if (str_contains($source, $marker)) {
        $failures[] = 'Old throwing reconciliation marker still present: ' . $marker;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Paid Stripe inventory reconcile static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo "[PASS] Paid Stripe inventory reconcile static check passed.\n";

// End of file.
