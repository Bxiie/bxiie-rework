<?php

declare(strict_types=1);

/**
 * Static regression coverage for prefilled Stripe shipping details.
 *
 * ArtsFolio collects shipping details before Stripe Checkout starts. This test
 * ensures the Stripe client sends those local order details to Stripe through a
 * prefilled Customer object and PaymentIntent shipping metadata so buyers do
 * not need to type the same shipping details twice.
 */

$path = __DIR__ . '/../../app/Tenant/Sales/StripeCheckoutService.php';
$source = file_get_contents($path);
if ($source === false) {
    fwrite(STDERR, "Could not read {$path}\n");
    exit(1);
}

$required = [
    "private function orderShippingContact(array \$order): ?array" => 'missing normalized local order shipping helper',
    "private function createCheckoutCustomer(string \$secretKey, array \$order, array \$shipping, ?string \$customerEmail): ?string" => 'missing Stripe Customer prefill helper',
    "https://api.stripe.com/v1/customers" => 'missing Stripe Customer creation endpoint',
    "shipping[name]" => 'missing Customer shipping name payload',
    "shipping[address][line1]" => 'missing Customer shipping address payload',
    "\$payload['customer'] = \$prefilledCustomerId;" => 'Checkout Session is not using the prefilled Customer id',
    "customer_update[shipping]" => 'missing Checkout customer shipping update setting',
    "payment_intent_data[shipping][address][line1]" => 'missing PaymentIntent shipping payload',
    "metadata[artsfolio_checkout_prefill]" => 'missing Stripe Customer prefill metadata',
];

$problems = [];
foreach ($required as $needle => $message) {
    if (strpos($source, $needle) === false) {
        $problems[] = $message;
    }
}

if ($problems !== []) {
    fwrite(STDERR, "Sales Stripe shipping prefill static check failed:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, " - {$problem}\n");
    }
    exit(1);
}

echo "[PASS] Sales Stripe shipping prefill static check passed.\n";

// End of file.
