<?php

declare(strict_types=1);

/**
 * Static coverage for tenant custom-domain routing on tenant hosts and the
 * logged-in unpublished preview footer switch.
 */

$root = dirname(__DIR__, 2);

$files = [
    'tenant_routes' => $root . '/app/Http/Routes/tenant.php',
    'tenant_nav' => $root . '/app/Http/View/TenantAdminNav.php',
    'home' => $root . '/app/Http/Controllers/Tenant/HomeController.php',
    'state' => $root . '/PROJECT_STATE.md',
];

foreach ($files as $label => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$label}: {$path}\n");
        exit(1);
    }
}

$routes = file_get_contents($files['tenant_routes']);
$nav = file_get_contents($files['tenant_nav']);
$home = file_get_contents($files['home']);
$state = file_get_contents($files['state']);

$required = [
    [$routes, "\$router->get('/admin/domains'", 'tenant router must serve /admin/domains on custom-domain hosts'],
    [$routes, "\$router->post('/admin/domains/action'", 'tenant router must serve /admin/domains/action on custom-domain hosts'],
    [$routes, 'TenantAdminDomainsController', 'tenant router must invoke tenant domain controller'],
    [$nav, "'domains' => ['/admin/domains', 'Domains']", 'tenant nav must expose Domains'],
    [$home, 'function unpublishedPreviewEnabled', 'home controller must have preview enabled helper'],
    [$home, 'function unpublishedPreviewFooterSwitch', 'home controller must have footer switch helper'],
    [$home, '$includeUnpublished = $this->unpublishedPreviewEnabled();', 'tenant pages must gate unpublished content behind switch'],
    [$home, '{$this->unpublishedPreviewFooterSwitch()}', 'footer must render preview switch'],
    [$home, 'preview_unpublished', 'preview switch must use preview_unpublished query parameter'],
    [$home, 'Show unpublished sections and images', 'preview switch must show enable label'],
    [$state, 'tenant custom-domain routes and unpublished preview footer switch', 'PROJECT_STATE must record repair'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Tenant custom-domain routes and unpublished preview footer switch static checks passed.\n";

// End of file.
