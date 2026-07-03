<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php');
$routes = file_get_contents($root . '/app/Http/Routes/tenant.php');

$failures = [];

foreach ([
    "formaction=\"/cart/remove\"",
    'formnovalidate',
    'remove_item_id',
    'public function remove(Request $request, TenantContext $tenant): Response',
    'updateQuantity((int) $cart[\'id\'], $itemId, 0)',
] as $fragment) {
    if (!str_contains($controller, $fragment)) {
        $failures[] = 'SalesController missing cart remove/no-email marker: ' . $fragment;
    }
}

if (str_contains($controller, 'public function update(Request $request, TenantContext $tenant): Response') && !str_contains($controller, 'public function remove(Request $request, TenantContext $tenant): Response')) {
    $failures[] = 'SalesController missing remove action.';
}

if (!str_contains($routes, "post('/cart/remove'")) {
    $failures[] = 'Tenant routes missing POST /cart/remove.';
}

$updateStart = strpos($controller, 'public function update(Request $request, TenantContext $tenant): Response');
$updateEnd = strpos($controller, 'public function remove(Request $request, TenantContext $tenant): Response');
if ($updateStart === false || $updateEnd === false) {
    $failures[] = 'Could not isolate update/remove methods.';
} else {
    $updateBlock = substr($controller, $updateStart, $updateEnd - $updateStart);
    if (str_contains($updateBlock, 'saveCartContact')) {
        $failures[] = 'Cart quantity update must not save or require cart contact fields.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Cart remove/no-email static checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    }
    exit(1);
}

echo "Cart remove/no-email static checks passed." . PHP_EOL;

// End of file.
