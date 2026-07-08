<?php

declare(strict_types=1);

/**
 * Regression check for tenant-admin sales order review navigation.
 *
 * The list page should remain a sales table. Clicking an order should open the
 * dedicated order review page instead of expanding the order on the same page.
 */
$root = dirname(__DIR__, 2);
$routes = file_get_contents($root . '/app/Http/Routes/tenant.php') ?: '';
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SalesController.php') ?: '';

$required = [
    'dedicated order route' => "get('/admin/sales/order'",
    'singular sales redirect route' => "get('/admin/sale'",
    'order review action' => 'public function show(Request $request, TenantContext $tenant, ?array $currentUser): Response',
    'order links point to review page' => '/admin/sales/order?id=',
    'back link to sales list' => 'Back to sales',
];

$missing = [];
foreach ($required as $label => $needle) {
    $haystack = str_contains($label, 'route') ? $routes : $controller;
    if (!str_contains($haystack, $needle)) {
        $missing[] = "Missing {$label}: {$needle}";
    }
}

$indexStart = strpos($controller, 'public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response');
$indexEnd = $indexStart === false ? false : strpos($controller, '\n    public function ', $indexStart + 1);
$indexBody = ($indexStart === false || $indexEnd === false) ? '' : substr($controller, $indexStart, $indexEnd - $indexStart);

if (str_contains($indexBody, '$selectedId') || str_contains($indexBody, '$_GET[\'id\']') || str_contains($indexBody, '$this->detailForm(')) {
    $missing[] = 'Sales index still contains inline selected-order detail loading.';
}

if (str_contains($indexBody, '{$detail}')) {
    $missing[] = 'Sales index still renders the old inline detail placeholder.';
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Sales order review page static check failed:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo "[PASS] Sales order review page static check passed.\n";

// End of file.
