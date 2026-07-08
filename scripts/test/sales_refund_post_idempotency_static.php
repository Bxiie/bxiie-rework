<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SalesController.php') ?: '';
$stripe = file_get_contents($root . '/app/Tenant/Sales/StripeCheckoutService.php') ?: '';
$routes = file_get_contents($root . '/app/Http/Routes/tenant.php') ?: '';

$checks = [
    'refund POST wrapper catches Throwable' => 'catch (Throwable $e)',
    'refund failure is logged' => 'ArtsFolio sales refund failed:',
    'refund problem page exists' => 'private function refundProblemPage(',
    'refund idempotency helper exists' => 'private function refundIdempotencyKey(',
    'controller passes idempotency key to Stripe' => 'refundPaymentIntent($secretKey, $paymentIntentId, $amountCents, $reason, $idempotencyKey)',
    'safe direct GET handler exists' => 'public function refundGet(',
    'safe GET route calls refundGet' => '->refundGet($request, $tenant, $currentUser)',
    'POST route still calls refund' => '->refund($request, $tenant, $currentUser)',
    'Stripe refund accepts idempotency key' => '?string $idempotencyKey = null',
    'Stripe sends idempotency header' => 'Idempotency-Key: ',
];

$failures = [];
foreach ($checks as $label => $needle) {
    $haystack = str_starts_with($label, 'Stripe') ? $stripe : (str_contains($label, 'route') ? $routes : $controller);
    if (!str_contains($haystack, $needle)) {
        $failures[] = $label;
    }
}

if (preg_match("~\$router->get\('/admin/sales/refund'.*?->refund\(~s", $routes)) {
    $failures[] = 'GET /admin/sales/refund must not call refund()';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Sales refund POST idempotency static check failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

echo "[PASS] Sales refund POST idempotency static checks passed.
";

// End of file.
