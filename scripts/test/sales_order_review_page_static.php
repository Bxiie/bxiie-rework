<?php

declare(strict_types=1);

$controllerPath = __DIR__ . '/../../app/Http/Controllers/Tenant/Admin/SalesController.php';
$routePath = __DIR__ . '/../../app/Http/Routes/tenant.php';

$controller = file_get_contents($controllerPath);
$routes = file_get_contents($routePath);
if ($controller === false || $routes === false) {
    fwrite(STDERR, "[FAIL] Could not read sales controller or tenant route file.\n");
    exit(1);
}

$failures = [];
$markers = [
    'dedicated order route' => "get('/admin/sales/order'",
    'dedicated order action' => ')->show($request, $tenant, $currentUser)',
    'singular sales redirect' => "get('/admin/sale'",
    'order links point to review page' => '/admin/sales/order?id=',
    'three-argument detailForm signature' => 'private function detailForm(array $order, string $csrf, bool $includeNoSales): string',
    'show passes include-no-sales to detail form' => '$this->detailForm($order, $csrf, $includeNoSales)',
    'show computes include-no-sales flag' => "\$includeNoSales = isset(\$_GET['include_no_sales']);",
];

foreach ($markers as $label => $marker) {
    $haystack = str_starts_with($label, 'dedicated') || str_starts_with($label, 'singular') ? $routes : $controller;
    if (!str_contains($haystack, $marker)) {
        $failures[] = "Missing marker: {$label}";
    }
}

if (!preg_match('/public function index\(.*?\n    public function show\(/s', $controller, $indexMatch)) {
    $failures[] = 'Could not isolate SalesController::index().';
} else {
    $indexBody = $indexMatch[0];
    if (str_contains($indexBody, '$selectedId') || str_contains($indexBody, '$_GET[\'id\']') || str_contains($indexBody, '$this->detailForm(')) {
        $failures[] = 'Sales index still contains inline selected-order detail loading.';
    }
    if (str_contains($indexBody, '{$detail}')) {
        $failures[] = 'Sales index still renders the old inline detail placeholder.';
    }
}

if (preg_match('/public function show\(.*?\n    public function update\(/s', $controller, $showMatch)) {
    $showBody = $showMatch[0];
    if (str_contains($showBody, '$this->detailForm($order, $csrf);')) {
        $failures[] = 'Sales show() still calls detailForm() without includeNoSales.';
    }
} else {
    $failures[] = 'Could not isolate SalesController::show().';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Sales order review page static check failed:\n - " . implode("\n - ", $failures) . "\n");
    exit(1);
}

echo "[PASS] Sales order review page static check passed.\n";

// End of file.
