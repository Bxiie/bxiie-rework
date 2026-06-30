<?php
/**
 * Static regression checks for phase-two artwork sale admin controls.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'app/Tenant/Sales/ArtworkSaleAdminForm.php' => [
        'final class ArtworkSaleAdminForm',
        'public function render(int $tenantId, array $artwork = []): string',
        'public function saveFromPost(int $tenantId, int $artworkId, array $post, string $saleStatus): void',
        'public function legacyInventoryFromPost(array $post): array',
        'artwork_sale_config',
        'artwork_sale_variants',
        'variant_inventory',
        'shipping_mode',
        'End of file.',
    ],
    'app/Http/Controllers/Tenant/Admin/ArtworksController.php' => [
        'use App\\Tenant\\Sales\\ArtworkSaleAdminForm;',
        '(new ArtworkSaleAdminForm($this->pdo))->render($tenant->tenantId, $artwork)',
        'legacyInventoryFromPost($_POST)',
        'saveFromPost($tenant->tenantId, $id, $_POST, $saleStatus)',
        'Sales &amp; checkout',
    ],
    'app/Http/Controllers/Tenant/Admin/ArtworkUploadController.php' => [
        'use App\\Tenant\\Sales\\ArtworkSaleAdminForm;',
        '(new ArtworkSaleAdminForm($this->pdo))->render($tenant->tenantId)',
        'saveFromPost($tenant->tenantId, $artworkId, $post, (string) ($post[\'sale_status\'] ?? \'nfs\'))',
        'legacyInventoryFromPost($post)',
    ],
    'public/assets/tenant-admin.css' => [
        'Phase-two sales catalog controls',
        '.admin-sale-config',
        '.admin-sale-variant-table',
    ],
    'docs/admin/sales-cart-products.md' => [
        'Phase 2 admin controls',
        'Variant rows',
        'Shipping configuration',
    ],
    'docs/dev/sales-cart-checkout.md' => [
        'Phase 2 admin persistence',
        'ArtworkSaleAdminForm',
    ],
    'docs/user/buying-artwork.md' => [
        'Phase 2 status',
    ],
    'PROJECT_STATE.md' => [
        'Phase 2 adds tenant-admin artwork sale controls',
    ],
];

$missing = [];
foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $missing[] = "Missing file: {$relative}";
        continue;
    }
    $content = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $missing[] = "Missing {$needle} in {$relative}";
        }
    }
}

if ($missing) {
    fwrite(STDERR, "Shopping cart phase 2 static checks failed:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo "Shopping cart phase 2 static checks passed.\n";

// End of file.
