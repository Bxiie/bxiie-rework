<?php

/**
 * Static regression check for tenant sales order shipping display.
 *
 * The order review page must render a readable address from Stripe JSON instead
 * of showing the raw JSON blob or silently hiding missing shipping data.
 */
declare(strict_types=1);

$controllerPath = __DIR__ . '/../../app/Http/Controllers/Tenant/Admin/SalesController.php';
$controller = file_get_contents($controllerPath);
if ($controller === false) {
    fwrite(STDERR, "[FAIL] Could not read tenant sales controller.\n");
    exit(1);
}

$failures = [];
$requiredMarkers = [
    'customer uses normalized shipping renderer' => '$shippingHtml = $this->shippingAddressHtml($order);',
    'readable shipping address label' => 'Shipping address:',
    'address element for formatted display' => '<address class="admin-shipping-address">',
    'Stripe JSON decode' => 'json_decode((string) $raw, true)',
    'Stripe shipping details support' => "shipping_details",
    'explicit empty state' => 'No shipping address recorded.',
    'raw fallback is not code block' => 'nl2br($this->e((string) $address[\'raw\']), false)',
];
foreach ($requiredMarkers as $label => $marker) {
    if (!str_contains($controller, $marker)) {
        $failures[] = "Missing marker: {$label}";
    }
}

if (preg_match('/private function customerHtml\(.*?\n    \}\n/s', $controller, $match)) {
    $customerHtml = $match[0];
    if (str_contains($customerHtml, 'shipping_address_json') || str_contains($customerHtml, '<code>')) {
        $failures[] = 'customerHtml() still directly renders raw shipping JSON.';
    }
} else {
    $failures[] = 'Could not isolate customerHtml().';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Sales shipping address render static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "[PASS] Sales shipping address render static check passed.\n";

// End of file.
