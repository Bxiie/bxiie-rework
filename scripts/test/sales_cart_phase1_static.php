<?php

declare(strict_types=1);

/**
 * Static regression checks for shopping-cart phase 1 schema and documentation.
 */

$root = dirname(__DIR__, 2);
$failures = [];

$requiredFiles = [
    'database/migrations/0054_cart_variants_shipping_aliases.sql',
    'docs/dev/sales-cart-checkout.md',
    'docs/admin/sales-cart-products.md',
    'docs/user/buying-artwork.md',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Missing {$file}";
    }
}

$migration = is_file($root . '/database/migrations/0054_cart_variants_shipping_aliases.sql')
    ? (string) file_get_contents($root . '/database/migrations/0054_cart_variants_shipping_aliases.sql')
    : '';

foreach ([
    'CREATE TABLE IF NOT EXISTS artwork_sale_config',
    'CREATE TABLE IF NOT EXISTS artwork_sale_variants',
    'CREATE TABLE IF NOT EXISTS sales_cart_aliases',
    'cart_token_hash CHAR(64) NOT NULL',
    'domain_host VARCHAR(255) NOT NULL',
    'ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED NULL AFTER artwork_id',
    'ADD COLUMN IF NOT EXISTS shipping_cents INT UNSIGNED NOT NULL DEFAULT 0',
    'ADD COLUMN IF NOT EXISTS abandoned_1d_email_sent_at',
    'ADD COLUMN IF NOT EXISTS abandoned_3d_email_sent_at',
    'ADD COLUMN IF NOT EXISTS abandoned_7d_email_sent_at',
    'INSERT INTO artwork_sale_config',
    'INSERT INTO artwork_sale_variants',
    'UPDATE sales_cart_items ci',
    'Keep uq_sales_cart_items_artwork',
] as $needle) {
    if (!str_contains($migration, $needle)) {
        $failures[] = "Migration missing marker: {$needle}";
    }
}

if (str_contains($migration, 'DROP INDEX IF EXISTS uq_sales_cart_items_artwork')) {
    $failures[] = 'Phase 1 must not drop uq_sales_cart_items_artwork before runtime add-to-cart writes variant_id.';
}

$devDoc = is_file($root . '/docs/dev/sales-cart-checkout.md') ? (string) file_get_contents($root . '/docs/dev/sales-cart-checkout.md') : '';
foreach (['sales_cart_aliases', 'first-party cart cookie', 'Phase 3', 'Stripe Checkout'] as $needle) {
    if (!str_contains($devDoc, $needle)) {
        $failures[] = "Developer cart doc missing marker: {$needle}";
    }
}

$projectState = is_file($root . '/PROJECT_STATE.md') ? (string) file_get_contents($root . '/PROJECT_STATE.md') : '';
foreach (['0054_cart_variants_shipping_aliases.sql', 'domain-portable', 'sales_cart_aliases'] as $needle) {
    if (!str_contains($projectState, $needle)) {
        $failures[] = "PROJECT_STATE.md missing marker: {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Shopping cart phase 1 static checks failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Shopping cart phase 1 static checks passed.\n";

// End of file.
