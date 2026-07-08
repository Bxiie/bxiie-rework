<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$salesController = file_get_contents($root . '/app/Http/Controllers/Tenant/SalesController.php') ?: '';
$salesRepository = file_get_contents($root . '/app/Tenant/Sales/SalesRepository.php') ?: '';
$stripeCheckout = file_get_contents($root . '/app/Tenant/Sales/StripeCheckoutService.php') ?: '';
$webhook = file_get_contents($root . '/app/Http/Controllers/Platform/StripeWebhookController.php') ?: '';
$migration = file_get_contents($root . '/database/migrations/0064_sales_order_shipping_contact.sql') ?: '';

$checks = [
    'Cart form collects shipping phone' => strpos($salesController, 'name="shipping_phone"') !== false,
    'Cart form collects shipping address line 1' => strpos($salesController, 'name="shipping_line1"') !== false,
    'Checkout validates shipping before Stripe' => strpos($salesController, 'postedShippingContactIsComplete()') !== false,
    'Cart contact persists shipping JSON' => strpos($salesController, 'shipping_address_json = :shipping_address_json') !== false,
    'Order insert copies shipping phone' => strpos($salesRepository, 'shipping_phone, shipping_address_json') !== false,
    'Order insert copies cart shipping contact' => strpos($salesRepository, 'cartShippingContactForOrder($cart)') !== false,
    'Paid reconciliation stores Stripe phone' => strpos($salesRepository, 'shipping_phone = COALESCE(:shipping_phone, shipping_phone)') !== false,
    'Stripe Checkout asks for phone' => strpos($stripeCheckout, "'phone_number_collection[enabled]' => 'true'") !== false,
    'Webhook enriches shipping phone' => strpos($webhook, "shipping['phone']") !== false,
    'Migration adds cart shipping JSON' => strpos($migration, 'ADD COLUMN IF NOT EXISTS shipping_address_json JSON') !== false,
    'Migration adds order shipping phone' => strpos($migration, 'ADD COLUMN IF NOT EXISTS shipping_phone VARCHAR(80)') !== false,
];

$failed = [];
foreach ($checks as $label => $ok) {
    if (!$ok) {
        $failed[] = $label;
    }
}

if ($failed !== []) {
    fwrite(STDERR, "[FAIL] Sales shipping contact collection static check failed:\n");
    foreach ($failed as $label) {
        fwrite(STDERR, ' - ' . $label . "\n");
    }
    exit(1);
}

echo "[PASS] Sales shipping contact collection static check passed.\n";

# End of file.
