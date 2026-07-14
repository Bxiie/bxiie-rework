<?php

declare(strict_types=1);

/**
 * Regression checks for tenant-aware timezone page rendering.
 */

$root = dirname(__DIR__, 2);
$controller = (string) file_get_contents(
    $root . '/app/Http/Controllers/Auth/UserTimezoneController.php'
);
$tenantRoutes = (string) file_get_contents(
    $root . '/app/Http/Routes/tenant.php'
);
$platformRoutes = (string) file_get_contents(
    $root . '/app/Http/Routes/platform.php'
);

$failures = [];

$controllerMarkers = [
    'use App\\Http\\View\\TenantAdminLayout;',
    'use App\\Platform\\Tenancy\\TenantContext;',
    'use App\\Tenant\\Settings\\TenantSettingsRepository;',
    'private readonly ?TenantContext $tenant = null',
    'private readonly ?TenantSettingsRepository $tenantSettings = null',
    'private function renderPage(string $title, string $body): string',
    'new TenantAdminLayout($this->tenantSettings)',
    '$this->tenant,',
    "active: 'account'",
];

foreach ($controllerMarkers as $marker) {
    if (!str_contains($controller, $marker)) {
        $failures[] = "UserTimezoneController missing marker: {$marker}";
    }
}

$tenantConstructor = 'new UserTimezoneController('
    . 'new UserRepository($pdo), '
    . 'new CsrfTokenService(), '
    . '$tenant, '
    . '$tenantSettings'
    . ')';

if (substr_count($tenantRoutes, $tenantConstructor) < 2) {
    $failures[] = 'Tenant timezone GET and POST routes do not pass tenant context.';
}

$platformConstructor = 'new UserTimezoneController('
    . 'new UserRepository($pdo), '
    . 'new CsrfTokenService()'
    . ')';

if (substr_count($platformRoutes, $platformConstructor) < 2) {
    $failures[] = 'Platform timezone routes no longer use the platform-only constructor.';
}

if ($failures !== []) {
    fwrite(STDERR, "[FAIL] Tenant timezone layout check failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL]  - {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Timezone pages use the correct tenant or platform admin shell.\n";

// End of file.
