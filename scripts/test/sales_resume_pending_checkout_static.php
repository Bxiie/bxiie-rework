<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php');
$repository = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php');

$failures = [];

$controllerMarkers = [
    'pending preflight before order creation' => <<<'MARKER'
$pendingResponse = $this->resumePendingCheckout($tenant, (int) $cart['id'], $stripeSecretKey);
MARKER,
    'checkout service reused' => <<<'MARKER'
$checkoutService = new StripeCheckoutService();
MARKER,
    'existing open Stripe checkout redirects' => <<<'MARKER'
if ($sessionStatus === 'open' && $checkoutUrl !== '')
MARKER,
    'paid pending checkout reconciles' => <<<'MARKER'
$this->refreshPaidStripeCheckout($tenant, $sessionId);
MARKER,
    'paid pending checkout expires cart cookie' => <<<'MARKER'
'Set-Cookie' => (new CartIdentityService($this->pdo))->expireCartCookie(),
MARKER,
    'orphaned pending checkout releases reservations' => <<<'MARKER'
$this->sales->releaseReservationsForOrder($orderId, 'checkout_abandoned');
MARKER,
];

$repositoryMarkers = [
    'pending checkout lookup method' => <<<'MARKER'
public function pendingCheckoutForCart(TenantContext $tenant, int $cartId): ?array
MARKER,
    'pending checkout selects by cart' => <<<'MARKER'
AND cart_id = :cart_id
MARKER,
    'pending checkout filters status' => <<<'MARKER'
AND payment_status = "checkout_pending"
MARKER,
];

foreach ($controllerMarkers as $label => $marker) {
    if (strpos($controller, trim($marker)) === false) {
        $failures[] = 'Missing SalesController marker: ' . $label;
    }
}

foreach ($repositoryMarkers as $label => $marker) {
    if (strpos($repository, trim($marker)) === false) {
        $failures[] = 'Missing SalesRepository marker: ' . $label;
    }
}

$orderCreation = strpos($controller, '$order = $this->sales->createOrderFromCart(');
$pendingCheck = strpos($controller, '$pendingResponse = $this->resumePendingCheckout(');
if ($orderCreation === false || $pendingCheck === false || $pendingCheck > $orderCreation) {
    $failures[] = 'Pending checkout preflight must run before createOrderFromCart().';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Sales pending-checkout resume static check failed:
 - " . implode("
 - ", $failures) . "
");
    exit(1);
}

fwrite(STDOUT, "[PASS] Sales pending-checkout resume static check passed.
");

// End of file.
