<?php

/**
 * Verifies that long sales order notes wrap inside the tenant admin order review page.
 */
declare(strict_types=1);

$controllerPath = __DIR__ . '/../../app/Http/Controllers/Tenant/Admin/SalesController.php';
$cssPath = __DIR__ . '/../../public/assets/tenant-admin.css';

$controller = file_get_contents($controllerPath);
$css = file_get_contents($cssPath);

$problems = [];

if ($controller === false || $css === false) {
    fwrite(STDERR, "Could not read sales controller or tenant admin CSS.
");
    exit(1);
}

if (!str_contains($controller, 'sales-order-notes')) {
    $problems[] = 'Sales order notes block is missing the sales-order-notes CSS hook.';
}

foreach (['.sales-order-notes', 'white-space: pre-wrap', 'overflow-wrap: anywhere', 'word-break: break-word'] as $marker) {
    if (!str_contains($css, $marker)) {
        $problems[] = 'Missing notes wrapping CSS marker: ' . $marker;
    }
}

if ($problems !== []) {
    fwrite(STDERR, "Sales notes word-wrap static check failed:
 - " . implode("
 - ", $problems) . "
");
    exit(1);
}

fwrite(STDOUT, "[PASS] Sales notes word-wrap static check passed.
");

// End of file.
