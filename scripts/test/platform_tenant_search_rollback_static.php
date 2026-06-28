<?php

declare(strict_types=1);

/**
 * Static coverage for emergency rollback of Platform Admin tenant search.
 */

$root = dirname(__DIR__, 2);
$tenantsPath = $root . '/app/Http/Controllers/Platform/Admin/TenantsController.php';

if (!is_file($tenantsPath)) {
    fwrite(STDERR, "Missing TenantsController: {$tenantsPath}\n");
    exit(1);
}

$source = file_get_contents($tenantsPath);

$forbidden = [
    '$this->tenantSearchPanel() .',
    'function tenantSearchPanel',
    'function tenantSearchTableExists',
    'function tenantSearchColumnExists',
    'platform-tenant-search-card',
    'Search results across all tenants',
];

foreach ($forbidden as $needle) {
    if (str_contains($source, $needle)) {
        fwrite(STDERR, "TenantsController still contains tenant search rollback target: {$needle}\n");
        exit(1);
    }
}

$required = [
    [$source, 'AdminLayout::render', 'tenants controller must still render through AdminLayout'],
    [$source, 'Tenants', 'tenants controller must still contain tenant page title/content'],
];

foreach ($required as [$haystack, $needle, $message]) {
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Missing {$message}: {$needle}\n");
        exit(1);
    }
}

echo "Platform tenant search rollback static checks passed.\n";

// End of file.
