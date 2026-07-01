<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$home = file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php') ?: '';
foreach ([
    'private function cartChrome',
    'tenant-cart-link',
    'href="/cart"',
    'private function cartMoney',
    'item_count',
] as $needle) {
    if (!str_contains($home, $needle)) {
        $failures[] = "HomeController missing {$needle}";
    }
}
if (str_contains($home, '$this->money((int) ($summary')) {
    $failures[] = 'HomeController cartChrome still calls unavailable money helper.';
}

$sales = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';
foreach ([
    'private function tenantPageResponse',
    'private function tenantPage',
    '<header class="site-header">',
    '<a class="brand" href="/">',
    '<link rel="stylesheet" href="/tenant.css">',
    'cart-page-surface',
    'tenant-cart-link',
    'return $this->tenantPageResponse($tenant, \'Shopping cart\'',
    'return $this->tenantPageResponse($tenant, \'Checkout could not be started\'',
    'return $this->tenantPageResponse($tenant, \'Order received\'',
    '$this->csrf->validate',
] as $needle) {
    if (!str_contains($sales, $needle)) {
        $failures[] = "SalesController missing {$needle}";
    }
}
foreach ([
    '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Cart</title>',
    '<!doctype html><html><head><meta charset="utf-8"><title>Order received</title>',
    '$this->csrf->verify',
] as $forbidden) {
    if (str_contains($sales, $forbidden)) {
        $failures[] = "SalesController still contains unbranded/obsolete marker {$forbidden}";
    }
}

$css = file_get_contents($root . '/public/assets/site.css') ?: '';
foreach (['Cart branding and navigation repair', '.tenant-cart-link', '.cart-page-surface'] as $needle) {
    if (!str_contains($css, $needle)) {
        $failures[] = "site.css missing {$needle}";
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Cart branding and navigation static checks failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "Cart branding and navigation static checks passed.\n";

// End of file.
