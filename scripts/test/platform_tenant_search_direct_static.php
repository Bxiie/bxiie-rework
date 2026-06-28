<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$path = $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php';

if (!is_file($path)) {
    fwrite(STDERR, "Missing TenantsController: {$path}\n");
    exit(1);
}

$source = file_get_contents($path);

$required = [
    [$source, '$tenantSearchRaw = trim((string) ($_GET[\'q\'] ?? \'\'));', 'index must read q'],
    [$source, '$tenantRows = $tenantSearchRaw !== \'\' ? $this->searchTenants($tenantSearchRaw) : $this->tenants->latest();', 'index must choose search results or latest'],
    [$source, 'foreach ($tenantRows as $tenant)', 'index must render chosen tenant rows'],
    [$source, 'platform-tenant-search', 'index must render search form'],
    [$source, 'Searches all tenants', 'tenant search help text must explain all-tenant search'],
    [$source, 'function searchTenants', 'controller must include server-side search method'],
    [$source, 'tenantSearchTableExists', 'search must guard optional tables'],
    [$source, 'tenantSearchColumnExists', 'search must guard optional columns'],
    [$source, 'tenant_plan_assignments', 'search should include billing/plan terms when available'],
    [$source, 'LIMIT 250', 'search must cap result set'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

$forbidden = [
    '$this->tenantSearchPanel() .',
    'function tenantSearchPanel',
    'platform-tenant-search-results',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "Forbidden old tenant search injection remains: {$needle}\n");
        exit(1);
    }
}

echo "Direct server-side Platform tenant search static checks passed.\n";

// End of file.
