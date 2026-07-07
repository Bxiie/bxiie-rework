<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$salesController = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php');
$stripeService = file_get_contents($root . '/app/Tenant/Sales/StripeCheckoutService.php');
$repository = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php');

$required = [
    'SalesController success finalizes paid Stripe sessions' => 'refreshPaidStripeCheckout($tenant, $sessionId)',
    'SalesController retrieves session from Stripe' => '->retrieveSession((string) $this->platformSettings->get',
    'SalesController verifies session order ownership' => 'stripeSessionMatchesOrder(array $session, array $order)',
    'SalesController expires current cart cookie after paid success' => "expireCartCookie()",
    'SalesController renders order details on success' => 'orderSummaryHtml($order, $items)',
    'Stripe service can retrieve Checkout Sessions' => 'public function retrieveSession(string $secretKey, string $sessionId): array',
    'Repository marks paid source cart checked out' => 'UPDATE sales_carts SET status = "checked_out"',
];

$haystacks = [$salesController, $stripeService, $repository];
$missing = [];
foreach ($required as $label => $needle) {
    $found = false;
    foreach ($haystacks as $haystack) {
        if (str_contains($haystack, $needle)) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $missing[] = $label . ': ' . $needle;
    }
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Stripe checkout success finalization static check failed:
 - " . implode("
 - ", $missing) . "
");
    exit(1);
}

echo "[PASS] Stripe checkout success finalization static check passed.
";

// End of file.
