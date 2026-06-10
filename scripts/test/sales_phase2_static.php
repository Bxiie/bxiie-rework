<?php

declare(strict_types=1);

/**
 * Static regression checks for ArtsFolio sales phase two wiring.
 */

$root = dirname(__DIR__, 2);
$required = [
    'database/migrations/0027_sales_checkout_orders.sql' => ['sales_orders', 'sales_cart_items', 'sales_order_items'],
    'app/Tenant/Sales/SalesRepository.php' => ['createOrderFromCart', 'markPaidByStripeSession', 'updateWorkflow'],
    'app/Tenant/Sales/StripeCheckoutService.php' => ['checkout/sessions', 'application_fee_amount', 'transfer_data'],
    'app/Http/Controllers/Tenant/SalesController.php' => ['/cart', 'Checkout with Stripe', 'stripe_secret_key'],
    'app/Http/Controllers/Tenant/Admin/SalesController.php' => ['ordered', 'acknowledged', 'packed', 'shipped'],
    'app/Http/Controllers/Platform/Admin/SalesController.php' => ['Platform-admin sales visibility', 'commission'],
    'app/Http/Controllers/Platform/StripeWebhookController.php' => ['checkout.session.completed', 'HTTP_STRIPE_SIGNATURE'],
    'public/index.php' => ['TenantSalesController', 'StripeWebhookController', '/platform/admin/sales', '/admin/sales'],
];

foreach ($required as $relative => $markers) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$relative}\n");
        exit(1);
    }
    $content = file_get_contents($path) ?: '';
    foreach ($markers as $marker) {
        if (!str_contains($content, $marker)) {
            fwrite(STDERR, "Missing marker {$marker} in {$relative}\n");
            exit(1);
        }
    }
}

echo "Sales phase two static checks passed.\n";

// End of file.
