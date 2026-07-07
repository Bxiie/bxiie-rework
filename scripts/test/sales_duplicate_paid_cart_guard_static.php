<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';
$repo = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php') ?: '';

$missing = [];
foreach ([
    'checkout paid-cart guard call' => '$this->sales->paidOrderForCart($tenant, (int) $cart[\'id\'])',
    'paid cart expires cookie' => '$identity->expireCartCookie()',
    'paid cart redirects to success' => '/checkout/success?session_id=',
    'repository paid cart lookup' => 'public function paidOrderForCart(TenantContext $tenant, int $cartId): ?array',
    'paid statuses in cart lookup' => 'payment_status IN ("paid", "complete", "succeeded", "partially_refunded")',
] as $label => $needle) {
    $haystack = str_contains($label, 'repository') || str_contains($label, 'statuses') ? $repo : $controller;
    if (!str_contains($haystack, $needle)) {
        $missing[] = $label . ': ' . $needle;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Duplicate paid cart guard static check failed:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo "[PASS] Duplicate paid cart guard static check passed.\n";

// End of file.
