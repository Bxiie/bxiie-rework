<?php

declare(strict_types=1);

/**
 * Regression check for the tenant-admin refund route contract.
 *
 * Refund creation must remain POST-only, but direct GET loads of the refund URL
 * should redirect back to the Sales desk instead of producing an opaque error.
 */
$root = dirname(__DIR__, 2);
$routes = file_get_contents($root . '/app/Http/Routes/tenant.php') ?: '';
$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/SalesController.php') ?: '';

$required = [
    'safe GET refund route' => "get('/admin/sales/refund'",
    'POST refund route' => "post('/admin/sales/refund'",
    'GET refund entry method' => 'public function refundEntry(Request $request, TenantContext $tenant, ?array $currentUser): Response',
    'refund entry redirect notice' => 'refund_direct_link',
    'refund creation remains POST method' => 'public function refund(Request $request, TenantContext $tenant, ?array $currentUser): Response',
    'refund catch returns error page' => 'return $this->errorPage',
];

$missing = [];
foreach ($required as $label => $needle) {
    $haystack = str_contains($label, 'route') ? $routes : $controller;
    if (!str_contains($haystack, $needle)) {
        $missing[] = "Missing {$label}: {$needle}";
    }
}

if (str_contains($controller, "} catch (Throwable \$e) {\n        } catch (Throwable \$e) {")) {
    $missing[] = 'Duplicate empty Throwable catch block still exists in refund handler.';
}

if ($missing !== []) {
    fwrite(STDERR, "[FAIL] Sales refund direct route static check failed:\n - " . implode("\n - ", $missing) . "\n");
    exit(1);
}

echo "[PASS] Sales refund direct route static check passed.\n";

// End of file.
