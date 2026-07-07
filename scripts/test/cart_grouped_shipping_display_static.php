<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$salesController = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php');

$required = [
    <<<'NEEDLE'
$shippingAllocations = $this->sales->cartShippingAllocations($items);
NEEDLE,
    <<<'NEEDLE'
$shipping = (int) ($shippingAllocations[(int) $item['id']] ?? $this->lineShippingCents($item));
NEEDLE,
];

$missing = [];
foreach ($required as $needle) {
    if (!str_contains($salesController, $needle)) {
        $missing[] = $needle;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Cart grouped shipping display static check failed:
 - " . implode("
 - ", $missing) . "
");
    exit(1);
}

echo "[PASS] Cart grouped shipping display static check passed.
";

// End of file.
