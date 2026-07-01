<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$home = file_get_contents($root . '/app/Http/Controllers/Tenant/HomeController.php') ?: '';
foreach (['private function cartChrome', 'site-cart-link tenant-cart-link', 'item_count', "return '';", 'misleading cart link'] as $needle) {
    if (!str_contains($home, $needle)) {
        $failures[] = "HomeController missing {$needle}";
    }
}
if (str_contains($home, 'href="/cart" aria-label="Shopping cart">Cart</a>')) {
    $failures[] = 'HomeController still renders an empty-cart fallback link.';
}

$sales = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';
foreach (['releaseCancelledCheckout', 'checkout=cancelled&order_id=', 'checkout_cancelled', 'cartChromeForTenantPage', 'return new Response'] as $needle) {
    if (!str_contains($sales, $needle)) {
        $failures[] = "SalesController missing {$needle}";
    }
}
foreach (['expireCartCookie()]);', 'markCartCheckedOut((int) $cart', 'href="/cart" aria-label="Shopping cart">Cart</a>'] as $forbidden) {
    if (str_contains($sales, $forbidden)) {
        $failures[] = "SalesController still contains {$forbidden}";
    }
}

$state = file_get_contents($root . '/PROJECT_STATE.md') ?: '';
if (!str_contains($state, 'Cart navigation visibility and Stripe checkout cancellation')) {
    $failures[] = 'PROJECT_STATE.md missing cart navigation/cancel note.';
}

if ($failures !== []) {
    fwrite(STDERR, "Cart nav visibility and checkout cancel static checks failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "Cart nav visibility and checkout cancel static checks passed.
";

// End of file.
