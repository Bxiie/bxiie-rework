<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$repo = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php') ?: '';
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';

$required = [
    'public function cartShippingAllocations(array $items): array',
    'return $this->shippingAllocations($items);',
    '$shippingAllocations = $this->sales->cartShippingAllocations($items);',
    '$shippingTotal = array_sum($shippingAllocations);',
    '$shipping = (int) ($shippingAllocations[$itemId] ?? $this->lineShippingCents($item));',
];

$missing = [];
foreach ($required as $needle) {
    if (!str_contains($repo . "\n" . $controller, $needle)) {
        $missing[] = $needle;
    }
}

$badBlock = "\$shippingTotal = 0;\n        foreach (\$items as \$item) {\n            \$line = (int) \$item['quantity'] * (int) \$item['unit_price_cents'];\n            \$shipping = \$this->lineShippingCents(\$item);";
if (str_contains($controller, $badBlock)) {
    $missing[] = 'Cart page still totals shipping line-by-line instead of by grouped shipping profile.';
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Cart shipping profile grouping static check failed:\n");
    foreach ($missing as $needle) {
        fwrite(STDERR, "[FAIL]  - {$needle}\n");
    }
    exit(1);
}

echo "Cart shipping profile grouping static checks passed.\n";

// End of file.
