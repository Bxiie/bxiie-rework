<?php

declare(strict_types=1);

/**
 * Verifies direct browser access to /admin/sales/refund is a redirect, while
 * live Stripe refund creation remains POST-only.
 */

$root = dirname(__DIR__, 2);
$routePath = $root . '/app/Http/Routes/tenant.php';

if (!is_file($routePath)) {
    fwrite(STDERR, "Missing tenant route file.\n");
    exit(1);
}

$routes = (string) file_get_contents($routePath);
$problems = [];

if (!str_contains($routes, "\$router->get('/admin/sales/refund', function (Request \$request): Response")) {
    $problems[] = 'Missing safe GET /admin/sales/refund redirect route.';
}

if (!str_contains($routes, "Actual Stripe refunds are created only by POST /admin/sales/refund.")) {
    $problems[] = 'Missing POST-only safety comment on refund GET route.';
}

if (!str_contains($routes, "\$router->post('/admin/sales/refund'")) {
    $problems[] = 'Missing POST /admin/sales/refund route for real refund creation.';
}

$getRefundRouteOffset = strpos($routes, "\$router->get('/admin/sales/refund'");
if ($getRefundRouteOffset !== false) {
    $getRefundRouteEnd = strpos($routes, "\$router->post('/admin/sales/refund'", $getRefundRouteOffset);
    $getRefundRouteBlock = $getRefundRouteEnd === false ? substr($routes, $getRefundRouteOffset, 1200) : substr($routes, $getRefundRouteOffset, $getRefundRouteEnd - $getRefundRouteOffset);
    if (str_contains($getRefundRouteBlock, '->refund(')) {
        $problems[] = 'GET /admin/sales/refund must not call the live refund handler.';
    }
}

if (!str_contains($routes, "'/admin/sales/order?id=' . \$orderId . '&notice=refund_direct'")) {
    $problems[] = 'Refund GET route should preserve an order id by redirecting to the order page.';
}

if ($problems !== []) {
    fwrite(STDERR, "Sales refund direct route static check failed:\n");
    foreach ($problems as $problem) {
        fwrite(STDERR, " - {$problem}\n");
    }
    exit(1);
}

echo "Sales refund direct route static check passed.\n";

// End of file.
