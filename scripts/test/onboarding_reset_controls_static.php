#!/usr/bin/php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$files = [
    'tenant_controller' => $root . '/app/Http/Controllers/Tenant/Admin/OnboardingController.php',
    'tenant_page' => $root . '/app/Http/Controllers/Tenant/Admin/OnboardingPageController.php',
    'platform_tenants' => $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php',
    'tenant_routes' => $root . '/app/Http/Routes/tenant.php',
    'platform_routes' => $root . '/app/Http/Routes/platform.php',
];
$content = [];
foreach ($files as $name => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "[FAIL] Missing {$name}: {$path}\n");
        exit(1);
    }
    $content[$name] = (string) file_get_contents($path);
}
$checks = [
    'tenant onboarding page has reset form' => str_contains($content['tenant_page'], 'action="/admin/onboarding/reset"'),
    'tenant reset route is POST' => str_contains($content['tenant_routes'], '$router->post(\'/admin/onboarding/reset\''),
    'platform tenant page has reset form' => str_contains($content['platform_tenants'], 'action="/platform/admin/tenants/onboarding/reset"'),
    'platform reset route is POST' => str_contains($content['platform_routes'], '$router->post(\'/platform/admin/tenants/onboarding/reset\''),
    'tenant reset requires CSRF' => str_contains($content['tenant_controller'], '$this->csrf->validate'),
    'tenant reset is role protected' => str_contains($content['tenant_controller'], '$this->roles->allows'),
    'reset is tenant scoped' => str_contains($content['tenant_controller'], 'WHERE tenant_id = :tenant_id'),
    'tenant reset is audited' => str_contains($content['tenant_controller'], 'tenant.onboarding.reset'),
    'platform reset is audited' => str_contains($content['platform_tenants'], 'platform.tenant.onboarding_reset'),
];
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}
echo "[PASS] Onboarding reset controls static check passed.\n";

// End of file.
