<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$repoPath = $root . '/app/Tenant/Sales/SalesRepository.php';
$repo = is_file($repoPath) ? (file_get_contents($repoPath) ?: '') : '';

foreach ([
    'private function lineShippingCents(array $item): int',
    'shipping_price_cents',
    'shipping_additional_item_cents',
    'max(0, $quantity - 1)',
    '$shippingTotal += $this->lineShippingCents($item)',
    '$lineShipping = $this->lineShippingCents',
] as $needle) {
    if (!str_contains($repo, $needle)) {
        $failures[] = "SalesRepository missing {$needle}";
    }
}

if (substr_count($repo, 'function lineShippingCents') !== 1) {
    $failures[] = 'SalesRepository should contain exactly one lineShippingCents method.';
}

if ($failures !== []) {
    fwrite(STDERR, "Cart checkout line-shipping static checks failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "Cart checkout line-shipping static checks passed.
";

// End of file.
