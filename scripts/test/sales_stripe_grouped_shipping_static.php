<?php

declare(strict_types=1);

$controller = file_get_contents(__DIR__ . '/../../app/Http/Controllers/Tenant/SalesController.php');
$repository = file_get_contents(__DIR__ . '/../../app/Tenant/Sales/SalesRepository.php');

$failures = [];

foreach ([
    'SalesRepository exposes grouped cart shipping allocations' => 'public function cartShippingAllocations(array $items): array',
    'SalesController saleEconomics uses grouped shipping allocations' => '$shipping = array_sum($this->sales->cartShippingAllocations($items));',
    'Stripe Checkout receives the persisted order shipping total' => "(int) (\$order['shipping_cents'] ?? \$fees['shipping_cents'])",
] as $label => $needle) {
    if (!str_contains($label === 'SalesRepository exposes grouped cart shipping allocations' ? $repository : $controller, $needle)) {
        $failures[] = $label;
    }
}

if (preg_match('/private function saleEconomics[\s\S]*?\$shipping \+= \$this->lineShippingCents\(\$item\);[\s\S]*?private function planPaymentFees/', $controller)) {
    $failures[] = 'saleEconomics still sums per-line legacy shipping';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Stripe grouped shipping static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

fwrite(STDOUT, "[PASS] Stripe grouped shipping static check passed.\n");

// End of file.
