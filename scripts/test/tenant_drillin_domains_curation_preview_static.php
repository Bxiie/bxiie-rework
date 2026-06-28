<?php

declare(strict_types=1);

/**
 * Static coverage for tenant search drill-in, tenant domains nav, curation placement,
 * and tenant preview footer controls.
 */

$root = dirname(__DIR__, 2);

$files = [
    'tenants_controller' => $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php',
    'tenant_nav' => $root . '/app/Http/View/TenantAdminNav.php',
    'tenant_home' => $root . '/app/Http/Controllers/Tenant/HomeController.php',
    'platform_routes' => $root . '/app/Http/Routes/platform.php',
    'tenant_routes' => $root . '/app/Http/Routes/tenant.php',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$tenants = file_get_contents($files['tenants_controller']);
$nav = file_get_contents($files['tenant_nav']);
$home = file_get_contents($files['tenant_home']);
$platformRoutes = file_get_contents($files['platform_routes']);
$tenantRoutes = file_get_contents($files['tenant_routes']);
$allRoutes = $platformRoutes . "\n" . $tenantRoutes;
$state = file_get_contents($files['state']);

$required = [
    [$tenants, 'function searchTenants', 'Platform tenant search must be server-side'],
    [$tenants, "WHERE t.status <> 'deleted'", 'Platform tenant search/drill-in must exclude deleted tenants consistently'],
    [$tenants, 'private function findTenant(int $tenantId): ?array', 'Platform tenant drill-in must use direct lookup'],
    [$tenants, 'WHERE t.id = :tenant_id', 'Platform tenant drill-in must look up requested id directly'],
    [$tenants, 'ErrorPage::notFound', 'Tenant not-found response must be branded'],
    [$nav, "'domains' => ['/admin/domains', 'Domains']", 'Tenant admin nav must expose Domains'],
    [$allRoutes, "/admin/domains", 'Tenant admin custom-domain routes must exist'],
    [$allRoutes, "TenantAdminDomainsController", 'Tenant admin custom-domain controller must be routed'],
    [$home, 'unpublishedPreviewEnabled()', 'Tenant pages must use preview switch for unpublished content'],
    [$home, 'unpublishedPreviewFooterSwitch()', 'Tenant footer must render preview switch'],
    [$home, 'preview_unpublished', 'Tenant preview switch must use preview_unpublished query flag'],
    [$home, '$body .= $this->curation?->form($tenant->tenantId, (int) $artwork[\'id\'], \'/artwork/\' . (string) $artwork[\'slug\'], $this->currentUser) ?? \'\';', 'Curation controls must be on artwork detail page'],
    [$state, 'tenant drill-in, tenant domains, curation placement, and preview switch', 'PROJECT_STATE must record this repair batch'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    [$home, '$curationForm = $this->curation?->form($tenant->tenantId, (int)$item[\'id\'], \'/\', $this->currentUser) ?? \'\';', 'Curation form must not be on home cards'],
    [$home, '$curationForm = $this->curation?->form($tenant->tenantId, (int)$item[\'id\'], \'/portfolio\', $this->currentUser) ?? \'\';', 'Curation form must not be on portfolio cards'],
    [$home, '</a>{$curationForm}</div>', 'Home card must not append curation form'],
    [$home, '{$curationForm}', 'Portfolio card must not render curation form placeholder'],
];

foreach ($forbidden as [$haystack, $needle, $message]) {
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Forbidden {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Tenant drill-in/domain/curation/preview static checks passed.\n";

// End of file.
