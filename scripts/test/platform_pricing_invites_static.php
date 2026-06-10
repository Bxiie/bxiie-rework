<?php

declare(strict_types=1);

/**
 * Static regression checks for pricing, tenant delete confirmation, and invite resend controls.
 */

$root = dirname(__DIR__, 2);
$checks = [
    'app/Http/Controllers/Platform/Admin/TenantsController.php' => ['confirm_delete', '/platform/admin/tenants/delete', 'prompt(&quot;Type delete'],
    'app/Platform/Tenants/TenantAdminRepository.php' => ["WHERE t.status <> 'deleted'", "'deleted'"],
    'app/Http/Controllers/Platform/Admin/PricingController.php' => ['new_plan[slug]', 'INSERT INTO plans', 'platform_sales_commission_percent'],
    'app/Http/Controllers/Platform/Admin/UsersController.php' => ['/platform/admin/users/invite', '/platform/admin/users/resend-invite', 'queueInviteEmail'],
    'app/Http/Controllers/Tenant/Admin/UsersController.php' => ['/admin/users/resend-invite', 'tenant.user.invite_resent'],
    'app/Http/Controllers/Tenant/HomeController.php' => ['--menu-bg-image:none', '(float) $menuOpacity > 0.0'],
    'app/Http/View/TenantAdminLayout.php' => ['--menu-bg-image:none', '(float) $menuOpacity > 0.0'],
    'public/assets/site.css' => ['Tenant nav and form polish', '.link-button'],
    'database/migrations/0024_seed_strawman_pricing_plans.sql' => ['Studio', 'Professional', 'Collective'],
    'database/migrations/0025_tenant_deleted_status.sql' => ["'deleted'", 'MODIFY status'],
];

$failures = [];
foreach ($checks as $relative => $needles) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        $failures[] = "Missing file: {$relative}";
        continue;
    }
    $content = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $failures[] = "Missing marker in {$relative}: {$needle}";
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Platform pricing and invite static checks passed.\n";

// End of file.
