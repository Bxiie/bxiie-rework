<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php');
$failures = [];

if ($source === false) {
    fwrite(STDERR, "[FAIL] Could not read SalesController.php\n");
    exit(1);
}

$required = [
    'Checkout success catches Stripe refresh failures' => 'try {' . "\n" . '                $this->refreshPaidStripeCheckout($tenant, $sessionId);',
    'Checkout success logs failures with marker' => '[ArtsFolio checkout/success]',
    'Checkout success avoids generic 500 for order lookup failures' => 'Order status unavailable',
    'Checkout success keeps rendering when order items fail' => 'Missing line-item details should not convert a successful checkout',
    'Checkout success logs to checkout_success.log' => "checkout_success.log",
    'Checkout success warns buyer not to pay twice' => 'do not pay again until the order is reconciled',
];

foreach ($required as $label => $marker) {
    if (!str_contains($source, $marker)) {
        $failures[] = $label . ' missing marker: ' . $marker;
    }
}

$successStart = strpos($source, 'public function success(Request $request, TenantContext $tenant): Response');
$tenantResponse = strpos($source, 'private function tenantPageResponse', $successStart ?: 0);
$successBlock = $successStart !== false && $tenantResponse !== false ? substr($source, $successStart, $tenantResponse - $successStart) : '';
if ($successBlock === '' || !str_contains($successBlock, 'catch (Throwable $e)')) {
    $failures[] = 'SalesController::success does not contain buyer-safe Throwable handling.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Checkout success 500 guard static check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

echo "[PASS] Checkout success 500 guard static check passed.\n";

// End of file.
