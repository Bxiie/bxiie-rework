<?php

declare(strict_types=1);

$path = __DIR__ . '/../../app/Tenant/Sales/SalesRepository.php';
$contents = file_get_contents($path);
$failures = [];

foreach ([
    'private function syncLegacyArtworkInventoryFromVariants(int $tenantId, int $artworkId): void',
    'Variant inventory is now the checkout source of truth',
    'COALESCE(SUM(CASE WHEN v.is_active = 1 THEN GREATEST(v.inventory_quantity, 0) ELSE 0 END), 0) AS active_inventory',
    'UPDATE artworks',
    'inventory_quantity = :inventory_quantity',
    'markPaidByStripeSession(',
    '$this->syncLegacyArtworkInventoryFromVariants($tenantId, $artworkId);',
] as $marker) {
    if (!str_contains($contents, $marker)) {
        $failures[] = 'Missing marker: ' . $marker;
    }
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Legacy inventory sync method static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

fwrite(STDOUT, "[PASS] Legacy inventory sync method static check passed.\n");

// End of file.
