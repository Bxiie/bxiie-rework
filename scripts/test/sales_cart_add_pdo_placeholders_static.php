<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];

$cartIdentity = file_get_contents($root . '/app/Tenant/Sales/CartIdentityService.php') ?: '';
$salesRepository = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php') ?: '';
$salesController = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';

$required = [
    'app/Tenant/Sales/CartIdentityService.php' => [
        'alias_tenant_id',
        'cart_tenant_id',
        'tokenHash($token)',
    ],
    'app/Tenant/Sales/SalesRepository.php' => [
        'decrement_quantity',
        'inventory_quantity >= :quantity',
        "'quantity' => (int) \$reservation['quantity']",
    ],
    'app/Http/Controllers/Tenant/SalesController.php' => [
        'customer_email = :customer_email',
        'contact_email = :contact_email',
    ],
];

foreach ($required as $relative => $needles) {
    $content = file_get_contents($root . '/' . $relative) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $failures[] = "Missing {$needle} in {$relative}";
        }
    }
}

$forbidden = [
    'CartIdentityService duplicate alias placeholder' => 'a.tenant_id = :tenant_id
               AND a.cart_token_hash = :hash
               AND c.status = "active"
               AND c.tenant_id = :tenant_id',
    'SalesRepository old unsafe duplicate quantity SQL shape' => 'inventory_quantity = GREATEST(0, inventory_quantity - :quantity)',
    'SalesRepository missing distinct decrement placeholder' => 'inventory_quantity = inventory_quantity - :quantity',
    'SalesController duplicate email placeholder' => 'contact_email = :email',
];

foreach ($forbidden as $label => $needle) {
    $haystack = $cartIdentity . "
" . $salesRepository . "
" . $salesController;
    if (str_contains($haystack, $needle)) {
        $failures[] = $label . ' still present.';
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Cart add PDO placeholder static checks failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "Cart add PDO placeholder static checks passed.
";

// End of file.
